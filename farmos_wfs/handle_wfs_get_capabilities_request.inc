<?php

/**
 * @file
 * handle_wfs_get_capabilities_request.inc
 */

function handle_wfs_get_capabilities_request($query_params) {
  global $base_url;

  $host = $base_url;

  $site_name = filter_xss_admin(variable_get('site_name', 'farmOS'));

  print farmos_wfs_makeDoc(function($doc, $elem) use ($site_name, $host) {
    $doc->appendChild($elem('wfs:WFS_Capabilities', array(
      "xmlns:farmos" => "https://farmos.org/wfs",
      'xmlns:gml' => "http://www.opengis.net/gml",
      'xmlns:wfs' => "http://www.opengis.net/wfs",
      'xmlns:ogc' => "http://www.opengis.net/ogc",
      'xmlns:ows' => "http://www.opengis.net/ows",
      'xmlns:xlink' => "http://www.w3.org/1999/xlink",
      'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
      'xsi:schemaLocation' => "http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.1.0/wfs.xsd",
      'version' => "1.1.0",
    ), function($wfsCapabilities, $elem) use ($site_name, $host) {

      $wfsCapabilities->appendChild($elem('ows:ServiceIdentification', [],
        function($serviceIdentification, $elem) {

          $serviceIdentification->appendChild($elem('ows:Title', [], "farmOS OGC WFS API"));
          $serviceIdentification->appendChild($elem('ows:Abstract', [], "Web Feature Service for farmOS"));

          $serviceIdentification->appendChild($elem('ows:Keywords', [],
            function($keywords, $elem) {
              $keywords->appendChild($elem('ows:Keyword', [], "farmOS"));
              $keywords->appendChild($elem('ows:Type', [], "String"));
          }));

          $serviceIdentification->appendChild($elem('ows:ServiceType', [], "WFS"));
          $serviceIdentification->appendChild($elem('ows:ServiceTypeVersion', [], "1.1.0"));

      }));

      $wfsCapabilities->appendChild($elem('ows:ServiceProvider', [],
        function($serviceProvider, $elem) use ($site_name, $host) {

          $serviceProvider->appendChild($elem('ows:ProviderName', [], $site_name));
          $serviceProvider->appendChild($elem('ows:ProviderSite', array(
            'xlink:href' => $host
          )));

      }));

      $wfsCapabilities->appendChild($elem('ows:OperationsMetadata', [],
        function($operationsMetadata, $elem) use ($site_name, $host) {

          foreach(["GetCapabilities", "DescribeFeatureType", "GetFeature"] as $operationName) {

            $operationsMetadata->appendChild($elem('ows:Operation', array(
              'name' => $operationName,
              ), function($operation, $elem) use ($operationName, $host) {

              $operation->appendChild($elem('ows:DCP', [],
                function($dcp, $elem) use ($host) {

                  $dcp->appendChild($elem('ows:HTTP', array(),
                    function($http, $elem) use ($host) {

                      $http->appendChild($elem('ows:Get', array(
                        "xlink:href"  => "$host/wfs",
                      )));

                  }));

              }));

              if ($operationName == 'GetCapabilities') {

                $operation->appendChild($elem('ows:Parameter', array(
                  'name' => "AcceptVersions",
                ), function($param, $elem) {
                    $param->appendChild($elem('ows:Value', [], "1.1.0"));
                }));

                $operation->appendChild($elem('ows:Parameter', array(
                  'name' => "AcceptFormats",
                ), function($param, $elem) {
                  $param->appendChild($elem('ows:Value', [], "text/xml"));
                }));

              } else {

                $operation->appendChild($elem('ows:Parameter', array(
                  'name' => "outputFormat",
                ), function($param, $elem) {
                  $param->appendChild($elem('ows:Value', [], "text/xml; subtype=gml/3.1.1"));
                }));

              }

            }));

        }

      }));

      $wfsCapabilities->appendChild($elem('wfs:FeatureTypeList', [],
        function($featureTypeList, $elem) {

          $featureTypeList->appendChild($elem('wfs:Operations', [],
            function($operations, $elem) {
              $operations->appendChild($elem('wfs:Operation', [], "Query"));
          }));

          $known_type_names = array('farmos:PointArea', 'farmos:PolygonArea', 'farmos:LineStringArea');

          foreach($known_type_names as $type_name) {

            $featureTypeList->appendChild($elem('wfs:FeatureType', [],
              function($featureType, $elem) use ($type_name) {
                $featureType->appendChild($elem('wfs:Name', [], $type_name));

                $geometry_type = preg_replace('/^farmos:(.*)Area$/', '$1', $type_name);

                $geometry_type_name_parts = preg_split('/(?=[A-Z])/', $geometry_type);

                $geometry_type_name_title_case = implode(' ', $geometry_type_name_parts);

                $featureType->appendChild($elem('wfs:Title', [], "farmOS $geometry_type_name_title_case Feature"));

                $geometry_type_name_sentence_case = ucfirst(strtolower($geometry_type_name_title_case));

                $featureType->appendChild($elem('wfs:Abstract', [], "$geometry_type_name_sentence_case features in farmOS."));
                $featureType->appendChild($elem('wfs:DefaultSRS', [], FARMOS_WFS_DEFAULT_CRS));
                $featureType->appendChild($elem('wfs:OutputFormats', [],
                  function($outputFormats, $elem) {
                    $outputFormats->appendChild($elem('wfs:Format', [], "text/xml; subtype=gml/3.1.1"));
                }));
            }));

          }

      }));

      $wfsCapabilities->appendChild($elem('ogc:Filter_Capabilities', [],
        function($filterCapabilities, $elem) {

          $filterCapabilities->appendChild($elem('ogc:Spatial_Capabilities', [],
            function($spatialCapabilities, $elem) {

              $spatialCapabilities->appendChild($elem('ogc:SpatialOperators', [],
                function($spatialOperators, $elem) {

                  $spatialOperators->appendChild($elem('ogc:SpatialOperator', array(
                    'name' => "BBOX"
                  )));

                }));

          }));

      }));

    }));
  })->saveXML();
  drupal_exit();
  return;
}
