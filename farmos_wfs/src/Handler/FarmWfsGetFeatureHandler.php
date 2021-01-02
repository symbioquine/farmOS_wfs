<?php

namespace Drupal\farmos_wfs\Handler;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\farm_location\AssetLocationInterface;
use Drupal\farmos_wfs\FarmWfsFeatureTypeFactoryValidator;
use Drupal\farmos_wfs\QueryResolver\FarmWfsBboxQueryResolver;
use Drupal\farmos_wfs\QueryResolver\FarmWfsFilterQueryResolver;
use Drupal\farmos_wfs\QueryResolver\FarmWfsSimpleQueryResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Geometry;

/**
 * Defines FarmWfsGetFeatureHandler class.
 */
class FarmWfsGetFeatureHandler {

  protected $requestStack;

  protected $entityTypeManager;

  protected $entityTypeBundleInfo;

  protected $entityFieldManager;

  protected $featureTypeFactoryValidator;

  protected $simpleQueryResolver;

  protected $filterQueryResolver;

  protected $bboxQueryResolver;

  protected $assetLocation;

  public function __construct(RequestStack $request_stack, EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $entity_bundle_info, EntityFieldManagerInterface $entity_field_manager,
    FarmWfsFeatureTypeFactoryValidator $feature_type_factory_validator,
    FarmWfsSimpleQueryResolver $simple_query_resolver, FarmWfsFilterQueryResolver $filter_query_resolver,
    FarmWfsBboxQueryResolver $bbox_query_resolver, AssetLocationInterface $asset_location) {
    $this->requestStack = $request_stack;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_bundle_info;
    $this->entityFieldManager = $entity_field_manager;
    $this->featureTypeFactoryValidator = $feature_type_factory_validator;

    $this->simpleQueryResolver = $simple_query_resolver;
    $this->filterQueryResolver = $filter_query_resolver;
    $this->bboxQueryResolver = $bbox_query_resolver;

    $this->assetLocation = $asset_location;
  }

  public function handle(array $query_params) {
    $current_request = $this->requestStack->getCurrentRequest();

    $host = $current_request->getSchemeAndHttpHost();

    $feature_types = [];
    $unknown_type_names = [];
    list ($feature_types, $unknown_type_names) = $this->featureTypeFactoryValidator->type_names_to_validated_feature_types(
      $query_params['TYPENAME'] ?? '');

    if (! empty($unknown_type_names)) {
      return farmos_wfs_makeExceptionReport(
        function ($eReport, $elem) {
          $eReport->appendChild(
            $elem('Exception', array(
              "exceptionCode" => "InvalidParameterValue",
              "locator" => "typename"
            )));
        });
    }

    if (empty($feature_types)) {
      return farmos_wfs_makeExceptionReport(
        function ($eReport, $elem) {
          $eReport->appendChild(
            $elem('Exception', array(
              "exceptionCode" => "MissingParameterValue",
              "locator" => "typename"
            )));
        });
    }

    if (count($feature_types) > 1) {
      return farmos_wfs_makeExceptionReport(
        function ($eReport, $elem) {
          $eReport->appendChild(
            $elem('Exception', array(
              "exceptionCode" => "InvalidParameterValue",
              "locator" => "typename"
            ),
              $elem('ExceptionText', [],
                "GetFeature currently only supports retrieving features from a single feature type")));
        });
    }

    $feature_type = $feature_types[0];

    $bbox = array_filter(explode(',', $query_params['BBOX'] ?? ''));

    if (! empty($bbox) && (count($bbox) < 4 || count($bbox) > 5)) {
      return farmos_wfs_makeExceptionReport(
        function ($eReport, $elem) {
          $eReport->appendChild(
            $elem('Exception', array(
              "exceptionCode" => "InvalidParameterValue",
              "locator" => "bbox"
            )));
        });
    }

    if (count($bbox) > 4 && $bbox[4] != 'EPSG:4326') {
      return farmos_wfs_makeExceptionReport(
        function ($eReport, $elem) {
          $eReport->appendChild(
            $elem('Exception', array(
              "exceptionCode" => "InvalidParameterValue",
              "locator" => "bbox"
            )));
        });
    }

    $filter = $query_params['FILTER'] ?? null;

    $filter_elem = null;
    if ($filter) {
      // TODO: error handling
      $filter_doc = farmos_wfs_loadXml($filter);

      // $doc->validate();

      if (! $filter_doc->documentElement || $filter_doc->documentElement->localName != "Filter") {

        return farmos_wfs_makeExceptionReport(
          function ($eReport, $elem) {
            $eReport->appendChild(
              $elem('Exception', [],
                $elem('ExceptionText', [], "Could not understand filter parameter: root element must be a Filter")));
          });
      }

      $filter_elem = $filter_doc->documentElement;
    }

    if (! empty($bbox) && $filter_elem) {
      return farmos_wfs_makeExceptionReport(
        function ($eReport, $elem) {
          $eReport->appendChild(
            $elem('Exception', array(
              "exceptionCode" => "InvalidParameterValue",
              "locator" => "filter"
            ),
              $elem('ExceptionText', [], "Illegal request; please supply only one of the 'filter' or 'bbox' parameters")));
        });
    }

    $asset_type = $feature_type->getAssetType();
    $geometry_types = [
      $feature_type->getGeometryType()
    ];

    $asset_ids = [];
    if (! empty($bbox)) {
      $asset_ids = $this->bboxQueryResolver->resolve_query($asset_type, $geometry_types, $bbox);
    } elseif ($filter_elem) {
      $asset_ids = $this->filterQueryResolver->resolve_query($asset_type, $geometry_types, $filter_elem);
    } else {
      $asset_ids = $this->simpleQueryResolver->resolve_query($asset_type, $geometry_types);
    }

    $asset_storage = $this->entityTypeManager->getStorage('asset');

    $assets = $asset_storage->loadMultiple($asset_ids);

    return farmos_wfs_makeDoc(
      function ($doc, $elem) use ($host, $query_params, $feature_type, $assets) {
        $doc->appendChild(
          $elem('wfs:FeatureCollection',
            array(
              "xmlns:farmos" => "https://farmos.org/wfs",
              'xmlns:gml' => "http://www.opengis.net/gml",
              'xmlns:wfs' => "http://www.opengis.net/wfs",
              'xmlns:ogc' => "http://www.opengis.net/ogc",
              'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
              'xsi:schemaLocation' => "https://farmos.org/wfs " .
              "$host/wfs?SERVICE=WFS&VERSION=1.1.0&REQUEST=DescribeFeatureType&TYPENAME={$query_params['TYPENAME']}&OUTPUTFORMAT=text/xml;%20subtype=gml/3.1.1 " .
              "http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.1.0/wfs.xsd"
            ),
            function ($featureCollection, $elem) use ($feature_type, $assets) {

              $limits = array();
              $accumulate_limit = function ($source, $edge, $accumulator) use (&$limits) {
                if (! isset($source[$edge])) {
                  return;
                }
                if (! isset($limits[$edge])) {
                  $limits[$edge] = (float) $source[$edge];
                } else {
                  $limits[$edge] = $accumulator((float) $source[$edge], $limits[$edge]);
                }
              };

              foreach ($assets as $asset) {

                $wkt = $this->assetLocation->getGeometry($asset);

                // Get WKT from the field. If empty, bail.
                if (empty($wkt)) {
                  continue;
                }

                $geom = \geoPHP::load($wkt, 'wkt');

                // If the geometry is empty, bail.
                if ($geom->isEmpty()) {
                  continue;
                }

                $bbox = $geom->getBBox();

                $accumulate_limit($bbox, 'minx', 'min');
                $accumulate_limit($bbox, 'miny', 'min');
                $accumulate_limit($bbox, 'maxy', 'max');
                $accumulate_limit($bbox, 'maxx', 'max');

                $featureCollection->appendChild(
                  $elem('gml:featureMember', [],
                    function ($featureMember, $elem) use ($feature_type, $asset, $geom, $bbox) {

                      $featureMember->appendChild(
                        $elem($feature_type->qualifiedTypeName(),
                          array(
                            'gml:id' => "{$feature_type->unqualifiedTypeName()}.{$asset->uuid()}"
                          ),
                          function ($feature, $elem) use ($asset, $geom, $bbox) {

                            $feature->appendChild(gml_bounded_by($bbox, $elem));

                            $feature->appendChild(
                              $elem('farmos:geometry', [],
                                function ($geometry, $elem) use ($asset, $geom) {

                                  $geometry->appendChild(geophp_to_gml_three_point_one_point_one($geom, $elem));
                                }));

                            $field_definitions = $asset->getFieldDefinitions();

                            foreach ($field_definitions as $field_id => $field_definition) {

                              $field_type = $field_definition->getType();

                              $supported_field_types = [
                                'string',
                                'text_long',
                                'timestamp',
                                'boolean',
                                'uuid',
                                'list_string',
                                'string_long',
                                'integer',
                              ];

                              if (in_array($field_type, $supported_field_types)) {

                                if ($field_definition->isReadOnly()) {
                                  $property_name = 'farmos:__' . $field_id;
                                } else {
                                  $property_name = 'farmos:' . $field_id;
                                }

                                $field_data = $asset->get($field_id);

                                if ($field_data->isEmpty()) {
                                  continue;
                                }

                                $first_field_datum = $field_data->first();

                                if (! $first_field_datum) {
                                  continue;
                                }

                                $property_value = $first_field_datum->getValue()['value'];

                                $feature->appendChild($elem($property_name, [], $property_value));
                              }
                            }
                          }));
                    }));
              }

              if (! empty($limits)) {
                $featureCollection->appendChild(gml_bounded_by($limits, $elem));
              }
            }));
      });
  }
}

function gml_bounded_by($limits, $elem) {
  return $elem('gml:boundedBy', [],
    function ($boundedBy, $elem) use ($limits) {

      $boundedBy->appendChild(
        $elem('gml:Envelope', array(
          'srsName' => FARMOS_WFS_DEFAULT_CRS
        ),
          function ($envelope, $elem) use ($limits) {
            $envelope->appendChild($elem('gml:lowerCorner', [], "{$limits['minx']} {$limits['miny']}"));
            $envelope->appendChild($elem('gml:upperCorner', [], "{$limits['maxx']} {$limits['maxy']}"));
          }));
    });
}

function geophp_to_gml_three_point_one_point_one(Geometry $geometry, $elem) {
  // TODO: Consider refactoring to reduce duplication in these cases
  switch ($geometry->geometryType()) {
    case 'Point':

      return $elem('gml:Point', array(
        'srsName' => FARMOS_WFS_DEFAULT_CRS
      ),
        function ($point, $elem) use ($geometry) {

          $point->appendChild(
            $elem('gml:pos', array(
              'srsDimension' => '2'
            ), function ($pos, $elem) use ($geometry) {

              $pos->nodeValue = $geometry->getX() . ' ' . $geometry->getY();
            }));
        });
    case 'LineString':

      return $elem('gml:LineString', array(
        'srsName' => FARMOS_WFS_DEFAULT_CRS
      ),
        function ($lineString, $elem) use ($geometry) {

          $lineString->appendChild(
            $elem('gml:posList', array(
              'srsDimension' => '2'
            ),
              function ($posList, $elem) use ($geometry) {

                $posList->nodeValue = implode(' ',
                  array_map(function ($point) {
                    return $point->getX() . ' ' . $point->getY();
                  }, $geometry->getPoints()));
              }));
        });

    case 'Polygon':

      return $elem('gml:Polygon', array(
        'srsName' => FARMOS_WFS_DEFAULT_CRS
      ),
        function ($polygon, $elem) use ($geometry) {

          foreach ($geometry->getComponents() as $ringIdx => $ring) {

            $polygon->appendChild(
              $elem($ringIdx == 0 ? 'gml:exterior' : 'gml:interior', [],
                function ($surface, $elem) use ($ring) {

                  $surface->appendChild(
                    $elem('gml:LinearRing', [],
                      function ($linearRing, $elem) use ($ring) {

                        $linearRing->appendChild(
                          $elem('gml:posList', array(
                            'srsDimension' => '2'
                          ),
                            function ($posList, $elem) use ($ring) {

                              $posList->nodeValue = implode(' ',
                                array_map(function ($point) {
                                  return $point->getX() . ' ' . $point->getY();
                                }, $ring->getPoints()));
                            }));
                      }));
                }));
          }
        });

    default:
      throw new \Exception("Unsupported geometry type: {$geometry->geometryType()}");
  }
}
