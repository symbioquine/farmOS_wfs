<?php

/**
 * @file
 * handle_wfs_describe_feature_type_request.inc
 */

function handle_wfs_describe_feature_type_request($query_params) {
  $requested_type_names = array_filter(explode(',', $query_params['TYPENAME'] ?? ''));

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
    $requested_type_names = $known_type_names;
  }

  print farmos_wfs_makeDoc(function($doc, $elem) use ($requested_type_names) {
    $doc->appendChild($elem('xsd:schema', array(
      'targetNamespace' => "https://farmos.org/wfs",
      "xmlns:farmos" => "https://farmos.org/wfs",
      'xmlns:gml' => "http://www.opengis.net/gml",
      'xmlns:xsd' => "http://www.w3.org/2001/XMLSchema",
      'xmlns' => "http://www.w3.org/2001/XMLSchema",
      'elementFormDefault' => "qualified",
      'version' => "0.1",
    ), function($schema, $elem) use ($requested_type_names) {

      $schema->appendChild($elem('xsd:import', array(
        "namespace"  => "http://www.opengis.net/gml",
        "schemaLocation" => "http://schemas.opengis.net/gml/3.1.1/base/gml.xsd",
      )));

      foreach($requested_type_names as $type_name) {

        $geometry_type = preg_replace('/^farmos:(.*)Area$/', '$1', $type_name);

        $schema->appendChild($elem('xsd:element', array(
          "name"  => "{$geometry_type}Area",
          "type" => "farmos:{$geometry_type}AreaType",
          "substitutionGroup" => "gml:_Feature",
        )));

        $schema->appendChild($elem('xsd:complexType', array(
          "name"  => "{$geometry_type}AreaType",
        ), function($complexType, $elem) use ($geometry_type) {

          $complexType->appendChild($elem('xsd:complexContent', array(),
            function($complexContent, $elem) use ($geometry_type) {

              $complexContent->appendChild($elem('xsd:extension', array(
                "base"  => "gml:AbstractFeatureType",
              ), function($extension, $elem) use ($geometry_type) {

                $extension->appendChild($elem('xsd:sequence', array(),
                  function($sequence, $elem) use ($geometry_type) {

                    $sequence->appendChild($elem('xsd:element', array(
                      "name"  => "geometry",
                      "type" => "gml:{$geometry_type}PropertyType",
                    )));

                    $sequence->appendChild($elem('xsd:element', array(
                      "name"  => "name",
                      "type" => "string",
                    )));

                    $sequence->appendChild($elem('xsd:element', array(
                      "name"  => "area_type",
                      "type" => "string",
                    )));

                    $sequence->appendChild($elem('xsd:element', array(
                      "name"  => "description",
                      "type" => "string",
                    )));

                  }));

              }));

            }));

        }));

      }

    }));
  })->saveXML();
  drupal_exit();
  return;
}
