<?php

/**
 * @file
 * handle_wfs_get_feature_request.inc
 */

function handle_wfs_get_feature_request($query_params) {
  global $base_url;

  $host = $base_url;

  $requested_type_names = array_filter(explode(',', $query_params['TYPENAME'] ?? ''));

  dpm($query_params['FILTER'] ?? null);

  $known_type_names = array('farmos:PointArea', 'farmos:PolygonArea', 'farmos:LineStringArea');

  $unknown_type_names = array_diff_key($requested_type_names, $known_type_names);

  if (!empty($unknown_type_names)) {
    print farmos_wfs_makeExceptionReport(function($eReport, $elem) {
      $eReport->appendChild($elem('Exception', array(
        "exceptionCode"  => "InvalidParameterValue",
        "locator" => "typename",
      )));
    });
    drupal_exit();
    return;
  }

  if (empty($requested_type_names)) {
    print farmos_wfs_makeExceptionReport(function($eReport, $elem) {
      $eReport->appendChild($elem('Exception', array(
        "exceptionCode"  => "MissingParameterValue",
        "locator" => "typename",
      )));
    });
    drupal_exit();
    return;
  }

  $requested_type_names_by_geofield_geo_type = array();

  foreach($requested_type_names as $type_name) {
    $geo_type = strtolower(preg_replace('/^farmos:(.*)Area$/', '$1', $type_name));

    $requested_type_names_by_geofield_geo_type[$geo_type] = $type_name;
  }

  $requested_geometry_types = array_keys($requested_type_names_by_geofield_geo_type);

  $query = new EntityFieldQuery();
  $query->entityCondition('entity_type', 'taxonomy_term')
    ->entityCondition('bundle', 'farm_areas')
    ->fieldCondition('field_farm_geofield', 'geo_type', $requested_geometry_types, 'IN');

  $result = $query->execute();
  if (isset($result['taxonomy_term'])) {
    $area_ids = array_keys($result['taxonomy_term']);
    $areas = entity_load('taxonomy_term', $area_ids);
  } else {
    $areas = array();
  }

  dpm($areas);

  print farmos_wfs_makeDoc(function($doc, $elem) use ($host, $query_params, $requested_type_names, $areas, $requested_type_names_by_geofield_geo_type) {
    $doc->appendChild($elem('wfs:FeatureCollection', array(
      "xmlns:farmos" => "https://farmos.org/wfs",
      'xmlns:gml' => "http://www.opengis.net/gml",
      'xmlns:wfs' => "http://www.opengis.net/wfs",
      'xmlns:ogc' => "http://www.opengis.net/ogc",
      'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
      'xsi:schemaLocation' => "https://farmos.org/wfs " .
        "$host/wfs?SERVICE=WFS&VERSION=1.1.0&REQUEST=DescribeFeatureType&TYPENAME={$query_params['TYPENAME']}&OUTPUTFORMAT=text/xml;%20subtype=gml/3.1.1 " .
        "http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.1.0/wfs.xsd",
    ), function($featureCollection, $elem) use ($requested_type_names, $areas, $requested_type_names_by_geofield_geo_type) {

      foreach($areas as $area) {

        $geo_type = $area->field_farm_geofield[LANGUAGE_NONE][0]['geo_type'];
        $type_name = $requested_type_names_by_geofield_geo_type[$geo_type];

        $geometry_type = preg_replace('/^farmos:(.*)Area$/', '$1', $type_name);

        // Get WKT from the field. If empty, bail.
        if (empty($area->field_farm_geofield[LANGUAGE_NONE][0]['geom'])) {
          continue;
        }

        $wkt = $area->field_farm_geofield[LANGUAGE_NONE][0]['geom'];

        geophp_load();
        $geom = geoPHP::load($wkt, 'wkt');

        // If the geometry is empty, bail.
        if ($geom->isEmpty()) {
          continue;
        }

        $featureCollection->appendChild($elem('gml:featureMember', [],
          function($featureMember, $elem) use ($area, $geometry_type, $geom) {

            $featureMember->appendChild($elem("farmos:{$geometry_type}Area", array(
              'gml:id'  => "{$geometry_type}Area.{$area->tid}",
            ), function($feature, $elem) use ($area, $geom) {

              $feature->appendChild($elem('farmos:geometry', [],
                function($geometry, $elem) use ($area, $geom) {

                  $geometry->appendChild(geophp_to_gml_three_point_one_point_one($geom, $elem));

                }));

              $feature->appendChild($elem('farmos:name', [],
                function($areaName, $elem) use ($area) {

                  $areaName->nodeValue = $area->name;

                }));

              $feature->appendChild($elem('farmos:area_type', [],
                function($areaType, $elem) use ($area) {

                  $areaType->nodeValue = ($area->field_farm_area_type[LANGUAGE_NONE][0]['value'] ?? '');

                }));

              $feature->appendChild($elem('farmos:description', [],
                function($areaDescription, $elem) use ($area) {

                  $areaDescription->nodeValue = $area->description;

                }));

            }));

          }));
      }

    }));
  })->saveXML();
  drupal_exit();
  return;
}

function geophp_to_gml_three_point_one_point_one(Geometry $geometry, $elem) {
  switch ($geometry->geometryType()) {
    case 'Point':

      return $elem('gml:Point', array(
        'srsName' => FARMOS_WFS_DEFAULT_CRS,
      ), function($point, $elem) use ($geometry) {

        $point->appendChild($elem('gml:pos', array(
          'srsDimension' => '2',
        ), function($pos, $elem) use ($geometry) {

            $pos->nodeValue = $geometry->getX() . ' ' . $geometry->getY();

          }));
      });
    case 'LineString':

      return $elem('gml:LineString', array(
        'srsName' => FARMOS_WFS_DEFAULT_CRS,
        ), function($lineString, $elem) use ($geometry) {

        $lineString->appendChild($elem('gml:posList', array(
          'srsDimension' => '2',
        ), function($posList, $elem) use ($geometry) {

          $posList->nodeValue = implode(' ', array_map(function($point) {
              return $point->getX() . ' ' . $point->getY();
            }, $geometry->getPoints()));

        }));
      });

    case 'Polygon':

      return $elem('gml:Polygon', array(
        'srsName' => FARMOS_WFS_DEFAULT_CRS,
        ), function($polygon, $elem) use ($geometry) {

        foreach ($geometry->getComponents() as $ringIdx => $ring) {

          $polygon->appendChild($elem($ringIdx == 0 ? 'gml:exterior' : 'gml:interior', [],
            function($surface, $elem) use ($ring) {

              $surface->appendChild($elem('gml:LinearRing', [],
                function($linearRing, $elem) use ($ring) {

                  $linearRing->appendChild($elem('gml:posList', array(
                    'srsDimension' => '2',
                  ), function($posList, $elem) use ($ring) {

                    $posList->nodeValue = implode(' ', array_map(function($point) {
                      return $point->getX() . ' ' . $point->getY();
                    }, $ring->getPoints()));

                }));

              }));

            }));

        }

      });

    default:
      throw new Exception("Unsupported geometry type: {$geometry->geometryType()}");

  }
}
