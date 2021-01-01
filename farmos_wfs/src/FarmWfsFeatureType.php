<?php

namespace Drupal\farmos_wfs;

const FARMOS_WFS_FEATURE_TYPE_PATTERN = '/^(farmos:)?asset_(?P<asset_type>\w+)_(?P<geometry_type>\w+)$/';

class FarmWfsFeatureType {

  protected $assetType;

  protected $geometryType;

  public function __construct(string $asset_type, string $geometry_type) {
    $this->assetType = $asset_type;
    $this->geometryType = $geometry_type;
  }

  public static function fromString(string $feature_type) {
    $matches = [];
    if (! preg_match(FARMOS_WFS_FEATURE_TYPE_PATTERN, $feature_type, $matches)) {
      return null;
    }

    return new FarmWfsFeatureType($matches['asset_type'], $matches['geometry_type']);
  }

  public function getAssetType() {
    return $this->assetType;
  }

  public function getGeometryType() {
    return $this->geometryType;
  }

  public function unqualifiedTypeName() {
    return "asset_{$this->assetType}_{$this->geometryType}";
  }

  public function qualifiedTypeName() {
    return "farmos:" . $this->unqualifiedTypeName();
  }

  public function unqualifiedTypeSchemaName() {
    return $this->unqualifiedTypeName() + "_type";
  }

  public function qualifiedTypeSchemaName() {
    return "farmos:" . $this->unqualifiedTypeSchemaName();
  }

  public function getGeometryTypeName() {
    return \FARMOS_WFS_RECOGNIZED_GEOMETRY_TYPES_LOWERCASE_TO_UPPERCASE[$this->geometryType];
  }

  public function getGmlGeometryTypeSchemaName() {
    $geometry_type_name = $this->getGeometryTypeName();

    return "gml:{$geometry_type_name}PropertyType";
  }
}
