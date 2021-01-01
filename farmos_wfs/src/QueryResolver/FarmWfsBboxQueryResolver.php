<?php

namespace Drupal\farmos_wfs\QueryResolver;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class FarmWfsBboxQueryResolver {

  protected $requestStack;

  protected $entityTypeManager;

  protected $entityTypeBundleInfo;

  protected $entityFieldManager;

  public function __construct(RequestStack $request_stack, EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $entity_bundle_info, EntityFieldManagerInterface $entity_field_manager) {
    $this->requestStack = $request_stack;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_bundle_info;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * Retrieves an array of asset ids by geometry type and bounding box.
   */
  function resolve_query(string $asset_type, array $geometry_types, array $bbox = null) {
    return [];
  }
}
