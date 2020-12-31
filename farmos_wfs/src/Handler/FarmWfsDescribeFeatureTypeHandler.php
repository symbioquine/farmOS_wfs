<?php

namespace Drupal\farmos_wfs\Handler;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use const False\MyClass\true;

/**
 * Defines FarmWfsDescribeFeatureTypeHandler class.
 */
class FarmWfsDescribeFeatureTypeHandler {

  protected $entityTypeManager;

  protected $entityTypeBundleInfo;

  protected $entityFieldManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $entity_bundle_info, EntityFieldManagerInterface $entity_field_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_bundle_info;
    $this->entityFieldManager = $entity_field_manager;
  }

  public function handle(array $query_params) {
    // $asset_entity_type = $this->entityTypeManager->getDefinition('asset');

    // dpm($asset_entity_type->id());
    $requested_type_names = array_filter(explode(',', $query_params['TYPENAME'] ?? ''));

    $asset_bundles = $this->entityTypeBundleInfo->getBundleInfo('asset');

    $unknown_type_names = array_filter($requested_type_names,
      function ($requested_type_name) use ($asset_bundles) {
        $matches = [];
        if (! preg_match(FARMOS_WFS_FEATURE_TYPE_PATTERN, $requested_type_name, $matches)) {
          return false;
        }

        $asset_type = $matches['asset_type'];

        if (! in_array($asset_type, $asset_bundles)) {
          return false;
        }

        $geometry_type = $matches['geometry_type'];

        if (! in_array($geometry_type, FARMOS_WFS_RECOGNIZED_GEOMETRY_TYPES_LOWERCASE_TO_UPPERCASE)) {
          return false;
        }

        return true;
      });

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

    if (empty($requested_type_names)) {
      $requested_type_names = [];
      foreach ($asset_bundles as $asset_type => $asset_bundle_info) {
        foreach (FARMOS_WFS_RECOGNIZED_GEOMETRY_TYPES as $geometry_type) {
          $requested_type_names[] = implode('.', [
            'farmos:asset',
            $asset_type,
            strtolower($geometry_type)
          ]);
        }
      }
    }

    return farmos_wfs_makeDoc(
      function ($doc, $elem) use ($requested_type_names) {
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
            function ($schema, $elem) use ($requested_type_names) {

              $schema->appendChild(
                $elem('xsd:import',
                  array(
                    "namespace" => "http://www.opengis.net/gml",
                    "schemaLocation" => "http://schemas.opengis.net/gml/3.1.1/base/gml.xsd"
                  )));

              foreach ($requested_type_names as $requested_type_name) {
                $matches = [];
                preg_match(FARMOS_WFS_FEATURE_TYPE_PATTERN, $requested_type_name, $matches);

                $asset_type = $matches['asset_type'];
                $geometry_type = $matches['geometry_type'];

                $schema->appendChild(
                  $elem('xsd:element',
                    array(
                      "name" => "asset.{$asset_type}.{$geometry_type}",
                      "type" => "farmos:asset.{$asset_type}.{$geometry_type}.type",
                      "substitutionGroup" => "gml:_Feature"
                    )));

                $schema->appendChild(
                  $elem('xsd:complexType', array(
                    "name" => "asset.{$asset_type}.{$geometry_type}.type"
                  ),
                    function ($complexType, $elem) use ($asset_type, $geometry_type) {

                      $complexType->appendChild(
                        $elem('xsd:complexContent', array(),
                          function ($complexContent, $elem) use ($asset_type, $geometry_type) {

                            $complexContent->appendChild(
                              $elem('xsd:extension', array(
                                "base" => "gml:AbstractFeatureType"
                              ),
                                function ($extension, $elem) use ($asset_type, $geometry_type) {

                                  $extension->appendChild(
                                    $elem('xsd:sequence', array(),
                                      function ($sequence, $elem) use ($asset_type, $geometry_type) {

                                        $field_definitions = $this->entityFieldManager->getFieldDefinitions('asset',
                                          $asset_type);

                                        foreach ($field_definitions as $field_id => $field_definition) {
                                          dpm($field_id . ': ' . $field_definition->getType());

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

                                        $geometry_type_name = FARMOS_WFS_RECOGNIZED_GEOMETRY_TYPES_LOWERCASE_TO_UPPERCASE[$geometry_type];

                                        $sequence->appendChild(
                                          $elem('xsd:element',
                                            array(
                                              "name" => "geometry",
                                              "type" => "gml:{$geometry_type_name}PropertyType"
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