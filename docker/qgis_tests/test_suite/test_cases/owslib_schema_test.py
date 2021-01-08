from collections import defaultdict
from contextlib import contextmanager
import json
import os
import sys
import unittest
from urllib.parse import urlencode

import pytest
import requests

from qgis.core import QgsAuthMethodConfig, QgsJsonUtils, QgsApplication, QgsVectorLayer, QgsFeature, QgsVectorLayerUtils, QgsGeometry, QgsPointXY, edit, QgsEditError, QgsRectangle

from ..cleanup_old_assets_fixture import cleanup_old_assets
from ..farmos_constants import *
from ..owslib_fixture import owslib
from ..requests_oauth_fixture import requests_oauth
from ..qgis_oauth_cfg_fixture import qgis_oauth_cfg


@pytest.mark.usefixtures('requests_oauth', 'owslib', 'qgis_oauth_cfg')
class OwsLibSchemaTests(unittest.TestCase):

    def test_owslib_service_info(self):
        self.assertEqual(self.wfs11.identification.title, "farmOS OGC WFS API")

        self.assertEqual(self.wfs11.provider.name, "Test0")
        self.assertEqual(self.wfs11.provider.url, "http://www")

        self.assertSetEqual({operation.name for operation in self.wfs11.operations}, {
                            'GetCapabilities', 'GetFeature', 'DescribeFeatureType', 'Transaction'})

        self.assertSetEqual(set(self.wfs11.contents), {
                            'farmos:asset_{asset_type}_{geometry_type}'.format(
                                asset_type=asset_type, geometry_type=geometry_type)
                            for asset_type in ('animal', 'equipment', 'land', 'plant', 'structure', 'water')
                            for geometry_type in ('point', 'linestring', 'polygon')
                            })

    def test_owslib_land_asset_point_schema(self):
        self.maxDiff = None

        land_asset_point_schema = self.wfs11.get_schema(
            'farmos:asset_land_point')

        self.assertDictEqual(land_asset_point_schema, {
            'properties': {
                '__id': 'integer',
                '__uuid': 'string',
                '__revision_id': 'integer',
                '__revision_translation_affected': 'boolean',
                'name': 'string',
                'data': 'string',
                'land_type': 'string',
                'notes': 'string',
                'is_fixed': 'boolean',
                'is_location': 'boolean',
                'archived': 'dateTime',
                'flag': 'string',
                'default_langcode': 'boolean',
                'revision_default': 'boolean',
                'revision_log_message': 'string',
            },
            'required': ['name', 'land_type', 'geometry'],
            'geometry': 'Point',
            'geometry_column': 'geometry',
        })

    def test_owslib_water_asset_line_string_schema(self):
        self.maxDiff = None

        water_asset_line_string_schema = self.wfs11.get_schema(
            'farmos:asset_water_linestring')

        self.assertDictEqual(water_asset_line_string_schema, {
            'properties': {
                '__id': 'integer',
                '__uuid': 'string',
                '__revision_id': 'integer',
                '__revision_translation_affected': 'boolean',
                'name': 'string',
                'data': 'string',
                'notes': 'string',
                'is_fixed': 'boolean',
                'is_location': 'boolean',
                'archived': 'dateTime',
                'flag': 'string',
                'default_langcode': 'boolean',
                'revision_default': 'boolean',
                'revision_log_message': 'string',
            },
            'required': ['name', 'geometry'],
            'geometry': 'LineString',
            'geometry_column': 'geometry',
        })

    def test_owslib_structure_asset_polygon_schema(self):
        self.maxDiff = None

        structure_asset_polygon_schema = self.wfs11.get_schema(
            'farmos:asset_structure_polygon')

        self.assertDictEqual(structure_asset_polygon_schema, {
            'properties': {
                '__id': 'integer',
                '__uuid': 'string',
                '__revision_id': 'integer',
                '__revision_translation_affected': 'boolean',
                'name': 'string',
                'data': 'string',
                'structure_type': 'string',
                'notes': 'string',
                'is_fixed': 'boolean',
                'is_location': 'boolean',
                'archived': 'dateTime',
                'flag': 'string',
                'default_langcode': 'boolean',
                'revision_default': 'boolean',
                'revision_log_message': 'string',
            },
            'required': ['name', 'structure_type', 'geometry'],
            'geometry': 'Polygon',
            'geometry_column': 'geometry',
        })
