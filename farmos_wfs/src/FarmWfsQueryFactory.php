<?php

namespace Drupal\farmos_wfs;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

class FarmWfsQueryFactory {

  protected $connection;

  protected $entityTypeManager;

  protected $time;

  public function __construct(Connection $connection, EntityTypeManagerInterface $entity_type_manager,
    TimeInterface $time) {
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
  }

  /**
   * Creates a query to fetch asset ids by asset type and geometry types.
   */
  function create_query(string $asset_type, array $geometry_types) {
    $asset_storage = $this->entityTypeManager->getStorage('asset');
    $log_storage = $this->entityTypeManager->getStorage('log');

    if (! ($asset_storage instanceof \Drupal\Core\Entity\Sql\SqlContentEntityStorage) ||
      ! ($log_storage instanceof \Drupal\Core\Entity\Sql\SqlContentEntityStorage)) {
      throw new \Exception(
        "WFS queries are not supported when the asset/log entity types are not backed by an SQL data store");
    }

    $asset_table_mapping = $asset_storage->getTableMapping();
    $log_table_mapping = $log_storage->getTableMapping();

    $latest_movement_log_query = $this->create_latest_movement_log_query($log_storage, $log_table_mapping);

    $asset_query = $this->connection->select($asset_storage->getBaseTable(), 'asset');

    $asset_query->addField('asset', 'id', 'asset_id');

    $asset_data_table = $asset_storage->getDataTable();

    $asset_query->join($asset_data_table, 'asset_field_data', 'asset.id = asset_field_data.id');

    $asset_intrinsic_geometry_table = $asset_table_mapping->getFieldTableName('intrinsic_geometry');

    $asset_query->leftJoin($asset_intrinsic_geometry_table, 'intrinsic_geometry',
      'asset.id = intrinsic_geometry.entity_id AND intrinsic_geometry.deleted = 0');

    $asset_query->leftJoin($latest_movement_log_query, 'most_recent_movement_log_ids',
      'asset.id = asset_target_id AND most_recent_movement_log_ids.row_num = 1');

    $log_geometry_table = $log_table_mapping->getFieldTableName('geometry');

    $asset_query->leftJoin($log_geometry_table, 'log_geometry',
      'most_recent_movement_log_ids.log_id = log_geometry.entity_id AND log_geometry.deleted = 0');

    $asset_query->condition('asset.type', $asset_type);

    $fixed_or_mobile_query_group = $asset_query->orConditionGroup();

    $fixed_or_mobile_query_group->condition(
      $fixed_or_mobile_query_group->andConditionGroup()
        ->condition('asset_field_data.is_fixed', 1)
        ->condition('intrinsic_geometry.intrinsic_geometry_geo_type', $geometry_types, 'IN'));

    $fixed_or_mobile_query_group->condition(
      $fixed_or_mobile_query_group->andConditionGroup()
        ->condition('asset_field_data.is_fixed', 0)
        ->condition('log_geometry.geometry_geo_type', $geometry_types, 'IN'));

    $asset_query->condition($fixed_or_mobile_query_group);

    return $asset_query;
  }

  private function create_latest_movement_log_query(SqlContentEntityStorage $log_storage, $log_table_mapping) {
    $latest_movement_log_query = $this->connection->select($log_storage->getBaseTable(), 'log');

    $latest_movement_log_query->addField('log', 'id', 'log_id');
    $latest_movement_log_query->addField('log_asset', 'asset_target_id', 'asset_target_id');
    $latest_movement_log_query->addExpression(
      "ROW_NUMBER() OVER (PARTITION BY log_asset.asset_target_id ORDER BY log_field_data.timestamp DESC, log.id DESC)",
      "row_num");

    $log_data_table = $log_storage->getDataTable();

    $latest_movement_log_query->join($log_data_table, 'log_field_data',
      'log.id = log_field_data.id AND log_field_data.is_movement = 1 AND log_field_data.status = \'done\' AND log_field_data.timestamp <= :current_timestamp',
      [
        'current_timestamp' => $this->time->getRequestTime()
      ]);

    $log_asset_table = $log_table_mapping->getFieldTableName('asset');

    $latest_movement_log_query->join($log_asset_table, 'log_asset',
      'log.id = log_asset.entity_id AND log_asset.deleted = 0');

    $latest_movement_log_query->groupBy('log.id')
      ->groupBy('log_asset.asset_target_id')
      ->groupBy('log_field_data.timestamp');

    return $latest_movement_log_query;
  }
}
