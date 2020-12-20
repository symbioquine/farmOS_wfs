<?php

/**
 * @file
 * handle_wfs_get_feature_request.inc
 */

function handle_wfs_get_feature_request($query_params) {
  global $base_url;

  $host = $base_url;

  $requested_type_names = array_filter(explode(',', $query_params['TYPENAME'] ?? ''));

  $known_type_names = array('farmos:PointArea', 'farmos:PolygonArea', 'farmos:LineStringArea');

  $unknown_type_names = array_diff_key($requested_type_names, $known_type_names);

  if (!empty($unknown_type_names)) {
    return farmos_wfs_makeExceptionReport(function($eReport, $elem) {
      $eReport->appendChild($elem('Exception', array(
        "exceptionCode"  => "InvalidParameterValue",
        "locator" => "typename",
      )));
    });
  }

  if (empty($requested_type_names)) {
    return farmos_wfs_makeExceptionReport(function($eReport, $elem) {
      $eReport->appendChild($elem('Exception', array(
        "exceptionCode"  => "MissingParameterValue",
        "locator" => "typename",
      )));
    });
  }

  $requested_type_names_by_geofield_geo_type = array();

  foreach($requested_type_names as $type_name) {
    $geo_type = strtolower(preg_replace('/^farmos:(.*)Area$/', '$1', $type_name));

    $requested_type_names_by_geofield_geo_type[$geo_type] = $type_name;
  }

  $requested_geometry_types = array_keys($requested_type_names_by_geofield_geo_type);

  $bbox = array_filter(explode(',', $query_params['BBOX'] ?? ''));

  if (!empty($bbox) && (count($bbox) < 4 || count($bbox) > 5)) {
    return farmos_wfs_makeExceptionReport(function($eReport, $elem) {
      $eReport->appendChild($elem('Exception', array(
        "exceptionCode"  => "InvalidParameterValue",
        "locator" => "bbox",
      )));
    });
  }

  if (count($bbox) > 4 && $bbox[4] != 'EPSG:4326') {
    return farmos_wfs_makeExceptionReport(function($eReport, $elem) {
      $eReport->appendChild($elem('Exception', array(
        "exceptionCode"  => "InvalidParameterValue",
        "locator" => "bbox",
      )));
    });
  }

  $filter = $query_params['FILTER'] ?? null;

  $filter_elem = null;
  if ($filter) {
    // TODO: error handling
    $filter_doc = farmos_wfs_loadXml($filter);

    // $doc->validate();

    if (!$filter_doc->firstChild || $filter_doc->firstChild->nodeName != "Filter") {

      return farmos_wfs_makeExceptionReport(function($eReport, $elem) {
        $eReport->appendChild($elem('Exception', [],
          $elem('ExceptionText', [], "Could not understand filter parameter: root element must be a Filter")));
      });

    }

    $filter_elem = $filter_doc->firstChild;
  }

  if (!empty($bbox) && $filter_elem) {
    return farmos_wfs_makeExceptionReport(function($eReport, $elem) {
      $eReport->appendChild($elem('Exception', array(
        "exceptionCode"  => "InvalidParameterValue",
        "locator" => "filter",
      ), $elem('ExceptionText', [], "Illegal request; please supply only one of the 'filter' or 'bbox' parameters")));
    });
  }

  $area_ids = farmos_wfs_ogc_filter_one_point_one_to_area_ids($requested_geometry_types, $filter_elem, $bbox, FARMOS_WFS_EMPTY_FILTER_BEHAVIOR_MATCH_ALL);

  $areas = entity_load('taxonomy_term', $area_ids);

  return farmos_wfs_makeDoc(function($doc, $elem) use ($host, $query_params, $requested_type_names, $areas, $requested_type_names_by_geofield_geo_type) {
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

      $limits = array();
      $accumulate_limit = function($source, $edge, $accumulator) use (&$limits) {
        if (!isset($source[$edge])) {
          return;
        }
        if (!isset($limits[$edge])) {
          $limits[$edge] = (float) $source[$edge];
        } else {
          $limits[$edge] = $accumulator((float) $source[$edge], $limits[$edge]);
        }
      };

      foreach($areas as $area) {

        $geofield = $area->field_farm_geofield[LANGUAGE_NONE][0];

        $geo_type = $geofield['geo_type'];
        $type_name = $requested_type_names_by_geofield_geo_type[$geo_type];

        $geometry_type = preg_replace('/^farmos:(.*)Area$/', '$1', $type_name);

        // Get WKT from the field. If empty, bail.
        if (empty($geofield['geom'])) {
          continue;
        }

        $wkt = $geofield['geom'];

        geophp_load();
        $geom = geoPHP::load($wkt, 'wkt');

        // If the geometry is empty, bail.
        if ($geom->isEmpty()) {
          continue;
        }

        $accumulate_limit($geofield, 'left', 'min');
        $accumulate_limit($geofield, 'bottom', 'min');
        $accumulate_limit($geofield, 'top', 'max');
        $accumulate_limit($geofield, 'right', 'max');

        $featureCollection->appendChild($elem('gml:featureMember', [],
          function($featureMember, $elem) use ($area, $geometry_type, $geofield, $geom) {

            $featureMember->appendChild($elem("farmos:{$geometry_type}Area", array(
              'gml:id'  => "{$geometry_type}Area.{$area->tid}",
            ), function($feature, $elem) use ($area, $geofield, $geom) {

              $feature->appendChild(gml_bounded_by($geofield, $elem));

              $feature->appendChild($elem('farmos:geometry', [],
                function($geometry, $elem) use ($area, $geom) {

                  $geometry->appendChild(geophp_to_gml_three_point_one_point_one($geom, $elem));

                }));

              $feature->appendChild($elem('farmos:area_id', [], "{$area->tid}"));
              $feature->appendChild($elem('farmos:name', [], $area->name));
              $feature->appendChild($elem('farmos:area_type', [], ($area->field_farm_area_type[LANGUAGE_NONE][0]['value'] ?? '')));
              $feature->appendChild($elem('farmos:description', [], $area->description));

            }));

          }));
      }

      if (!empty($limits)) {
        $featureCollection->appendChild(gml_bounded_by($limits, $elem));
      }

    }));
  });
}

function gml_bounded_by($limits, $elem) {
  return $elem('gml:boundedBy', [],
    function($boundedBy, $elem) use ($limits) {

      $boundedBy->appendChild($elem('gml:Envelope', array(
        'srsName' => FARMOS_WFS_DEFAULT_CRS,
      ), function($envelope, $elem) use ($limits) {
        $envelope->appendChild($elem('gml:lowerCorner', [], "{$limits['left']} {$limits['bottom']}"));
        $envelope->appendChild($elem('gml:upperCorner', [], "{$limits['right']} {$limits['top']}"));
      }));

  });
}

function geophp_to_gml_three_point_one_point_one(Geometry $geometry, $elem) {
  // TODO: Consider refactoring to reduce duplication in these cases
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
