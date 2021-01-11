<?php

namespace Drupal\farmos_wfs\Exception;

class FarmWfsException extends \Exception {

  protected $responseBody;

  protected $httpStatusCode;

  public function __construct(\DOMDocument $response_body, int $http_status_code) {
    $this->responseBody = $response_body;
    $this->httpStatusCode = $http_status_code;
  }

  public function getResponseBody(): \DOMDocument {
    return $this->responseBody;
  }

  public function getHttpStatusCode(): int {
    return $this->httpStatusCode;
  }
}