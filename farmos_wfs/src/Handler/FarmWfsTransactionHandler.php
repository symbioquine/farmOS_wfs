<?php

namespace Drupal\farmos_wfs\Handler;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\asset\Entity\Asset;
use Drupal\farmos_wfs\FarmWfsFeatureType;
use Drupal\farmos_wfs\FarmWfsFeatureTypeFactoryValidator;
use Drupal\farmos_wfs\Exception\FarmWfsException;
use Drupal\farmos_wfs\QueryResolver\FarmWfsFilterQueryResolver;

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

      if ($transaction_action_elem instanceof \DOMText && $transaction_action_elem->isWhitespaceInElementContent()) {
        continue;
      }

      $action_handler = $action_handlers[$transaction_action_elem->localName] ?? null;

      if (! $action_handler) {
        return farmos_wfs_makeExceptionReport(
          function ($eReport, $elem) use ($transaction_action_elem) {
            $eReport->appendChild(
              $elem('Exception', [],
                $elem('ExceptionText', [],
                  "Could not understand request body action '{$transaction_action_elem->localName}': Transaction actions must be one of Insert, Update, or Delete")));
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

  private function handle_insert(\DOMElement $transaction_action_elem, $transactionResults, $set_asset_property_method) {
    $handle = $transaction_action_elem->attributes['handle'] ?? null;

    foreach ($transaction_action_elem->childNodes as $feature_to_insert) {

      if ($feature_to_insert instanceof \DOMText && $feature_to_insert->isWhitespaceInElementContent()) {
        continue;
      }

      list ($feature_types, $unknown_type_names) = $this->featureTypeFactoryValidator->type_name_to_validated_feature_types(
        $feature_to_insert->localName);

      if (! empty($unknown_type_names)) {
        $unknown_type_name = $unknown_type_names[0];

        throw new FarmWfsException(
          farmos_wfs_makeExceptionReport(
            function ($eReport, $elem) use ($unknown_type_name) {
              $eReport->appendChild(
                $elem('Exception', [],
                  $elem('ExceptionText', [],
                    "Could not understand request body: Unknown feature type '$unknown_type_name'")));
            }), 400);
      }

      $feature_type = $feature_types[0];

      $asset_storage = $this->entityTypeManager->getStorage('asset');

      $asset = $asset_storage->create([
        'type' => $feature_type->getAssetType(),
      ]);

      $children_with_tag = 'farmos_wfs_get_xnode_children_with_tag';

      $property_elements = $children_with_tag($feature_to_insert,
        function ($localName) {
          return $localName != 'geometry';
        });

      $geometry_property_element = $children_with_tag($feature_to_insert, 'geometry')[0] ?? null;

      if ($geometry_property_element) {
        $property_elements[] = $geometry_property_element;
      }

      $asset_logs_to_save = [];

      foreach ($property_elements as $feature_property_elem) {
        try {
          $logs_to_save = $set_asset_property_method($feature_type, $feature_property_elem->localName,
            $feature_property_elem, $asset);
          $asset_logs_to_save += $logs_to_save;
        } catch (\Exception $e) {
          $transactionResults->recordInsertionFailure($handle, $e->getMessage());
          continue 2;
        }
      }

      $constraint_violation_list = $asset->validate();

      if ($constraint_violation_list && $constraint_violation_list->count() > 0) {
        $transactionResults->recordInsertionFailure($handle,
          "Inserted asset would not be valid. Constraint violation at path '{$constraint_violation_list->get(0)->getPropertyPath()}': {$constraint_violation_list->get(0)->getMessage()}");
        continue;
      }

      $asset->save();

      foreach ($asset_logs_to_save as $log_to_save) {
        $log_to_save->set('asset', [
          'target_id' => $asset->id()
        ]);
        $log_to_save->save();
      }

      $transactionResults->recordInsertionSuccess($handle, "{$feature_type->unqualifiedTypeName()}.{$asset->uuid()}");
    }
  }

  private function handle_update(\DOMElement $transaction_action_elem, $transactionResults, $set_asset_property_method) {
    list ($feature_types, $unknown_type_names) = $this->featureTypeFactoryValidator->type_name_to_validated_feature_types(
      $transaction_action_elem->getAttribute('typeName'));

    if (! empty($unknown_type_names)) {
      $unknown_type_name = $unknown_type_names[0];

      throw new FarmWfsException(
        farmos_wfs_makeExceptionReport(
          function ($eReport, $elem) use ($unknown_type_name) {
            $eReport->appendChild(
              $elem('Exception', [],
                $elem('ExceptionText', [],
                  "Could not understand request body: Unknown feature type '$unknown_type_name'")));
          }), 400);
    }

    $feature_type = $feature_types[0];

    $children_with_tag = 'farmos_wfs_get_xnode_children_with_tag';

    $filter_elem = $children_with_tag($transaction_action_elem, 'Filter')[0] ?? null;

    $geometry_types = [
      $feature_type->getGeometryTypeName()
    ];

    $asset_ids = $this->filterQueryResolver->resolve_query($feature_type->getAssetType(), $geometry_types, $filter_elem);

    $asset_storage = $this->entityTypeManager->getStorage('asset');

    $assets = $asset_storage->loadMultiple($asset_ids);

    $properties = properties_as_name_value_elem_pairs_with_geometry_last($transaction_action_elem);

    foreach ($assets as $asset) {

      $asset_logs_to_save = [];

      foreach ($properties as list ($name, $value_elem)) {
        try {
          $logs_to_save = $set_asset_property_method($feature_type, $name, $value_elem, $asset);
          $asset_logs_to_save += $logs_to_save;
        } catch (\Exception $e) {
          $transactionResults->recordUpdateFailure($e->getMessage());
          continue 2;
        }
      }

      $constraint_violation_list = $asset->validate();

      if ($constraint_violation_list && $constraint_violation_list->count() > 0) {
        $transactionResults->recordUpdateFailure(
          "Updated asset would not be valid. Constraint violation at path '{$constraint_violation_list->get(0)->getPropertyPath()}': {$constraint_violation_list->get(0)->getMessage()}");
        continue;
      }

      $asset->save();

      foreach ($asset_logs_to_save as $log_to_save) {
        $log_to_save->set('asset', [
          'target_id' => $asset->id()
        ]);
        $log_to_save->save();
      }

      $transactionResults->recordUpdateSuccess();
    }
  }

  private function handle_delete(\DOMElement $transaction_action_elem, $transactionResults, $set_asset_property_method) {
    list ($feature_types, $unknown_type_names) = $this->featureTypeFactoryValidator->type_name_to_validated_feature_types(
      $transaction_action_elem->getAttribute('typeName'));

    if (! empty($unknown_type_names)) {
      $unknown_type_name = $unknown_type_names[0];

      throw new FarmWfsException(
        farmos_wfs_makeExceptionReport(
          function ($eReport, $elem) use ($unknown_type_name) {
            $eReport->appendChild(
              $elem('Exception', [],
                $elem('ExceptionText', [],
                  "Could not understand request body: Unknown feature type '$unknown_type_name'")));
          }), 400);
    }

    $feature_type = $feature_types[0];

    $filter_elem = farmos_wfs_get_xnode_children_with_tag($transaction_action_elem, 'Filter')[0] ?? null;

    $geometry_types = [
      $feature_type->getGeometryTypeName()
    ];

    $asset_ids = $this->filterQueryResolver->resolve_query($feature_type->getAssetType(), $geometry_types, $filter_elem);

    $asset_storage = $this->entityTypeManager->getStorage('asset');

    $assets = $asset_storage->loadMultiple($asset_ids);

    foreach ($assets as $asset) {
      $asset->delete();
      $transactionResults->recordDeleteSuccess();
    }
  }

  private function create_asset_property_setter() {
    $field_definitions_by_asset_type_cache = [];

    $log_storage = $this->entityTypeManager->getStorage('log');

    return function (FarmWfsFeatureType $feature_type, string $raw_property_name, \DOMElement $property_value_elem,
      Asset $asset) use (&$field_definitions_by_asset_type_cache, &$log_storage) {

      $logs_to_save = [];

      if ($raw_property_name == 'geometry') {
        $gml_geometry_elem = farmos_wfs_get_xnode_children_with_tag($property_value_elem)[0];

        $geophp_geometry = gml_three_point_one_point_one_to_geophp($gml_geometry_elem);

        if ($geophp_geometry->geometryType() != $feature_type->getGeometryTypeName()) {
          throw new \Exception(
            "Attempted to set geometry of type '{$geophp_geometry->geometryType()}' when expected geometry type should be '{$feature_type->getGeometryTypeName()}'");
        }

        $wkt = $geophp_geometry->out('wkt');

        if ($asset->get('is_fixed')->value) {
          $asset->set('intrinsic_geometry', $wkt);
        } else {
          $movement_log = $log_storage->create(
            [
              'type' => 'activity',
              'status' => 'done',
              'asset' => [
                'target_id' => $asset->uuid()
              ],
              'is_movement' => TRUE,
              'geometry' => $wkt,
            ]);

          $logs_to_save[] = $movement_log;
        }

        return $logs_to_save;
      }

      if (! isset($field_definitions_by_asset_type_cache[$feature_type->getAssetType()])) {
        $field_definitions_by_asset_type_cache[$feature_type->getAssetType()] = $this->entityFieldManager->getFieldDefinitions(
          'asset', $feature_type->getAssetType());
      }

      $field_definitions = $field_definitions_by_asset_type_cache[$feature_type->getAssetType()];

      $field_definition = $field_definitions[$raw_property_name] ?? null;

      if (str_starts_with($raw_property_name, '__') || ($field_definition && $field_definition->isReadOnly())) {
        throw new \Exception("Attempted to set read-only asset property: $raw_property_name");
      }

      if (! $field_definition) {
        throw new \Exception("Attempted to set unknown asset property: $raw_property_name");
      }

      $value = $property_value_elem->nodeValue;

      if ($field_definition->getType() == 'timestamp') {
        $datetime = new \DateTime($value);

        $value = $datetime->getTimestamp();
      }

      $asset->set($raw_property_name, $value);

      $field_data = $asset->get($raw_property_name);

      $constraint_violation_list = $field_data->validate();

      if ($constraint_violation_list && $constraint_violation_list->count() > 0) {
        throw new \Exception(
          "Attempted to set an illegal value of '$value' for asset property '$raw_property_name': {$constraint_violation_list->get(0)->getMessage()}");
      }

      // For some reason state fields only validate on non-new entities...
      // https://git.drupalcode.org/project/state_machine/-/blob/2f33a2a78db28e82fb62222cdcce211942aec231/src/Plugin/Validation/Constraint/StateConstraintValidator.php#L19
      if ($field_definition->getType() == 'state' && ! $field_data->first()->isValid()) {
        throw new \Exception("Attempted to set an illegal value of '$value' for asset property '$raw_property_name'");
      }

      return $logs_to_save;
    };
  }
}

function properties_as_name_value_elem_pairs_with_geometry_last($transaction_action_elem) {
  $children_with_tag = 'farmos_wfs_get_xnode_children_with_tag';

  $properties = $children_with_tag($transaction_action_elem, 'Property');

  $property_pairs = [];
  $geometry_pair = null;

  foreach ($properties as $property_elem) {

    $name_elem = $children_with_tag($property_elem, 'Name')[0] ?? null;
    $value_elem = $children_with_tag($property_elem, 'Value')[0] ?? null;

    $name = preg_replace('/^farmos:/', '', $name_elem->nodeValue);

    if ($name == 'geometry') {
      $geometry_pair = [
        'geometry',
        $value_elem
      ];
    } else {
      $property_pairs[] = [
        $name,
        $value_elem
      ];
    }
  }

  if ($geometry_pair) {
    $property_pairs[] = $geometry_pair;
  }

  return $property_pairs;
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
  $children_with_tag = 'farmos_wfs_get_xnode_children_with_tag';

  switch ($geometry_elem->localName) {
    case 'Point':

      $pos = $children_with_tag($geometry_elem, 'pos')[0];

      // Check $pos->attributes['srsDimension'] == '2'

      $coord_pair = explode(' ', $pos->nodeValue);

      return new \Point($coord_pair[0], $coord_pair[1]);

    case 'LineString':

      $posList = $children_with_tag($geometry_elem, 'posList')[0];

      $coord_pairs = array_chunk(explode(' ', $posList->nodeValue), 2);

      $points = array_map(function ($coord_pair) {
        return new \Point($coord_pair[0], $coord_pair[1]);
      }, $coord_pairs);

      return new \LineString($points);

    case 'Polygon':

      $lines = array();

      $component_elems = $children_with_tag($geometry_elem);

      foreach ($component_elems as $component_elem) {

        $fn = array(
          'exterior' => 'array_unshift',
          'interior' => 'array_push'
        )[$component_elem->localName] ?? null;

        if ($fn) {
          $linearRing = $children_with_tag($component_elem, 'LinearRing')[0];

          $posList = $children_with_tag($linearRing, 'posList')[0];

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
