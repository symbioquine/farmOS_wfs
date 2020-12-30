<?php

namespace Drupal\farmos_wfs\Handler;

/**
 * Defines FarmWfsGetFeatureHandler class.
 */
class FarmWfsTransactionHandler {

  public function handle(array $query_params, $transaction_elem) {

    $action_handlers = array(
      'Insert' => 'handle_wfs_transaction_insert_action',
      'Update' => 'handle_wfs_transaction_update_action',
      'Delete' => 'handle_wfs_transaction_delete_action',
    );
  
    $allowed_area_types = array_keys(farm_area_types());
  
    geophp_load();
  
    $transactionResults = new TransactionResults();
  
    foreach ($transaction_elem->childNodes as $transaction_action_elem) {
  
      $action_handler = $action_handlers[$transaction_action_elem->nodeName] ?? null;
  
      if (!$action_handler) {
        return farmos_wfs_makeExceptionReport(function($eReport, $elem) {
          $eReport->appendChild($elem('Exception', [],
            $elem('ExceptionText', [], "Could not understand request body: Transaction actions must be one of Insert, Update, or Delete")));
        });
      }
  
      $action_handler($transaction_action_elem, $transactionResults, $allowed_area_types);
    }
  
    return farmos_wfs_makeDoc(function($doc, $elem) use ($transactionResults) {
      $doc->appendChild($elem('wfs:TransactionResponse', array(
        'xmlns:wfs' => "http://www.opengis.net/wfs",
        'xmlns:ogc' => "http://www.opengis.net/ogc",
        'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
        'xsi:schemaLocation' => "http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.1.0/wfs.xsd",
      ), function($transactionResponse, $elem) use ($transactionResults) {
  
        $transactionResponse->appendChild($elem('wfs:TransactionSummary', [],
          function($transactionSummary, $elem) use ($transactionResults) {
  
            $transactionSummary->appendChild($elem('wfs:totalInserted', [], "{$transactionResults->totalInserted()}"));
            $transactionSummary->appendChild($elem('wfs:totalUpdated', [], "{$transactionResults->totalUpdated()}"));
            $transactionSummary->appendChild($elem('wfs:totalDeleted', [], "{$transactionResults->totalDeleted()}"));
  
        }));
  
        $transactionResponse->appendChild($elem('wfs:InsertResults', [],
          function($insertResults, $elem) use ($transactionResults) {
  
            foreach ($transactionResults->insertedFeaturesByHandle as $handle => $featureIds) {
  
              $insertResults->appendChild($elem('wfs:Feature',
                $handle ? array('handle' => $handle) : [],
                function($feature, $elem) use ($featureIds) {
  
                  foreach ($featureIds as $featureId) {
  
                    $feature->appendChild($elem('ogc:FeatureId', array('fid' => $featureId)));
  
                  }
  
              }));
  
            }
  
        }));
  
        $transactionResponse->appendChild($elem('wfs:TransactionResults', [],
          function($insertResults, $elem) use ($transactionResults) {
  
            foreach ($transactionResults->insertionFailureMessagesByHandle as $handle => $messages) {
  
              foreach ($messages as $message) {
  
                $insertResults->appendChild($elem('wfs:Action',
                  $handle ? array('locator' => $handle) : [],
                  function($feature, $elem) use ($message) {
  
                      $feature->appendChild($elem('wfs:Message', [], $message));
  
                }));
  
              }
  
            }
  
            foreach ($transactionResults->updateFailureMessages as $failed_update_idx => $message) {
  
              $insertResults->appendChild($elem('wfs:Action', array('locator' => "failed_update-$failed_update_idx"),
                function($feature, $elem) use ($message) {
  
                  $feature->appendChild($elem('wfs:Message', [], $message));
  
              }));
  
            }
  
        }));
  
      }));
    });
  
  }
}

function handle_wfs_transaction_insert_action($transaction_action_elem, $transactionResults, $allowed_area_types) {
  $handle = $transaction_action_elem->attributes['handle'] ?? null;

  foreach ($transaction_action_elem->childNodes as $feature_to_insert) {

    $feature_type = $feature_to_insert->localName;

    if (!in_array($feature_type, FARMOS_WFS_UNQUALIFIED_TYPE_NAMES)) {
      return farmos_wfs_makeExceptionReport(function($eReport, $elem) use ($feature_type) {
        $eReport->appendChild($elem('Exception', [],
          $elem('ExceptionText', [], "Could not understand request body: Unknown feature type '$feature_type'")));
      });
    }

    $vocab = taxonomy_vocabulary_machine_name_load('farm_areas');
    if (empty($vocab)) {
      // TODO: Handle 'farm_areas' vocab not existing
    }

    $area = new stdClass();
    $area->vid = $vocab->vid;

    $property_handlers = farmos_wfs_get_property_handlers($allowed_area_types);

    foreach ($feature_to_insert->childNodes as $feature_property_elem) {

      $property_handler = $property_handlers[$feature_property_elem->localName] ?? null;

      if ($property_handler) {

        try {
          $property_handler($feature_property_elem, $area);
        } catch (Exception $e) {
          $transactionResults->recordInsertionFailure($handle, $e->getMessage());
          continue 2;
        }

      }

    }

    taxonomy_term_save($area);

    $transactionResults->recordInsertionSuccess($handle, "$feature_type.{$area->tid}");

  }
}

function farmos_wfs_get_property_handlers($allowed_area_types) {
  return array(
    'name' => function($e, $area) {
      $area->name = check_plain($e->nodeValue);
    },
    'area_type' => function($e, $area) use ($allowed_area_types) {
      $area_type = $e->nodeValue;

      if (!in_array($area_type, $allowed_area_types)) {
        $allowed_area_types_str = implode(", ", $allowed_area_types);
        throw new Exception("Illegal area type $area_type. Value must be one of $allowed_area_types_str");
      }

      $area->field_farm_area_type[LANGUAGE_NONE][0]['value'] = $area_type;
    },
    'description' => function($e, $area) {
      $area->description = $e->nodeValue;
    },
    'geometry' => function($e, $area) {
      // TODO: Validate that geometry type matches $feature_type
      $area->field_farm_geofield[LANGUAGE_NONE][0]['geom'] = gml_three_point_one_point_one_to_geophp($e->firstChild);
    },
  );
}

function handle_wfs_transaction_update_action($transaction_action_elem, $transactionResults, $allowed_area_types) {
  $type_name = $transaction_action_elem->getAttribute('typeName');

  if (!in_array($type_name, FARMOS_WFS_QUALIFIED_TYPE_NAMES)) {
    return farmos_wfs_makeExceptionReport(function($eReport, $elem) use ($type_name) {
      $eReport->appendChild($elem('Exception', [],
        $elem('ExceptionText', [], "Could not understand request body: Unknown feature type '$type_name'")));
    });
  }

  $children_with_tag = 'farmos_wfs_get_xnode_children_with_tag';

  $filter = $children_with_tag($transaction_action_elem, 'Filter')[0] ?? null;

  $geo_type = strtolower(preg_replace('/^farmos:(.*)Area$/', '$1', $type_name));

  $area_ids = farmos_wfs_ogc_filter_one_point_one_to_area_ids([$geo_type], $filter);

  $areas = entity_load('taxonomy_term', $area_ids);

  $property_handlers = farmos_wfs_get_property_handlers($allowed_area_types);

  $properties = $children_with_tag($transaction_action_elem, 'Property');

  foreach ($areas as $area) {
    foreach ($properties as $property_elem) {

      $name_elem = $children_with_tag($property_elem, 'Name')[0] ?? null;
      $value_elem = $children_with_tag($property_elem, 'Value')[0] ?? null;

      $name = preg_replace('/^farmos:/', '', $name_elem->nodeValue);

      $property_handler = $property_handlers[$name] ?? null;

      if ($property_handler) {

        try {
          $property_handler($value_elem, $area);
        } catch (Exception $e) {
          $transactionResults->recordUpdateFailure($e->getMessage());
          continue 2;
        }

      }

    }

    taxonomy_term_save($area);

    $transactionResults->recordUpdateSuccess();

  }

}

function handle_wfs_transaction_delete_action($transaction_action_elem, $transactionResults, $allowed_area_types) {
  $type_name = $transaction_action_elem->getAttribute('typeName');

  if (!in_array($type_name, FARMOS_WFS_QUALIFIED_TYPE_NAMES)) {
    return farmos_wfs_makeExceptionReport(function($eReport, $elem) use ($type_name) {
      $eReport->appendChild($elem('Exception', [],
        $elem('ExceptionText', [], "Could not understand request body: Unknown feature type '$type_name'")));
    });
  }

  $filter_elem = farmos_wfs_get_xnode_children_with_tag($transaction_action_elem, 'Filter')[0] ?? null;

  $geo_type = strtolower(preg_replace('/^farmos:(.*)Area$/', '$1', $type_name));

  $area_ids = farmos_wfs_ogc_filter_one_point_one_to_area_ids([$geo_type], $filter_elem);

  foreach ($area_ids as $area_id) {
    taxonomy_term_delete($area_id);
    $transactionResults->recordDeleteSuccess();
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

      return new Point($coord_pair[0], $coord_pair[1]);

    case 'LineString':

      $posList = $geometry_elem->firstChild;

      $coord_pairs = array_chunk(explode(' ', $posList->nodeValue), 2);

      $points = array_map(function ($coord_pair) {
        return new Point($coord_pair[0], $coord_pair[1]);
      }, $coord_pairs);

      return new LineString($points);

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
            return new Point($coord_pair[0], $coord_pair[1]);
          }, $coord_pairs);

          $fn($lines, new LineString($points));
        }
      }

      return new Polygon($lines);

    default:
      throw new Exception("Unsupported geometry type: {$geometry_elem->localName}");
  }
}
