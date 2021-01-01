<?php

namespace Drupal\farmos_wfs\QueryResolver;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use DOMText;

class FarmWfsFilterQueryResolver {

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
   * Retrieves an array of asset ids by geometry type and OGC Filter element.
   */
  function resolve_query(string $asset_type, array $geometry_types, \DOMElement $filter_elem) {
    return [];
  }

  /*
   * Retrieves an array of taxonomy ids for the areas which have a particular geometry type and (optionally) match a OGC Filter or fall within a given bounding box.
   */
  function farmos_wfs_ogc_filter_one_point_one_to_area_ids($geo_types, $filter_elem, $bbox = null,
    $empty_filter_behavior = FARMOS_WFS_EMPTY_FILTER_BEHAVIOR_MATCH_NONE) {
    $filter_ids = [];
    if ($filter_elem) {
      $nontext_filter_children = array_values(
        array_filter(iterator_to_array($filter_elem->childNodes),
          function ($e) {
            return ! ($e instanceof DOMText && $e->isWhitespaceInElementContent());
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
          return preg_replace('/^[^.]+\.(\d+)$/', '$1', $raw_id);
        }, $filter_raw_ids);
      } else {
        throw new \Exception("Unsupported filter operation: '{$distinct_child_names[0]}'");
      }
    }

    // Bail if the filter has no criteria - i.e. don't support vacuously matching all features
    // TODO: Also check other filter criteria once those are supported
    if ($empty_filter_behavior == FARMOS_WFS_EMPTY_FILTER_BEHAVIOR_MATCH_NONE && empty($filter_ids) && empty($bbox)) {
      return [];
    }

    $query = new EntityFieldQuery();
    $query->entityCondition('entity_type', 'taxonomy_term')->entityCondition('bundle', 'farm_areas');

    if (! empty($geo_types)) {
      $query->fieldCondition('field_farm_geofield', 'geo_type', $geo_types, 'IN', 0);
    }

    // TODO: Consider handling features and/or bounding boxes which cross the anti-meridian
    if (! empty($bbox)) {
      $query->fieldCondition('field_farm_geofield', 'top', $bbox[0], '>=', 0);
      $query->fieldCondition('field_farm_geofield', 'right', $bbox[1], '>=', 0);
      $query->fieldCondition('field_farm_geofield', 'bottom', $bbox[2], '<=', 0);
      $query->fieldCondition('field_farm_geofield', 'left', $bbox[3], '<=', 0);
    }

    if (! empty($filter_ids)) {
      $query->propertyCondition('tid', $filter_ids, 'IN');
    }

    $result = $query->execute();

    if (isset($result['taxonomy_term'])) {
      return array_keys($result['taxonomy_term']);
    }

    return [];
  }
}
