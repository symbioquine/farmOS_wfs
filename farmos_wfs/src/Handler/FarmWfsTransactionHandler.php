<?php

namespace Drupal\farmos_wfs\Handler;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\asset\Entity\Asset;
use Drupal\farmos_wfs\FarmWfsFeatureType;
use Drupal\farmos_wfs\FarmWfsFeatureTypeFactoryValidator;
use Drupal\farmos_wfs\QueryResolver\FarmWfsFilterQueryResolver;
use function Drupal\farmos_wfs\QueryResolver\FarmWfsFilterQueryResolver\farmos_wfs_ogc_filter_one_point_one_to_area_ids;

/**
 * Defines FarmWfsGetFeatureHandler class.
 */
class FarmWfsTransactionHandler {

  protected $entityTypeManager;

  protected $entityFieldManager;

  protected $featureTypeFactoryValidator;

  protected $filterQueryResolver;

  public function __construct(EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    FarmWfsFeatureTypeFactoryValidator $feature_type_factory_validator,
    FarmWfsFilterQueryResolver $filter_query_resolver) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->featureTypeFactoryValidator = $feature_type_factory_validator;
    $this->filterQueryResolver = $filter_query_resolver;
  }

  public function handle(array $query_params, \DOMElement $transaction_elem) {
    $action_handlers = array(
      'Insert' => 'handle_insert',
      'Update' => 'handle_update',
      'Delete' => 'handle_delete'
    );

    $set_asset_property_method = $this->create_asset_property_setter();

    $transactionResults = new TransactionResults();

    foreach ($transaction_elem->childNodes as $transaction_action_elem) {

      $action_handler = $action_handlers[$transaction_action_elem->nodeName] ?? null;

      if (! $action_handler) {
        return farmos_wfs_makeExceptionReport(
          function ($eReport, $elem) {
            $eReport->appendChild(
              $elem('Exception', [],
                $elem('ExceptionText', [],
                  "Could not understand request body: Transaction actions must be one of Insert, Update, or Delete")));
          });
      }

      $this->$action_handler($transaction_action_elem, $transactionResults, $set_asset_property_method);
    }

    return farmos_wfs_makeDoc(
      function ($doc, $elem) use ($transactionResults) {
        $doc->appendChild(
          $elem('wfs:TransactionResponse',
            array(
              'xmlns:wfs' => "http://www.opengis.net/wfs",
              'xmlns:ogc' => "http://www.opengis.net/ogc",
              'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
              'xsi:schemaLocation' => "http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.1.0/wfs.xsd"
            ),
            function ($transactionResponse, $elem) use ($transactionResults) {

              $transactionResponse->appendChild(
                $elem('wfs:TransactionSummary', [],
                  function ($transactionSummary, $elem) use ($transactionResults) {

                    $transactionSummary->appendChild(
                      $elem('wfs:totalInserted', [], "{$transactionResults->totalInserted()}"));
                    $transactionSummary->appendChild(
                      $elem('wfs:totalUpdated', [], "{$transactionResults->totalUpdated()}"));
                    $transactionSummary->appendChild(
                      $elem('wfs:totalDeleted', [], "{$transactionResults->totalDeleted()}"));
                  }));

              $transactionResponse->appendChild(
                $elem('wfs:InsertResults', [],
                  function ($insertResults, $elem) use ($transactionResults) {

                    foreach ($transactionResults->insertedFeaturesByHandle as $handle => $featureIds) {

                      $insertResults->appendChild(
                        $elem('wfs:Feature', $handle ? array(
                          'handle' => $handle
                        ) : [],
                          function ($feature, $elem) use ($featureIds) {

                            foreach ($featureIds as $featureId) {

                              $feature->appendChild($elem('ogc:FeatureId', array(
                                'fid' => $featureId
                              )));
                            }
                          }));
                    }
                  }));

              $transactionResponse->appendChild(
                $elem('wfs:TransactionResults', [],
                  function ($insertResults, $elem) use ($transactionResults) {

                    foreach ($transactionResults->insertionFailureMessagesByHandle as $handle => $messages) {

                      foreach ($messages as $message) {

                        $insertResults->appendChild(
                          $elem('wfs:Action', $handle ? array(
                            'locator' => $handle
                          ) : [],
                            function ($feature, $elem) use ($message) {

                              $feature->appendChild($elem('wfs:Message', [], $message));
                            }));
                      }
                    }

                    foreach ($transactionResults->updateFailureMessages as $failed_update_idx => $message) {

                      $insertResults->appendChild(
                        $elem('wfs:Action', array(
                          'locator' => "failed_update-$failed_update_idx"
                        ),
                          function ($feature, $elem) use ($message) {

                            $feature->appendChild($elem('wfs:Message', [], $message));
                          }));
                    }
                  }));
            }));
      });
  }

  private function handle_insert($transaction_action_elem, $transactionResults, $set_asset_property_method) {
    $handle = $transaction_action_elem->attributes['handle'] ?? null;

    foreach ($transaction_action_elem->childNodes as $feature_to_insert) {

      list ($feature_types, $unknown_type_names) = $this->featureTypeFactoryValidator->type_name_to_validated_feature_types(
        $feature_to_insert->localName);

      if (! empty($unknown_type_names)) {
        $unknown_type_name = $unknown_type_names[0];

        return farmos_wfs_makeExceptionReport(
          function ($eReport, $elem) use ($unknown_type_name) {
            $eReport->appendChild(
              $elem('Exception', [],
                $elem('ExceptionText', [],
                  "Could not understand request body: Unknown feature type '$unknown_type_name'")));
          });
      }

      $feature_type = $feature_types[0];

      $asset_storage = $this->entityTypeManager->getStorage('asset');

      $asset = $asset_storage->create([
        'type' => $feature_type->getAssetType(),
      ]);

      foreach ($feature_to_insert->childNodes as $feature_property_elem) {

        try {
          $set_asset_property_method($feature_type, $feature_property_elem->localName, $feature_property_elem, $asset);
        } catch (\Exception $e) {
          $transactionResults->recordInsertionFailure($handle, $e->getMessage());
          continue 2;
        }
      }
    }

    $asset->save();

    $transactionResults->recordInsertionSuccess($handle, "{$feature_type->unqualifiedTypeName()}.{$asset->uuid()}");
  }

  private function handle_update($transaction_action_elem, $transactionResults, $set_asset_property_method) {
    list ($feature_types, $unknown_type_names) = $this->featureTypeFactoryValidator->type_name_to_validated_feature_types(
      $transaction_action_elem->getAttribute('typeName'));

    if (! empty($unknown_type_names)) {
      $unknown_type_name = $unknown_type_names[0];

      return farmos_wfs_makeExceptionReport(
        function ($eReport, $elem) use ($unknown_type_name) {
          $eReport->appendChild(
            $elem('Exception', [],
              $elem('ExceptionText', [], "Could not understand request body: Unknown feature type '$unknown_type_name'")));
        });
    }

    $feature_type = $feature_types[0];

    $children_with_tag = 'farmos_wfs_get_xnode_children_with_tag';

    $filter_elem = $children_with_tag($transaction_action_elem, 'Filter')[0] ?? null;

    $geometry_types = [
      $feature_type->getGeometryType()
    ];

    $asset_ids = $this->filterQueryResolver->resolve_query($feature_type->getAssetType(), $geometry_types, $filter_elem);

    $asset_storage = $this->entityTypeManager->getStorage('asset');

    $assets = $asset_storage->loadMultiple($asset_ids);

    $properties = $children_with_tag($transaction_action_elem, 'Property');

    foreach ($assets as $asset) {
      foreach ($properties as $property_elem) {

        $name_elem = $children_with_tag($property_elem, 'Name')[0] ?? null;
        $value_elem = $children_with_tag($property_elem, 'Value')[0] ?? null;

        $name = preg_replace('/^farmos:/', '', $name_elem->nodeValue);

        try {
          $set_asset_property_method($feature_type, $name, $value_elem, $asset);
        } catch (\Exception $e) {
          $transactionResults->recordUpdateFailure($e->getMessage());
          continue 2;
        }
      }

      $asset->save();

      $transactionResults->recordUpdateSuccess();
    }
  }

  private function handle_delete($transaction_action_elem, $transactionResults, $set_asset_property_method) {
    $type_name = $transaction_action_elem->getAttribute('typeName');

    if (! in_array($type_name, FARMOS_WFS_QUALIFIED_TYPE_NAMES)) {
      return farmos_wfs_makeExceptionReport(
        function ($eReport, $elem) use ($type_name) {
          $eReport->appendChild(
            $elem('Exception', [],
              $elem('ExceptionText', [], "Could not understand request body: Unknown feature type '$type_name'")));
        });
    }

    $filter_elem = farmos_wfs_get_xnode_children_with_tag($transaction_action_elem, 'Filter')[0] ?? null;

    $geo_type = strtolower(preg_replace('/^farmos:(.*)Area$/', '$1', $type_name));

    $area_ids = farmos_wfs_ogc_filter_one_point_one_to_area_ids([
      $geo_type
    ], $filter_elem);

    foreach ($area_ids as $area_id) {
      taxonomy_term_delete($area_id);
      $transactionResults->recordDeleteSuccess();
    }
  }

  private function create_asset_property_setter() {
    $field_definitions_by_asset_type_cache = [];

    return function (FarmWfsFeatureType $feature_type, string $raw_property_name, \DOMElement $property_value_elem,
      Asset $asset) use (&$field_definitions_by_asset_type_cache) {

      if ($raw_property_name == 'geometry') {
        $wkt = gml_three_point_one_point_one_to_geophp($property_value_elem->firstChild)->out('wkt');

        // TODO: Determine when to set intrinsic geometry or create logs
        $asset->set('intrinsic_geometry', $wkt);

        return;
      }

      if (! isset($field_definitions_by_asset_type_cache[$feature_type->getAssetType()])) {
        $field_definitions_by_asset_type_cache[$feature_type->getAssetType()] = $this->entityFieldManager->getFieldDefinitions(
          'asset', $feature_type->getAssetType());
      }

      $field_definitions = $field_definitions_by_asset_type_cache[$feature_type->getAssetType()];

      $field_definition = $field_definitions[$raw_property_name] ?? null;

      if (str_starts_with($raw_property_name, '__') || $field_definition->isReadOnly()) {
        throw new \Exception("Attempted to set read-only asset property: $raw_property_name");
      }

      if (! $field_definition) {
        throw new \Exception("Attempted to set unknown asset property: $raw_property_name");
      }

      $value = $property_value_elem->nodeValue;

      $asset->set($raw_property_name, $value);
    };
  }
}

class TransactionResults {

  public array $insertedFeaturesByHandle = [];

  private int $updateSuccessCount = 0;

  private int $deleteSuccessCount = 0;

  public array $insertionFailureMessagesByHandle = [];

  public array $updateFailureMessages = [];

  function totalInserted() {
    return count($this->insertedFeaturesByHandle);
  }

  function totalUpdated() {
    return $this->updateSuccessCount;
  }

  function totalDeleted() {
    return $this->deleteSuccessCount;
  }

  function recordInsertionSuccess($handle, $fid) {
    $this->insertedFeaturesByHandle[$handle][] = $fid;
  }

  function recordUpdateSuccess() {
    $this->updateSuccessCount ++;
  }

  function recordDeleteSuccess() {
    $this->deleteSuccessCount ++;
  }

  function recordInsertionFailure($handle, $message) {
    $this->insertionFailureMessagesByHandle[$handle][] = $message;
  }

  function recordUpdateFailure($message) {
    $this->updateFailureMessages[] = $message;
  }
}

function gml_three_point_one_point_one_to_geophp($geometry_elem) {
  switch ($geometry_elem->localName) {
    case 'Point':

      $pos = $geometry_elem->firstChild;

      // Check $pos->attributes['srsDimension'] == '2'

      $coord_pair = explode(' ', $pos->nodeValue);

      return new \Point($coord_pair[0], $coord_pair[1]);

    case 'LineString':

      $posList = $geometry_elem->firstChild;

      $coord_pairs = array_chunk(explode(' ', $posList->nodeValue), 2);

      $points = array_map(function ($coord_pair) {
        return new \Point($coord_pair[0], $coord_pair[1]);
      }, $coord_pairs);

      return new \LineString($points);

    case 'Polygon':

      $lines = array();

      foreach ($geometry_elem->childNodes as $component_elem) {

        $fn = array(
          'exterior' => 'array_unshift',
          'interior' => 'array_push'
        )[$component_elem->localName] ?? null;

        if ($fn) {

          // Check $component_elem->firstChild->localName == 'LinearRing'

          $posList = $component_elem->firstChild->firstChild;

          // Check $posList->attributes['srsDimension'] == '2'

          $coord_pairs = array_chunk(explode(' ', $posList->nodeValue), 2);

          $points = array_map(function ($coord_pair) {
            return new \Point($coord_pair[0], $coord_pair[1]);
          }, $coord_pairs);

          $fn($lines, new \LineString($points));
        }
      }

      return new \Polygon($lines);

    default:
      throw new \Exception("Unsupported geometry type: {$geometry_elem->localName}");
  }
}
