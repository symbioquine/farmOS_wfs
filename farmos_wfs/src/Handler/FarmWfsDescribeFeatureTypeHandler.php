<?php

namespace Drupal\farmos_wfs\Handler;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\farmos_wfs\FarmWfsFeatureType;
use Drupal\farmos_wfs\FarmWfsFeatureTypeFactoryValidator;

/**
 * Defines FarmWfsDescribeFeatureTypeHandler class.
 */
class FarmWfsDescribeFeatureTypeHandler {

  protected $entityTypeManager;

  protected $entityTypeBundleInfo;

  protected $entityFieldManager;

  protected $featureTypeFactoryValidator;

  public function __construct(EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $entity_bundle_info, EntityFieldManagerInterface $entity_field_manager,
    FarmWfsFeatureTypeFactoryValidator $feature_type_factory_validator) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_bundle_info;
    $this->entityFieldManager = $entity_field_manager;
    $this->featureTypeFactoryValidator = $feature_type_factory_validator;
  }

  public function handle(array $query_params) {
    $feature_types = [];
    $unknown_type_names = [];
    list ($feature_types, $unknown_type_names) = $this->featureTypeFactoryValidator->type_names_string_to_validated_feature_types(
      $query_params['TYPENAME'] ?? '');

    if (! empty($unknown_type_names)) {
      return farmos_wfs_makeExceptionReport(
        function ($eReport, $elem) {
          $eReport->appendChild(
            $elem('Exception', array(
              "exceptionCode" => "InvalidParameterValue",
              "locator" => "typename"
            )));
        });
    }

    if (empty($feature_types)) {
      $asset_types = array_keys($this->entityTypeBundleInfo->getBundleInfo('asset'));

      foreach ($asset_types as $asset_type) {
        foreach (FARMOS_WFS_RECOGNIZED_GEOMETRY_TYPES as $geometry_type) {
          $feature_types[] = new FarmWfsFeatureType($asset_type, strtolower($geometry_type));
        }
      }
    }

    return farmos_wfs_makeDoc(
      function ($doc, $elem) use ($feature_types) {
        $doc->appendChild(
          $elem('xsd:schema',
            array(
              'targetNamespace' => "https://farmos.org/wfs",
              "xmlns:farmos" => "https://farmos.org/wfs",
              'xmlns:gml' => "http://www.opengis.net/gml",
              'xmlns:xsd' => "http://www.w3.org/2001/XMLSchema",
              'xmlns' => "http://www.w3.org/2001/XMLSchema",
              'elementFormDefault' => "qualified",
              'version' => "0.1"
            ),
            function ($schema, $elem) use ($feature_types) {

              $schema->appendChild(
                $elem('xsd:import',
                  array(
                    "namespace" => "http://www.opengis.net/gml",
                    "schemaLocation" => "http://schemas.opengis.net/gml/3.1.1/base/gml.xsd"
                  )));

              foreach ($feature_types as $feature_type) {

                $schema->appendChild(
                  $elem('xsd:element',
                    array(
                      "name" => $feature_type->unqualifiedTypeName(),
                      "type" => $feature_type->qualifiedTypeSchemaName(),
                      "substitutionGroup" => "gml:_Feature"
                    )));

                $schema->appendChild(
                  $elem('xsd:complexType', array(
                    "name" => $feature_type->unqualifiedTypeSchemaName()
                  ),
                    function ($complexType, $elem) use ($feature_type) {

                      $complexType->appendChild(
                        $elem('xsd:complexContent', array(),
                          function ($complexContent, $elem) use ($feature_type) {

                            $complexContent->appendChild(
                              $elem('xsd:extension', array(
                                "base" => "gml:AbstractFeatureType"
                              ),
                                function ($extension, $elem) use ($feature_type) {

                                  $extension->appendChild(
                                    $elem('xsd:sequence', array(),
                                      function ($sequence, $elem) use ($feature_type) {

                                        $field_definitions = $this->entityFieldManager->getFieldDefinitions('asset',
                                          $feature_type->getAssetType());

                                        foreach ($field_definitions as $field_id => $field_definition) {

                                          $field_type = $field_definition->getType();

                                          $supported_field_types = [
                                            'string',
                                            'text_long',
                                            'timestamp',
                                            'boolean',
                                            'uuid',
                                            'list_string',
                                            'string_long',
                                            'integer',
                                          ];

                                          if (in_array($field_type, $supported_field_types)) {

                                            $elem_attrs = [];

                                            if ($field_definition->isReadOnly()) {
                                              $elem_attrs["name"] = '__' . $field_id;
                                            } else {
                                              $elem_attrs["name"] = $field_id;
                                            }

                                            if ($field_type == 'string' || $field_type == 'text_long' ||
                                            $field_type == 'list_string' || $field_type == 'string_long') {
                                              $elem_attrs["type"] = "string";
                                            } elseif ($field_type == 'timestamp') {
                                              $elem_attrs["type"] = "dateTime";
                                            } elseif ($field_type == 'boolean') {
                                              $elem_attrs["type"] = "boolean";
                                            } elseif ($field_type == 'uuid') {
                                              $elem_attrs["type"] = "string";
                                            } elseif ($field_type == 'integer') {
                                              $elem_attrs["type"] = "integer";
                                            }

                                            if (! $field_definition->isRequired()) {
                                              $elem_attrs['nillable'] = 'true';
                                              $elem_attrs['minOccurs'] = '0';
                                            }

                                            $cardinality = $field_definition->getCardinality();

                                            if ($cardinality > 1) {
                                              $elem_attrs['maxOccurs'] = "$cardinality";
                                            }

                                            $sequence->appendChild($elem('xsd:element', $elem_attrs));
                                          }
                                        }

                                        $sequence->appendChild(
                                          $elem('xsd:element',
                                            array(
                                              "name" => "geometry",
                                              "type" => $feature_type->getGmlGeometryTypeSchemaName()
                                            )));
                                      }));
                                }));
                          }));
                    }));
              }
            }));
      });
  }
}