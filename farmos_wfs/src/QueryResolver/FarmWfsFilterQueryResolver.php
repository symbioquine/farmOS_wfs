<?php

namespace Drupal\farmos_wfs\QueryResolver;

use Drupal\Core\Entity\EntityTypeManagerInterface;

class FarmWfsFilterQueryResolver {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Retrieves an array of asset ids by geometry type and OGC Filter element.
   */
  function resolve_query(string $asset_type, array $geometry_types, \DOMElement $filter_elem) {
    $filter_ids = [];

    $nontext_filter_children = array_values(
      array_filter(iterator_to_array($filter_elem->childNodes),
        function ($e) {
          return ! ($e instanceof \DOMText && $e->isWhitespaceInElementContent());
        }));

    $distinct_child_names = array_unique(array_map(function ($e) {
      return $e->localName;
    }, $nontext_filter_children));

    if (empty($distinct_child_names)) {
      throw new \Exception("Illegal filter expression. Cannot be empty.");
    }

    if (count($distinct_child_names) > 1) {
      $distinct_child_names_str = print_r($distinct_child_names, TRUE);
      throw new \Exception("Illegal filter expression. Heterogeneous children of types: $distinct_child_names_str");
    }

    if ($distinct_child_names[0] == 'FeatureId' || $distinct_child_names[0] == 'GmlObjectId') {

      $children_with_tag = 'farmos_wfs_get_xnode_children_with_tag';

      $filter_raw_ids = array_merge(
        array_map(function ($e) {
          return $e->getAttribute('fid');
        }, $children_with_tag($filter_elem, 'FeatureId')),
        array_map(function ($e) {
          return $e->getAttribute('id');
        }, $children_with_tag($filter_elem, 'GmlObjectId')));

      $filter_ids = array_map(function ($raw_id) {
        return preg_replace('/^[^.]+\.(.*)$/', '$1', $raw_id);
      }, $filter_raw_ids);
    } else {
      throw new \Exception("Unsupported filter operation: '{$distinct_child_names[0]}'");
    }

    $asset_storage = $this->entityTypeManager->getStorage('asset');

    $query = $asset_storage->getQuery();

    $query->condition('type', $asset_type);
    $query->condition('is_fixed', 1);
    $query->condition('intrinsic_geometry.geo_type', $geometry_types, 'IN');

    $query->condition('uuid', $filter_ids, 'IN');

    return $query->execute();
  }
}
