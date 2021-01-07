<?php

namespace Drupal\farmos_wfs\QueryResolver;

use Drupal\farmos_wfs\FarmWfsQueryFactory;

class FarmWfsBboxQueryResolver {

  protected $queryFactory;

  public function __construct(FarmWfsQueryFactory $query_factory) {
    $this->queryFactory = $query_factory;
  }

  /**
   * Retrieves an array of asset ids by geometry type and bounding box.
   */
  function resolve_query(string $asset_type, array $geometry_types, array $bbox) {
    $asset_query = $this->queryFactory->create_query($asset_type, $geometry_types);

    $fixed_or_mobile_query_group = $asset_query->orConditionGroup();

    $fixed_or_mobile_query_group->condition(
      $fixed_or_mobile_query_group->andConditionGroup()
        ->condition('asset_field_data.is_fixed', 1)
        ->condition('intrinsic_geometry.intrinsic_geometry_top', $bbox[0], '>=')
        ->condition('intrinsic_geometry.intrinsic_geometry_right', $bbox[1], '>=')
        ->condition('intrinsic_geometry.intrinsic_geometry_bottom', $bbox[2], '<=')
        ->condition('intrinsic_geometry.intrinsic_geometry_left', $bbox[3], '<='));

    $fixed_or_mobile_query_group->condition(
      $fixed_or_mobile_query_group->andConditionGroup()
        ->condition('asset_field_data.is_fixed', 0)
        ->condition('log_geometry.geometry_top', $bbox[0], '>=')
        ->condition('log_geometry.geometry_right', $bbox[1], '>=')
        ->condition('log_geometry.geometry_bottom', $bbox[2], '<=')
        ->condition('log_geometry.geometry_left', $bbox[3], '<='));

    $asset_query->condition($fixed_or_mobile_query_group);

    $result = $asset_query->execute();

    return $result->fetchCol(0);
  }
}
