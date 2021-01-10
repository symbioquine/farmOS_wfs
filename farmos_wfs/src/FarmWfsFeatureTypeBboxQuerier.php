<?php

namespace Drupal\farmos_wfs;

use Drupal\Core\Database\Connection;

class FarmWfsFeatureTypeBboxQuerier {

  protected $connection;

  protected $queryFactory;

  public function __construct(Connection $connection, FarmWfsQueryFactory $query_factory) {
    $this->connection = $connection;
    $this->queryFactory = $query_factory;
  }

  /**
   * Gets the bounding boxes by geometry type for a given asset type.
   */
  function get_bounding_boxes(string $asset_type) {
    $asset_query = $this->queryFactory->create_query($asset_type, FARMOS_WFS_RECOGNIZED_GEOMETRY_TYPES);

    $asset_query->addField('asset_field_data', 'is_fixed');

    $asset_query->addField('intrinsic_geometry', 'intrinsic_geometry_geo_type');
    $asset_query->addField('intrinsic_geometry', 'intrinsic_geometry_top');
    $asset_query->addField('intrinsic_geometry', 'intrinsic_geometry_right');
    $asset_query->addField('intrinsic_geometry', 'intrinsic_geometry_bottom');
    $asset_query->addField('intrinsic_geometry', 'intrinsic_geometry_left');

    $asset_query->addField('log_geometry', 'geometry_geo_type', 'log_geometry_geo_type');
    $asset_query->addField('log_geometry', 'geometry_top', 'log_geometry_top');
    $asset_query->addField('log_geometry', 'geometry_right', 'log_geometry_right');
    $asset_query->addField('log_geometry', 'geometry_bottom', 'log_geometry_bottom');
    $asset_query->addField('log_geometry', 'geometry_left', 'log_geometry_left');

    $limits_query = $this->connection->select($asset_query, 'asset_q');

    $limits_query->addField('asset_q', 'is_fixed');
    $limits_query->addField('asset_q', 'intrinsic_geometry_geo_type');
    $limits_query->addField('asset_q', 'log_geometry_geo_type');

    $limits_query->addExpression("min(intrinsic_geometry_left)", 'i_left');
    $limits_query->addExpression("min(log_geometry_left)", 'l_left');

    $limits_query->addExpression("min(intrinsic_geometry_bottom)", 'i_bottom');
    $limits_query->addExpression("min(log_geometry_bottom)", 'l_bottom');

    $limits_query->addExpression("max(intrinsic_geometry_right)", 'i_right');
    $limits_query->addExpression("max(log_geometry_right)", 'l_right');

    $limits_query->addExpression("max(intrinsic_geometry_top)", 'i_top');
    $limits_query->addExpression("max(log_geometry_top)", 'l_top');

    $limits_query->groupBy('asset_q.is_fixed')
      ->groupBy('asset_q.intrinsic_geometry_geo_type')
      ->groupBy('asset_q.log_geometry_geo_type');

    $result = $limits_query->execute();

    $rows = $result->fetchAll();

    $rows_by_geometry_type = [];

    foreach ($rows as $row) {
      if ($row->is_fixed) {
        $geometry_type = $row->intrinsic_geometry_geo_type;
      } else {
        $geometry_type = $row->log_geometry_geo_type;
      }

      $rows_by_geometry_type[$geometry_type][] = $row;
    }

    $accumulate_bbox_edge = function ($source, &$dest, $edge, $accumulator) {
      if ($source->is_fixed) {
        $source_edge_value = $source->{'i_' . $edge};
      } else {
        $source_edge_value = $source->{'l_' . $edge};
      }

      if (! isset($source_edge_value)) {
        return;
      }

      if (! isset($dest[$edge])) {
        $dest[$edge] = (float) $source_edge_value;
      } else {
        $dest[$edge] = $accumulator((float) $source_edge_value, $dest[$edge]);
      }
    };

    $bboxes_by_geometry_type = [];

    foreach (FARMOS_WFS_RECOGNIZED_GEOMETRY_TYPES as $geometry_type) {
      $rows = $rows_by_geometry_type[$geometry_type];

      $bboxes_by_geometry_type[$geometry_type] = [];

      foreach ($rows as $row) {
        $accumulate_bbox_edge($row, $bboxes_by_geometry_type[$geometry_type], 'left', 'min');
        $accumulate_bbox_edge($row, $bboxes_by_geometry_type[$geometry_type], 'bottom', 'min');
        $accumulate_bbox_edge($row, $bboxes_by_geometry_type[$geometry_type], 'right', 'max');
        $accumulate_bbox_edge($row, $bboxes_by_geometry_type[$geometry_type], 'top', 'max');
      }
    }

    return $bboxes_by_geometry_type;
  }
}
