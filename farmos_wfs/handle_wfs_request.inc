<?php

/**
 * @file
 * handle_wfs_request.inc
 */

define("FARMOS_WFS_IMPLEMENTATION_VERSION", '1.1.0');
define("FARMOS_WFS_DEFAULT_CRS", 'EPSG:4326');

/**
 * Handle WFS requests and return XML responses.
 *
 * @return string
 */
function handle_wfs_request() {
  $query_params = array_change_key_case(drupal_get_query_parameters(), CASE_UPPER);

  drupal_add_http_header('Content-Type', 'application/xml; utf-8');

  $service = $query_params['SERVICE'] ?? null;

  if ($service !== "WFS") {
    print farmos_wfs_makeExceptionReport(function($eReport, $elem) {
      $eReport->appendChild($elem('Exception', array(
        "exceptionCode"  => "InvalidParameterValue",
        "locator" => "service",
      )));
    });
    drupal_exit();
    return;
  }

  $request_handlers = array(
    'GetCapabilities' => 'handle_wfs_get_capabilities_request',
    'DescribeFeatureType' => 'handle_wfs_describe_feature_type_request',
    'GetFeature' => 'handle_wfs_get_feature_request',
  );

  $requested_operation = $query_params['REQUEST'] ?? null;

  $requested_operation_handler = $request_handlers[$requested_operation] ?? null;

  if (!$requested_operation_handler) {
    print farmos_wfs_makeExceptionReport(function($eReport, $elem) {
      $eReport->appendChild($elem('Exception', array(
        "exceptionCode"  => "InvalidParameterValue",
        "locator" => "request",
      )));
    });
    drupal_exit();
    return;
  }

  // TODO: Validate version parameter

  module_load_include('inc', 'farmos_wfs', $requested_operation_handler);

  $requested_operation_handler($query_params);
}

function farmos_wfs_makeDoc($declarator) {
  $xml = new DomDocument('1.0', 'UTF-8');

  $elem = null;
  $elem = function($name, $attrs, $elemDeclarator = null) use ($xml, &$elem) {
    $e = $xml->createElement($name);

    foreach ($attrs as $attrKey => $attrVal) {
      $e->setAttribute($attrKey, $attrVal);
    }

    if ($elemDeclarator) {
      $elemDeclarator($e, $elem);
    }

    return $e;
  };

  $declarator($xml, $elem);

  $xml->formatOutput = TRUE;

  return $xml;
}

function farmos_wfs_makeExceptionReport($declarator) {
  return farmos_wfs_makeDoc(function($doc, $elem) use ($declarator) {
    $doc->appendChild($elem('ExceptionReport', array(
      'xmlns' => "http://www.opengis.net/ows/1.1",
      'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
      'xsi:schemaLocation' => "http://www.opengis.net/ows/1.1 owsExceptionReport.xsd",
      'version' => FARMOS_WFS_IMPLEMENTATION_VERSION,
      'xml:lang' => "en",
    ), $declarator));
  })->saveXML();
}
