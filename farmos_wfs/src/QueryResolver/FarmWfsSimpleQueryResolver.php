<?php

namespace Drupal\farmos_wfs\QueryResolver;

use Drupal\Core\Entity\EntityTypeManagerInterface;

class FarmWfsSimpleQueryResolver {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Retrieves an array of asset ids by geometry type.
   */
  function resolve_query(string $asset_type, array $geometry_types) {
    $asset_storage = $this->entityTypeManager->getStorage('asset');

    $query = $asset_storage->getQuery();

    $query->condition('type', $asset_type);
    // $query->condition('is_fixed', 1);
    // $query->condition('intrinsic_geometry.geo_type', [
    // 'Point'
    // ], 'IN');

    return $query->execute();
  }
}
