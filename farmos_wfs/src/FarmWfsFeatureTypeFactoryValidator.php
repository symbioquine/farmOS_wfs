<?php

namespace Drupal\farmos_wfs;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

class FarmWfsFeatureTypeFactoryValidator {

  protected $entityTypeBundleInfo;

  public function __construct(EntityTypeBundleInfoInterface $entity_bundle_info) {
    $this->entityTypeBundleInfo = $entity_bundle_info;
  }

  public function type_names_to_validated_feature_types(string $type_names) {
    $requested_type_names = array_filter(explode(',', $type_names));

    $asset_bundles = array_keys($this->entityTypeBundleInfo->getBundleInfo('asset'));

    $feature_types = [];
    $unknown_type_names = [];

    foreach ($requested_type_names as $requested_type_name) {
      $feature_type = FarmWfsFeatureType::fromString($requested_type_name);

      if (! $feature_type) {
        $unknown_type_names[] = $requested_type_name;
        continue;
      }

      if (! in_array($feature_type->getAssetType(), $asset_bundles)) {
        $unknown_type_names[] = $requested_type_name;
        continue;
      }

      if (! array_key_exists($feature_type->getGeometryType(),
        FARMOS_WFS_RECOGNIZED_GEOMETRY_TYPES_LOWERCASE_TO_UPPERCASE)) {
        $unknown_type_names[] = $requested_type_name;
        continue;
      }

      $feature_types[] = $feature_type;
    }

    return [
      $feature_types,
      $unknown_type_names
    ];
  }
}
