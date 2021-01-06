<?php

namespace Drupal\farmos_wfs\QueryResolver;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class FarmWfsSimpleQueryResolver {

  protected $connection;

  protected $entityTypeManager;

  protected $entityFieldManager;

  protected $time;

  public function __construct(Connection $connection, EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager, TimeInterface $time) {
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->time = $time;
  }

  /**
   * Retrieves an array of asset ids by geometry type.
   */
  function resolve_query(string $asset_type, array $geometry_types) {
    $asset_field_definitions = $this->entityFieldManager->getFieldDefinitions('asset', $asset_type);
    $asset_storage = $this->entityTypeManager->getStorage('asset');
    $log_storage = $this->entityTypeManager->getStorage('log');

    if (! ($asset_storage instanceof \Drupal\Core\Entity\Sql\SqlContentEntityStorage)) {
      throw new \Exception(
        "WFS queries are not supported when the asset entity type is not backed by an SQL data store");
    }

    $field_id = 'land_type';

    ksm($asset_field_definitions[$field_id]);

    // $field_storage_definition = $field_definitions[$field_id]->getFieldStorageDefinition();

    $asset_table_mapping = $asset_storage->getTableMapping();
    // $field_table_names = $asset_table_mapping->getAllFieldTableNames($field_id);
    $columns = $asset_table_mapping->getColumnNames($field_id);

    ksm($asset_table_mapping->getFieldTableName($field_id));
    ksm($columns);

    // TODO: Make these queries use table/column names acquired from the field storage definitions
    $latest_movement_log_query = $this->connection->select('log', 'log');

    $latest_movement_log_query->addField('log', 'id', 'log_id');
    $latest_movement_log_query->addField('log_asset', 'asset_target_id', 'asset_target_id');
    $latest_movement_log_query->addExpression(
      "row_number() OVER (PARTITION BY log_asset.asset_target_id ORDER BY log_field_data.timestamp desc, log.id)",
      "row_num");

    $latest_movement_log_query->join('log_field_data', 'log_field_data',
      'log.id = log_field_data.id AND log_field_data.is_movement = 1 AND log_field_data.status = \'done\' AND log_field_data.timestamp <= :current_timestamp',
      [
        'current_timestamp' => $this->time->getRequestTime()
      ]);

    $latest_movement_log_query->join('log__asset', 'log_asset', 'log.id = log_asset.entity_id AND log_asset.deleted = 0');

    $prototype_query_sql = "
      SELECT
        asset.id,
        most_recent_movement_log_ids.log_id AS most_recent_movement_log_id,
        log_geom.geometry_geo_type AS most_recent_movement_log_geo_type
      FROM asset
        LEFT JOIN (SELECT log.id AS log_id, log__asset.asset_target_id,
            row_number() OVER (PARTITION BY log__asset.asset_target_id
              ORDER BY log_field_data.timestamp desc, log.id) AS row_num
          FROM log
            JOIN log_field_data ON log_field_data.id = log.id AND log_field_data.is_movement = 1 AND log_field_data.status = 'done' AND log_field_data.timestamp <= 1610793968
            JOIN log__asset ON log__asset.entity_id = log.id AND log__asset.deleted = 0) most_recent_movement_log_ids ON asset.id = asset_target_id AND most_recent_movement_log_ids.row_num = 1
        LEFT JOIN log__geometry log_geom ON log_id = log_geom.entity_id AND log_geom.deleted = 0
      ";

    $asset_query = $this->connection->select('asset', 'asset');

    $asset_query->addField('asset', 'id', 'asset_id');

    $asset_query->leftJoin($latest_movement_log_query, 'most_recent_movement_log_ids',
      'asset.id = asset_target_id AND most_recent_movement_log_ids.row_num = 1');
    $asset_query->leftJoin('log__geometry', 'log_geom',
      'most_recent_movement_log_ids.log_id = log_geom.entity_id AND log_geom.deleted = 0');

    $asset_query->condition('asset.type', $asset_type);

    $fixed_or_mobile_query_group = $asset_query->orConditionGroup();

    $fixed_or_mobile_query_group->andConditionGroup()
      ->condition('is_fixed', 1)
      ->condition('intrinsic_geometry.geo_type', $geometry_types, 'IN');

    $fixed_or_mobile_query_group->andConditionGroup()
      ->condition('is_fixed', 0)
      ->condition('log_geom.geometry_geo_type', $geometry_types, 'IN');

    $result = $asset_query->execute();

    return $result->fetchCol(0);
  }
}
