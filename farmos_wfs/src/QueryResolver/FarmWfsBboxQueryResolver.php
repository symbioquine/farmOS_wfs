<?php

namespace Drupal\farmos_wfs\QueryResolver;

use Drupal\Core\Entity\EntityTypeManagerInterface;

class FarmWfsBboxQueryResolver {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Retrieves an array of asset ids by geometry type and bounding box.
   */
  function resolve_query(string $asset_type, array $geometry_types, array $bbox) {
    $asset_storage = $this->entityTypeManager->getStorage('asset');

    $query = $asset_storage->getQuery();

    $query->condition('type', $asset_type);
    $query->condition('is_fixed', 1);
    $query->condition('intrinsic_geometry.geo_type', $geometry_types, 'IN');

    $query->condition('intrinsic_geometry.top', $bbox[0], '>=');
    $query->condition('intrinsic_geometry.right', $bbox[1], '>=');
    $query->condition('intrinsic_geometry.bottom', $bbox[2], '<=');
    $query->condition('intrinsic_geometry.left', $bbox[3], '<=');

    return $query->execute();
  }
}
