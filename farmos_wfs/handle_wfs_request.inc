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

  $request_handlers = array(
    'GetCapabilities' => 'handle_wfs_get_capabilities_request',
    'DescribeFeatureType' => 'handle_wfs_describe_feature_type_request',
    'GetFeature' => 'handle_wfs_get_feature_request',
  );

  $requested_operation = $query_params['REQUEST'] ?? null;

  $requested_operation_handler = $request_handlers[$requested_operation] ?? null;

  if (!$requested_operation_handler) {
    return farmos_wfs_makeExceptionReport(function($eReport, $elem) {
      $eReport->appendChild($elem('Exception', array(
        "exceptionCode"  => "InvalidParameterValue",
        "locator" => "request",
      )));
    });
  }

  // TODO: Validate version parameter

  module_load_include('inc', 'farmos_wfs', $requested_operation_handler);

  return $requested_operation_handler($query_params);
}
