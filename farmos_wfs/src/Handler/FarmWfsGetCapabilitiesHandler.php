<?php

namespace Drupal\farmos_wfs\Handler;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\farmos_wfs\FarmWfsFeatureType;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines FarmWfsController class.
 */
class FarmWfsGetCapabilitiesHandler {

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
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new FarmWfsController object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *          The request stack.
   * @param \Drupal\Core\State\State $state
   *          The object State.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *          The config factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *          The current user.
   */
  public function __construct(RequestStack $request_stack, ConfigFactoryInterface $config_factory,
    EntityTypeBundleInfo $entityTypeBundleInfo, AccountProxyInterface $currentUser) {
    $this->requestStack = $request_stack;
    $this->configFactory = $config_factory;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->currentUser = $currentUser;
  }

  public function handle(array $query_params) {
    $current_request = $this->requestStack->getCurrentRequest();

    $host = $current_request->getSchemeAndHttpHost();

    return farmos_wfs_makeDoc(
      function ($doc, $elem) use ($host) {
        $doc->appendChild(
          $elem('wfs:WFS_Capabilities',
            array(
              "xmlns:farmos" => "https://farmos.org/wfs",
              'xmlns:gml' => "http://www.opengis.net/gml",
              'xmlns:wfs' => "http://www.opengis.net/wfs",
              'xmlns:ogc' => "http://www.opengis.net/ogc",
              'xmlns:ows' => "http://www.opengis.net/ows",
              'xmlns:xlink' => "http://www.w3.org/1999/xlink",
              'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
              'xsi:schemaLocation' => "http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.1.0/wfs.xsd",
              'version' => "1.1.0"
            ),
            function ($wfsCapabilities, $elem) use ($host) {

              $wfsCapabilities->appendChild(
                $elem('ows:ServiceIdentification', [],
                  function ($serviceIdentification, $elem) {

                    $serviceIdentification->appendChild($elem('ows:Title', [], "farmOS OGC WFS API"));
                    $serviceIdentification->appendChild($elem('ows:Abstract', [], "Web Feature Service for farmOS"));

                    $serviceIdentification->appendChild(
                      $elem('ows:Keywords', [],
                        function ($keywords, $elem) {
                          $keywords->appendChild($elem('ows:Keyword', [], "farmOS"));
                          $keywords->appendChild($elem('ows:Type', [], "String"));
                        }));

                    $serviceIdentification->appendChild($elem('ows:ServiceType', [], "WFS"));
                    $serviceIdentification->appendChild($elem('ows:ServiceTypeVersion', [], "1.1.0"));
                  }));

              $wfsCapabilities->appendChild(
                $elem('ows:ServiceProvider', [],
                  function ($serviceProvider, $elem) use ($host) {

                    $serviceProvider->appendChild(
                      $elem('ows:ProviderName', [],
                        Xss::filterAdmin($this->configFactory->get('system.site')
                          ->get('name'))));
                    $serviceProvider->appendChild($elem('ows:ProviderSite', array(
                      'xlink:href' => $host
                    )));
                  }));

              $wfsCapabilities->appendChild(
                $elem('ows:OperationsMetadata', [],
                  function ($operationsMetadata, $elem) use ($host) {

                    foreach ([
                      "GetCapabilities",
                      "DescribeFeatureType",
                      "GetFeature",
                      "Transaction"
                    ] as $operationName) {

                      $operationsMetadata->appendChild(
                        $elem('ows:Operation', array(
                          'name' => $operationName
                        ),
                          function ($operation, $elem) use ($operationName, $host) {

                            $operation->appendChild(
                              $elem('ows:DCP', [],
                                function ($dcp, $elem) use ($operationName, $host) {

                                  $dcp->appendChild(
                                    $elem('ows:HTTP', array(),
                                      function ($http, $elem) use ($operationName, $host) {

                                        if ($operationName == 'Transaction') {

                                          $http->appendChild($elem('ows:Post', array(
                                            "xlink:href" => "$host/wfs"
                                          )));
                                        } else {

                                          $http->appendChild($elem('ows:Get', array(
                                            "xlink:href" => "$host/wfs"
                                          )));
                                        }
                                      }));
                                }));

                            if ($operationName == 'GetCapabilities') {

                              $operation->appendChild(
                                $elem('ows:Parameter', array(
                                  'name' => "AcceptVersions"
                                ), function ($param, $elem) {
                                  $param->appendChild($elem('ows:Value', [], "1.1.0"));
                                }));

                              $operation->appendChild(
                                $elem('ows:Parameter', array(
                                  'name' => "AcceptFormats"
                                ), function ($param, $elem) {
                                  $param->appendChild($elem('ows:Value', [], "text/xml"));
                                }));
                            }

                            if ($operationName == 'DescribeFeatureType' || $operationName == 'GetFeature') {

                              $operation->appendChild(
                                $elem('ows:Parameter', array(
                                  'name' => "outputFormat"
                                ),
                                  function ($param, $elem) {
                                    $param->appendChild($elem('ows:Value', [], "text/xml; subtype=gml/3.1.1"));
                                  }));
                            }

                            if ($operationName == 'Transaction') {

                              $operation->appendChild(
                                $elem('ows:Parameter', array(
                                  'name' => "inputFormat"
                                ),
                                  function ($param, $elem) {
                                    $param->appendChild($elem('ows:Value', [], "text/xml; subtype=gml/3.1.1"));
                                  }));
                            }
                          }));
                    }
                  }));

              $wfsCapabilities->appendChild(
                $elem('wfs:FeatureTypeList', [],
                  function ($featureTypeList, $elem) {

                    $featureTypeList->appendChild(
                      $elem('wfs:Operations', [],
                        function ($operations, $elem) {
                          $operations->appendChild($elem('wfs:Operation', [], "Query"));

                          if ($this->currentUser->hasPermission('Administer assets')) {
                            $operations->appendChild($elem('wfs:Operation', [], "Insert"));
                            $operations->appendChild($elem('wfs:Operation', [], "Update"));
                            $operations->appendChild($elem('wfs:Operation', [], "Delete"));
                          }
                        }));

                    $asset_bundles = $this->entityTypeBundleInfo->getBundleInfo('asset');

                    foreach ($asset_bundles as $asset_type => $asset_bundle_info) {
                      foreach (FARMOS_WFS_RECOGNIZED_GEOMETRY_TYPES as $geometry_type) {

                        $feature_type = new FarmWfsFeatureType($asset_type, strtolower($geometry_type));

                        $featureTypeList->appendChild(
                          $elem('wfs:FeatureType', [],
                            function ($featureType, $elem) use ($feature_type, $asset_bundle_info) {
                              $featureType->appendChild($elem('wfs:Name', [], $feature_type->qualifiedTypeName()));

                              $geometry_type_name_parts = preg_split('/(?=[A-Z])/', $feature_type->getGeometryTypeName());

                              $geometry_type_name_title_case = implode(' ', $geometry_type_name_parts);

                              $featureType->appendChild(
                                $elem('wfs:Title', [],
                                  "farmOS {$asset_bundle_info['label']} Asset $geometry_type_name_title_case Features"));

                              $asset_bundle_label_lower_case = strtolower($asset_bundle_info['label']);

                              $geometry_type_name_lower_case = strtolower($geometry_type_name_title_case);

                              $featureType->appendChild(
                                $elem('wfs:Abstract', [],
                                  "Feature representing $asset_bundle_label_lower_case assets with $geometry_type_name_lower_case geometry in farmOS."));
                              $featureType->appendChild($elem('wfs:DefaultSRS', [], FARMOS_WFS_DEFAULT_CRS));
                              $featureType->appendChild(
                                $elem('wfs:OutputFormats', [],
                                  function ($outputFormats, $elem) {
                                    $outputFormats->appendChild($elem('wfs:Format', [], "text/xml; subtype=gml/3.1.1"));
                                  }));

                              // TODO: Re-implement

                              // $geo_type = strtolower($geometry_type);
                              //
                              // $limits = db_query("SELECT
                              // min(field_farm_geofield_left) AS 'left',
                              // min(field_farm_geofield_bottom) AS 'bottom',
                              // max(field_farm_geofield_right) AS 'right',
                              // max(field_farm_geofield_top) AS 'top'
                              // FROM
                              // {field_data_field_farm_geofield} g
                              // WHERE g.bundle = 'farm_areas' AND g.field_farm_geofield_geo_type = :geo_type
                              // GROUP BY g.field_farm_geofield_geo_type",
                              // array(':geo_type' => $geo_type))->fetchAll();
                              //
                              // if (!empty($limits)) {
                              //
                              // $featureType->appendChild($elem('ows:WGS84BoundingBox', array(
                              // 'dimensions' => "2",
                              // ), function($bbox, $elem) use ($limits) {
                              // $bbox->appendChild($elem('ows:LowerCorner', [], "{$limits[0]->left} {$limits[0]->bottom}"));
                              // $bbox->appendChild($elem('ows:UpperCorner', [], "{$limits[0]->right} {$limits[0]->top}"));
                              // }));
                              //
                              // }
                            }));
                      }
                    }
                  }));

              $wfsCapabilities->appendChild(
                $elem('ogc:Filter_Capabilities', [],
                  function ($filterCapabilities, $elem) {

                    $filterCapabilities->appendChild(
                      $elem('ogc:Spatial_Capabilities', [],
                        function ($spatialCapabilities, $elem) {

                          $spatialCapabilities->appendChild(
                            $elem('ogc:SpatialOperators', [],
                              function ($spatialOperators, $elem) {

                                $spatialOperators->appendChild($elem('ogc:SpatialOperator', array(
                                  'name' => "BBOX"
                                )));
                              }));
                        }));
                  }));
            }));
      });
  }
}
