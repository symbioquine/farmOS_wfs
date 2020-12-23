# -*- coding: utf-8 -*-
import json
import os
import sys
import unittest
from urllib.parse import urlencode

import requests

from oauthlib.oauth2 import LegacyApplicationClient
from owslib.util import Authentication
from owslib.wfs import WebFeatureService
from qgis.core import QgsAuthMethodConfig, QgsJsonUtils, QgsApplication, QgsVectorLayer, QgsFeature, QgsVectorLayerUtils, QgsGeometry, QgsPointXY, edit
from requests_oauthlib import OAuth2Session, OAuth2


os.environ['OAUTHLIB_INSECURE_TRANSPORT'] = '1'


WFS_ENDPOINT = 'http://www/wfs'

OAUTH_ENDPOINT = 'http://www/oauth/token'
OAUTH_CLIENT_ID = 'farm'
OAUTH_SCOPE = 'user_access'
OAUTH_USERNAME = 'root'
OAUTH_PASSWORD = 'test'


class TestTest(unittest.TestCase):

    @classmethod
    def setUpClass(cls):
        cls.setup_qgis_oauth_cfg()
        cls.setup_requests_oauth()
        cls.setup_owslib()

        with requests.Session() as s:
            s.auth = cls.requests_oauth2

            farm_areas = s.get(
                "http://www/taxonomy_term.json?bundle=farm_areas").json()

            for area in farm_areas['list']:
                if not '[created by farmOS_wfs-qgis_tests]' in area['description']:
                    continue

                delete_response = s.delete(
                    'http://www/taxonomy_term/' + area['tid'])

                cls.assertTrue(None, delete_response.ok)

            north_field_create_response = s.post('http://www/taxonomy_term', json={
                "vocabulary": "3",
                "name": "North field",
                "description": "Sample description... [created by farmOS_wfs-qgis_tests]",
                "area_type": "field",
                "geofield": [
                    {
                        "geom": "POINT(-31.040038615465 39.592143995004)",
                    },
                ],
            })

            cls.assertTrue(None, north_field_create_response.ok)

            cls.north_field_id = north_field_create_response.json()['id']

            forty_ninth_parallel_create_response = s.post('http://www/taxonomy_term', json={
                "vocabulary": "3",
                "name": "49th Parallel",
                "description": "Another sample description... [created by farmOS_wfs-qgis_tests]",
                "area_type": "landmark",
                "geofield": [
                    {
                        "geom": "LINESTRING(-125.75 49,-53.833333 49)",
                    },
                ],
            })

            cls.assertTrue(None, forty_ninth_parallel_create_response.ok)

            cls.forty_ninth_parallel_id = forty_ninth_parallel_create_response.json()[
                'id']

            colorado_create_response = s.post('http://www/taxonomy_term', json={
                "vocabulary": "3",
                "name": "Colorado",
                "description": "Yet another sample description... [created by farmOS_wfs-qgis_tests]",
                "area_type": "property",
                "geofield": [
                    {
                        "geom": "POLYGON((-109.0448 37.0004,-102.0424 36.9949,-102.0534 41.0006,-109.0489 40.9996,-109.0448 37.0004,-109.0448 37.0004))",
                    },
                ],
            })

            cls.assertTrue(None, colorado_create_response.ok)

            cls.colorado_id = colorado_create_response.json()['id']

    def test_qgis_get_point_features(self):
        vlayer = self.get_qgis_wfs_vector_layer('farmos:PointArea')

        self.assertTrue(vlayer.isValid())

        features = list(vlayer.getFeatures())

        north_field_feature = next(
            iter(filter(lambda f: f.attribute('area_id') == self.north_field_id, features)))

        self.assertEqual(north_field_feature.attribute('name'), "North field")
        self.assertEqual(north_field_feature.attribute(
            'description'), "Sample description... [created by farmOS_wfs-qgis_tests]")
        self.assertEqual(north_field_feature.attribute('area_type'), "field")
        self.assertEqual(north_field_feature.geometry().asJson(
        ), '{"coordinates":[-31.040038615465,39.592143995004],"type":"Point"}')

    def test_qgis_get_line_string_features(self):
        vlayer = self.get_qgis_wfs_vector_layer('farmos:LineStringArea')

        self.assertTrue(vlayer.isValid())

        features = list(vlayer.getFeatures())

        forty_ninth_parallel_feature = next(iter(filter(lambda f: f.attribute(
            'area_id') == self.forty_ninth_parallel_id, features)))

        self.assertEqual(forty_ninth_parallel_feature.attribute(
            'name'), "49th Parallel")
        self.assertEqual(forty_ninth_parallel_feature.attribute(
            'description'), "Another sample description... [created by farmOS_wfs-qgis_tests]")
        self.assertEqual(forty_ninth_parallel_feature.attribute(
            'area_type'), "landmark")
        self.assertEqual(forty_ninth_parallel_feature.geometry().asJson(
        ), '{"coordinates":[[-125.75,49.0],[-53.833333,49.0]],"type":"LineString"}')

    def test_qgis_get_polygon_features(self):
        vlayer = self.get_qgis_wfs_vector_layer('farmos:PolygonArea')

        self.assertTrue(vlayer.isValid())

        features = list(vlayer.getFeatures())

        colorado_feature = next(
            iter(filter(lambda f: f.attribute('area_id') == self.colorado_id, features)))

        self.assertEqual(colorado_feature.attribute('name'), "Colorado")
        self.assertEqual(colorado_feature.attribute(
            'description'), "Yet another sample description... [created by farmOS_wfs-qgis_tests]")
        self.assertEqual(colorado_feature.attribute('area_type'), "property")
        self.assertEqual(colorado_feature.geometry().asJson(
        ), '{"coordinates":[[[-109.0448,37.0004],[-102.0424,36.9949],[-102.0534,41.0006],[-109.0489,40.9996],[-109.0448,37.0004],[-109.0448,37.0004]]],"type":"Polygon"}')

    def test_qgis_create_point_feature(self):
        vlayer = self.get_qgis_wfs_vector_layer('farmos:PointArea')

        with edit(vlayer):
            f = QgsFeature(vlayer.fields())
            f.setAttribute("name", "Example point")
            f.setAttribute("area_type", "building")
            f.setAttribute(
                "description", "Description for point created via WFS from QGIS [created by farmOS_wfs-qgis_tests]")
            f.setGeometry(QgsGeometry.fromPointXY(QgsPointXY(10, 10)))

            vlayer.addFeature(f)

        vlayer.reload()

        features = list(vlayer.getFeatures())

        created_feature = next(iter(filter(
            lambda f: 'Description for point created via WFS from QGIS' in f.attribute('description'), features)))

        created_area_id = created_feature.attribute('area_id')

        area = self.get_area_entity_by_id(created_area_id)

        self.assertEqual(area['name'], "Example point")
        self.assertEqual(area['area_type'], "building")
        # The Drupal entity API adds some markup around our description so just
        # assert that the description is a substring of it
        self.assertIn(
            "Description for point created via WFS from QGIS [created by farmOS_wfs-qgis_tests]", area['description'])
        self.assertEqual(area['geofield'][0]['geom'], 'POINT (10 10)')

    def test_qgis_create_line_string_feature(self):
        vlayer = self.get_qgis_wfs_vector_layer('farmos:LineStringArea')

        with edit(vlayer):
            f = QgsFeature(vlayer.fields())
            f.setAttribute("name", "Example line string")
            f.setAttribute("area_type", "water")
            f.setAttribute(
                "description", "Description for line string created via WFS from QGIS [created by farmOS_wfs-qgis_tests]")
            f.setGeometry(QgsGeometry.fromWkt("LINESTRING(-124.81957346280673 48.41387902376911,-123.93862573833353 45.842330434997535,"
                                              "-124.34239344538385 40.38160427010013,-120.56165946118642 34.59083932797574,"
                                              "-118.06564090851239 33.679388968040115,-117.25810549441198 32.60369705122281)"))

            vlayer.addFeature(f)

        vlayer.reload()

        features = list(vlayer.getFeatures())

        created_feature = next(iter(filter(
            lambda f: 'Description for line string created via WFS from QGIS' in f.attribute('description'), features)))

        created_area_id = created_feature.attribute('area_id')

        area = self.get_area_entity_by_id(created_area_id)

        self.assertEqual(area['name'], "Example line string")
        self.assertEqual(area['area_type'], "water")
        # The Drupal entity API adds some markup around our description so just
        # assert that the description is a substring of it
        self.assertIn(
            "Description for line string created via WFS from QGIS [created by farmOS_wfs-qgis_tests]", area['description'])
        self.assertEqual(area['geofield'][0]['geom'], "LINESTRING (-124.81957346281 48.413879023769, -123.93862573833 45.842330434998, "
                         "-124.34239344538 40.3816042701, -120.56165946119 34.590839327976, "
                         "-118.06564090851 33.67938896804, -117.25810549441 32.603697051223)")

    def test_qgis_create_polygon_feature(self):
        vlayer = self.get_qgis_wfs_vector_layer('farmos:PolygonArea')

        with edit(vlayer):
            f = QgsFeature(vlayer.fields())
            f.setAttribute("name", "Example polygon")
            f.setAttribute("area_type", "other")
            f.setAttribute(
                "description", "Description for polygon created via WFS from QGIS [created by farmOS_wfs-qgis_tests]")
            f.setGeometry(QgsGeometry.fromWkt(
                "POLYGON((-104.0556 41.0037,-104.0584 44.9949,-111.0539 44.9998,-111.0457 40.9986,-104.0556 41.0006,-104.0556 41.0037))"))

            vlayer.addFeature(f)

        vlayer.reload()

        features = list(vlayer.getFeatures())

        created_feature = next(iter(filter(
            lambda f: 'Description for polygon created via WFS from QGIS' in f.attribute('description'), features)))

        created_area_id = created_feature.attribute('area_id')

        area = self.get_area_entity_by_id(created_area_id)

        self.assertEqual(area['name'], "Example polygon")
        self.assertEqual(area['area_type'], "other")
        # The Drupal entity API adds some markup around our description so just
        # assert that the description is a substring of it
        self.assertIn(
            "Description for polygon created via WFS from QGIS [created by farmOS_wfs-qgis_tests]", area['description'])
        self.assertEqual(area['geofield'][0]['geom'],
                         "POLYGON ((-104.0556 41.0037, -104.0584 44.9949, -111.0539 44.9998, -111.0457 40.9986, -104.0556 41.0006, -104.0556 41.0037))")

    def test_owslib_service_info(self):
        self.assertEqual(self.wfs11.identification.title, "farmOS OGC WFS API")

        self.assertEqual(self.wfs11.provider.name, "Test0")
        self.assertEqual(self.wfs11.provider.url, "http://www")

        self.assertSetEqual({operation.name for operation in self.wfs11.operations}, {
                            'GetCapabilities', 'GetFeature', 'DescribeFeatureType', 'Transaction'})

        self.assertSetEqual(set(self.wfs11.contents), {
                            'farmos:PointArea', 'farmos:PolygonArea', 'farmos:LineStringArea'})

    def test_owslib_point_area(self):
        point_area_schema = self.wfs11.get_schema('farmos:PointArea')

        self.assertDictEqual(point_area_schema, {
            'properties': {
                'area_id': 'string',
                'name': 'string',
                'area_type': 'string',
                'description': 'string'
            },
            'required': ['geometry', 'name'],
            'geometry': 'Point',
            'geometry_column': 'geometry',
        })

    def test_owslib_line_string_area(self):
        line_string_area_schema = self.wfs11.get_schema(
            'farmos:LineStringArea')

        self.assertDictEqual(line_string_area_schema, {
            'properties': {
                'area_id': 'string',
                'name': 'string',
                'area_type': 'string',
                'description': 'string'
            },
            'required': ['geometry', 'name'],
            'geometry': 'LineString',
            'geometry_column': 'geometry',
        })

    def test_owslib_polygon_area(self):
        polygon_area_schema = self.wfs11.get_schema('farmos:PolygonArea')

        self.assertDictEqual(polygon_area_schema, {
            'properties': {
                'area_id': 'string',
                'name': 'string',
                'area_type': 'string',
                'description': 'string'
            },
            'required': ['geometry', 'name'],
            'geometry': 'Polygon',
            'geometry_column': 'geometry',
        })

    def get_qgis_wfs_vector_layer(self, type_name):
        return QgsVectorLayer(
            WFS_ENDPOINT + '?' + urlencode(dict(
                service='WFS',
                request='GetFeature',
                version='1.1.0',
                typename=type_name,
                authcfg=self.cfg.id(),
            ), safe=':'), type_name, "WFS")

    def get_area_entity_by_id(self, area_id):
        self.assertIsInstance(area_id, str)
        self.assertTrue(area_id.isnumeric(),
                        "area_id.isnumeric()")

        with requests.Session() as s:
            s.auth = self.requests_oauth2

            area_response = s.get(
                "http://www/taxonomy_term/{}.json".format(area_id))

        self.assertTrue(area_response.ok)

        return area_response.json()

    @classmethod
    def setup_requests_oauth(cls):
        oauth_client = LegacyApplicationClient(client_id=OAUTH_CLIENT_ID)

        oauth_session = OAuth2Session(client=oauth_client)

        token = oauth_session.fetch_token(token_url=OAUTH_ENDPOINT,
                                          username=OAUTH_USERNAME, password=OAUTH_PASSWORD, client_id=OAUTH_CLIENT_ID, scope=OAUTH_SCOPE)

        cls.requests_oauth2 = OAuth2(client=LegacyApplicationClient(
            client_id=OAUTH_CLIENT_ID), token=token)

    @classmethod
    def setup_owslib(cls):
        cls.owslib_auth = Authentication(auth_delegate=cls.requests_oauth2)

        cls.wfs11 = WebFeatureService(
            url=WFS_ENDPOINT, version='1.1.0', auth=cls.owslib_auth)

    @classmethod
    def setup_qgis_oauth_cfg(cls):
        cfg = QgsAuthMethodConfig()

        cfg.setName("test-cfg-method")
        cfg.setMethod("OAuth2")

        oauth2_config = {
            "clientId": OAUTH_CLIENT_ID,
            "configType": 1,
            "grantFlow": 2,
            "username": OAUTH_USERNAME,
            "password": OAUTH_PASSWORD,
            "persistToken": False,
            "requestTimeout": 30,
            "scope": OAUTH_SCOPE,
            "tokenUrl": OAUTH_ENDPOINT,
            "version": 1
        }

        cfg.setConfigMap({'oauth2config': json.dumps(oauth2_config)})

        if not QgsApplication.authManager().masterPasswordIsSet():
            QgsApplication.authManager().setMasterPassword("test")

        QgsApplication.authManager().storeAuthenticationConfig(cfg)

        cls.cfg = cfg


def run_all():
    """Default function that is called by the runner if nothing else is specified"""
    suite = unittest.TestLoader().loadTestsFromTestCase(TestTest)
    unittest.TextTestRunner(verbosity=3, stream=sys.stdout).run(suite)
