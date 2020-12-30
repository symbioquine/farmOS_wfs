<?php

namespace Drupal\farmos_wfs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use DOMDocument;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\farmos_wfs\Handler\FarmWfsGetCapabilitiesHandler;
use Drupal\farmos_wfs\Handler\FarmWfsDescribeFeatureTypeHandler;
use Drupal\farmos_wfs\Handler\FarmWfsGetFeatureHandler;
use Drupal\farmos_wfs\Handler\FarmWfsTransactionHandler;

/**
 * Defines FarmWfsController class.
 */
class FarmWfsController extends ControllerBase {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The GetCapabilities handler
   *
   * @var \Drupal\farmos_wfs\Handler\FarmWfsGetCapabilitiesHandler
   */
  protected $getCapabilitiesHandler;
  
  /**
   * The DescribeFeatureType handler
   *
   * @var \Drupal\farmos_wfs\Handler\FarmWfsDescribeFeatureTypeHandler
   */
  protected $describeFeatureTypeHandler;
  
  /**
   * The GetFeature handler
   *
   * @var \Drupal\farmos_wfs\Handler\FarmWfsGetFeatureHandler
   */
  protected $getFeatureHandler;
  
  /**
   * The Transaction handler
   *
   * @var \Drupal\farmos_wfs\Handler\FarmWfsTransactionHandler
   */
  protected $transactionHandler;

  /**
   * Constructs a new FarmWfsController object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *          The request stack.
   * @param \Drupal\Core\State\State $state
   *          The object State.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   */
  public function __construct(
    RequestStack $request_stack,
    ConfigFactoryInterface $config_factory,
    FarmWfsGetCapabilitiesHandler $getCapabilitiesHandler,
    FarmWfsDescribeFeatureTypeHandler $describeFeatureTypeHandler,
    FarmWfsGetFeatureHandler $getFeatureHandler,
    FarmWfsTransactionHandler $transactionHandler) {

    $this->requestStack = $request_stack;
    $this->configFactory = $config_factory;

    $this->getCapabilitiesHandler = $getCapabilitiesHandler;
    $this->describeFeatureTypeHandler = $describeFeatureTypeHandler;
    $this->getFeatureHandler = $getFeatureHandler;
    $this->transactionHandler = $transactionHandler;
  }

  /**
   * Top-level handler for WFS requests.
   *
   * @return \Symfony\Component\HttpFoundation\Response The XML encoded WFS Response.
   */
  public function content() {
    $response = $this->handle_request();

    if ($response instanceof DOMDocument) {
      $xml_response = $response->saveXML();

      $response = new Response();
      $response->headers->set('Content-Type', 'application/xml');
      $response->setContent($xml_response);
    }

    return $response;
  }

  private function handle_request() {
    $current_request = $this->requestStack->getCurrentRequest();

    $query_params = array_change_key_case($current_request->query->all(), CASE_UPPER);

    $service = $query_params['SERVICE'] ?? null;

    if ($service !== "WFS") {
      return farmos_wfs_makeExceptionReport(function ($eReport, $elem) {
        $eReport->appendChild($elem('Exception', array(
          "exceptionCode" => "InvalidParameterValue",
          "locator" => "service"
        )));
      });
    }

    // TODO: Validate version parameter

    $request_handlers = array(
      'GetCapabilities' => $this->getCapabilitiesHandler,
      'DescribeFeatureType' => $this->describeFeatureTypeHandler,
      'GetFeature' => $this->getFeatureHandler,
    );

    $requested_operation = $query_params['REQUEST'] ?? null;

    $requested_operation_handler = $request_handlers[$requested_operation] ?? null;

    $request_method = $current_request->getMethod();

    if ($requested_operation_handler) {
      if ($request_method != "GET") {

        return farmos_wfs_makeExceptionReport(function ($eReport, $elem) use ($requested_operation, $request_method) {
          $eReport->appendChild($elem('Exception', array(
            "exceptionCode" => "InvalidParameterValue",
            "locator" => "request"
          ), $elem('ExceptionText', [], "The $requested_operation operation is not supported via $request_method")));
        });
      }

      return $requested_operation_handler->handle($query_params);
    }

    if ($requested_operation == "Transaction" || $request_method == "POST") {
      if (! user_access('administer taxonomy')) {

        return farmos_wfs_makeExceptionReport(function ($eReport, $elem) {
          $eReport->appendChild($elem('Exception', array(
            "exceptionCode" => "AccessDenied"
          ), $elem('ExceptionText', [], "Access denied")));
        });
      }

      $request_body = file_get_contents('php://input');

      // TODO: Handle empty request

      // TODO: error handling
      $doc = farmos_wfs_loadXml($request_body);

      // $doc->validate();

      if (! $doc->firstChild || $doc->firstChild->nodeName != "Transaction") {

        return farmos_wfs_makeExceptionReport(function ($eReport, $elem) {
          $eReport->appendChild($elem('Exception', [], $elem('ExceptionText', [], "Could not understand request body: root element must be a Transaction")));
        });
      }

      return $this->transactionHandler($query_params, $doc->firstChild);
    }

    return farmos_wfs_makeExceptionReport(function ($eReport, $elem) {
      $eReport->appendChild($elem('Exception', array(
        "exceptionCode" => "InvalidParameterValue",
        "locator" => "request"
      )));
    });
  }
}
