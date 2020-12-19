<?php

/**
 * @file
 * handle_wfs_request.inc
 */

/**
 * Handle WFS requests and return XML responses.
 *
 * @return string
 */
function handle_wfs_request() {
  $query_params = array_change_key_case(drupal_get_query_parameters(), CASE_UPPER);

  $service = $query_params['SERVICE'] ?? null;

  if ($service !== "WFS") {
    return farmos_wfs_makeExceptionReport(function($eReport, $elem) {
      $eReport->appendChild($elem('Exception', array(
        "exceptionCode"  => "InvalidParameterValue",
        "locator" => "service",
      )));
    });
  }

  // TODO: Validate version parameter

  $request_handlers = array(
    'GetCapabilities' => 'handle_wfs_get_capabilities_request',
    'DescribeFeatureType' => 'handle_wfs_describe_feature_type_request',
    'GetFeature' => 'handle_wfs_get_feature_request',
  );

  $requested_operation = $query_params['REQUEST'] ?? null;

  $requested_operation_handler = $request_handlers[$requested_operation] ?? null;

  $request_method = $_SERVER['REQUEST_METHOD'] ?? null;

  if ($requested_operation_handler) {
    if ($request_method != "GET") {

      return farmos_wfs_makeExceptionReport(function($eReport, $elem) {
        $eReport->appendChild($elem('Exception', array(
          "exceptionCode"  => "InvalidParameterValue",
          "locator" => "request",
        ), $elem('ExceptionText', [], "The $requested_operation operation is not supported via $request_method")));
      });

    }

    module_load_include('inc', 'farmos_wfs', $requested_operation_handler);

    return $requested_operation_handler($query_params);
  }

  if ($requested_operation == "Transaction" || $request_method == "POST") {
    if (!user_access('administer taxonomy')) {
      return MENU_ACCESS_DENIED;
    }

    $request_body = file_get_contents('php://input');

    // TODO: Handle empty request

    // TODO: error handling
    $doc = farmos_wfs_loadXml($request_body);

    // $doc->validate();

    if (!$doc->firstChild || $doc->firstChild->nodeName != "Transaction") {

      return farmos_wfs_makeExceptionReport(function($eReport, $elem) {
        $eReport->appendChild($elem('Exception', [],
          $elem('ExceptionText', [], "Could not understand request body: root element must be a Transaction")));
      });

    }

    module_load_include('inc', 'farmos_wfs', 'handle_wfs_transaction_request');

    return handle_wfs_transaction_request($doc->firstChild);
  }

  return farmos_wfs_makeExceptionReport(function($eReport, $elem) {
    $eReport->appendChild($elem('Exception', array(
      "exceptionCode"  => "InvalidParameterValue",
      "locator" => "request",
    )));
  });


}

// Based on https://www.php.net/manual/en/domdocument.loadxml.php#69295
function farmos_wfs_handleXmlLoadingErrors($errno, $errstr, $errfile, $errline) {
  if ($errno==E_WARNING && (substr_count($errstr,"DOMDocument::loadXML()")>0)) {
    throw new DOMException($errstr);
  }

  return false;
}

// Based on https://www.php.net/manual/en/domdocument.loadxml.php#69295
function farmos_wfs_loadXml($xml_string) {
  set_error_handler('farmos_wfs_handleXmlLoadingErrors');
  $dom = new DOMDocument();
  $dom->loadXml($xml_string);
  restore_error_handler();
  return $dom;
}
