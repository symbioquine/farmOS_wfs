import unittest

import pytest

from qgis.core import QgsFeature, QgsGeometry, QgsPointXY, edit, QgsEditError, QgsRectangle

from ..cleanup_old_assets_fixture import cleanup_old_assets
from ..farmos_constants import *
from ..requests_oauth_fixture import requests_oauth
from ..qgis_oauth_cfg_fixture import qgis_oauth_cfg
from ..qgis_helpers_fixture import qgis_helpers
from ..farmos_asset_helpers_fixture import farmos_asset_helpers


@pytest.mark.usefixtures("requests_oauth", "cleanup_old_assets", 'qgis_oauth_cfg', 'qgis_helpers', 'farmos_asset_helpers')
class QgisBasicCrudTest(unittest.TestCase):

    def test_qgis_get_point_features(self):
        north_field_id = self.create_asset('land', {
            "name": "North field",
            "notes": {
                "value": "Sample description... [created by farmOS_wfs-qgis_tests]",
            },
            "intrinsic_geometry": {
                "value": "POINT(-31.040038615465 39.592143995004)",
            },
            "land_type": "field",
            "is_location": True,
            "is_field": True,
        })

        vlayer = self.get_qgis_wfs_vector_layer('farmos:asset_land_point')

        features = list(vlayer.getFeatures())

        north_field_feature = next(
            iter(filter(lambda f: f.attribute('__uuid') == north_field_id, features)))

        self.assertEqual(north_field_feature.attribute('name'), "North field")
        self.assertEqual(north_field_feature.attribute(
            'notes'), "Sample description... [created by farmOS_wfs-qgis_tests]")
        self.assertEqual(north_field_feature.attribute('land_type'), "field")
        self.assertEqual(north_field_feature.geometry().asJson(
        ), '{"coordinates":[-31.040038615465,39.592143995004],"type":"Point"}')

        with self.subTest("with BBOX filtering not containing the feature"):
            vlayer.reload()

            vlayer.selectByRect(QgsRectangle.fromWkt('POLYGON((-30 32, '
                                                     '-31 32, '
                                                     '-31 33, '
                                                     '-30 33, '
                                                     '-30 32))'))

            features = vlayer.getSelectedFeatures()

            self.assertNotIn('North field', list(
                map(lambda f: f.attribute('name'), features)))

        with self.subTest("with BBOX filtering containing the feature"):
            vlayer.reload()

            vlayer.selectByRect(QgsRectangle.fromWkt('POLYGON((-31 39, '
                                                     '-32 39, '
                                                     '-32 40, '
                                                     '-31 40, '
                                                     '-31 39))'))

            features = vlayer.getSelectedFeatures()

            self.assertIn('North field', list(
                map(lambda f: f.attribute('name'), features)))

    def test_qgis_get_line_string_features(self):
        forty_ninth_parallel_id = self.create_asset('land', {
            "name": "49th Parallel",
            "notes": {
                "value": "Another sample description... [created by farmOS_wfs-qgis_tests]",
            },
            "intrinsic_geometry": {
                "value": "LINESTRING(-125.75 49,-53.833333 49)",
            },
            "land_type": "landmark",
            "is_location": True,
            "is_fixed": True,
        })

        vlayer = self.get_qgis_wfs_vector_layer('farmos:asset_land_linestring')

        features = list(vlayer.getFeatures())

        forty_ninth_parallel_feature = next(iter(filter(lambda f: f.attribute(
            '__uuid') == forty_ninth_parallel_id, features)))

        self.assertEqual(forty_ninth_parallel_feature.attribute(
            'name'), "49th Parallel")
        self.assertEqual(forty_ninth_parallel_feature.attribute(
            'notes'), "Another sample description... [created by farmOS_wfs-qgis_tests]")
        self.assertEqual(forty_ninth_parallel_feature.attribute(
            'land_type'), "landmark")
        self.assertEqual(forty_ninth_parallel_feature.geometry().asJson(
        ), '{"coordinates":[[-125.75,49.0],[-53.833333,49.0]],"type":"LineString"}')

    def test_qgis_get_polygon_features(self):
        colorado_id = self.create_asset('land', {
            "name": "Colorado",
            "notes": {
                "value": "Yet another sample description... [created by farmOS_wfs-qgis_tests]",
            },
            "intrinsic_geometry": {
                "value": "POLYGON((-109.0448 37.0004,-102.0424 36.9949,-102.0534 41.0006,-109.0489 40.9996,-109.0448 37.0004,-109.0448 37.0004))",
            },
            "land_type": "property",
            "is_location": True,
            "is_fixed": True,
        })

        vlayer = self.get_qgis_wfs_vector_layer('farmos:asset_land_polygon')

        features = list(vlayer.getFeatures())

        colorado_feature = next(
            iter(filter(lambda f: f.attribute('__uuid') == colorado_id, features)))

        self.assertEqual(colorado_feature.attribute('name'), "Colorado")
        self.assertEqual(colorado_feature.attribute(
            'notes'), "Yet another sample description... [created by farmOS_wfs-qgis_tests]")
        self.assertEqual(colorado_feature.attribute('land_type'), "property")
        self.assertEqual(colorado_feature.geometry().asJson(
        ), '{"coordinates":[[[-109.0448,37.0004],[-102.0424,36.9949],[-102.0534,41.0006],[-109.0489,40.9996],[-109.0448,37.0004],[-109.0448,37.0004]]],"type":"Polygon"}')

    def test_qgis_create_point_land_asset(self):
        vlayer = self.get_qgis_wfs_vector_layer('farmos:asset_land_point')

        with edit(vlayer):
            f = QgsFeature(vlayer.fields())
            f.setAttribute("name", "Example point")
            f.setAttribute("land_type", "other")
            f.setAttribute(
                "notes", "Description for point created via WFS from QGIS [created by farmOS_wfs-qgis_tests]")
            f.setGeometry(QgsGeometry.fromPointXY(QgsPointXY(10, 10)))

            vlayer.addFeature(f)

        vlayer.reload()

        features = list(vlayer.getFeatures())

        created_feature = next(iter(filter(
            lambda f: 'Description for point created via WFS from QGIS' in str(f.attribute('notes')), features)))

        created_area_id = created_feature.attribute('__uuid')

        asset = self.get_asset_by_type_and_id('land', created_area_id)

        self.assertEqual(asset['attributes']['name'], "Example point")
        self.assertEqual(asset['attributes']['land_type'], "other")
        self.assertIn(
            "Description for point created via WFS from QGIS [created by farmOS_wfs-qgis_tests]", asset['attributes']['notes']['value'])
        self.assertEqual(asset['attributes']['geometry']
                         ['value'], 'POINT (10 10)')

    def test_qgis_create_land_asset_with_unknown_land_type(self):
        vlayer = self.get_qgis_wfs_vector_layer('farmos:asset_land_point')

        with self.assertRaises(QgsEditError):
            with edit(vlayer):
                f = QgsFeature(vlayer.fields())
                f.setAttribute("name", "Example point of unknown type")
                f.setAttribute("land_type", "somethingunknown")
                f.setAttribute(
                    "notes", "Description for point that shouldn't be created via WFS from QGIS [created by farmOS_wfs-qgis_tests]")
                f.setGeometry(QgsGeometry.fromPointXY(QgsPointXY(10, 10)))

                vlayer.addFeature(f)

    def test_qgis_create_line_string_water_asset(self):
        self.maxDiff = None

        vlayer = self.get_qgis_wfs_vector_layer(
            'farmos:asset_water_linestring')

        with edit(vlayer):
            f = QgsFeature(vlayer.fields())
            f.setAttribute("name", "Example line string")
            f.setAttribute(
                "notes", "Description for line string created via WFS from QGIS [created by farmOS_wfs-qgis_tests]")
            f.setGeometry(QgsGeometry.fromWkt("LINESTRING(-124.81957346280673 48.41387902376911,-123.93862573833353 45.842330434997535,"
                                              "-124.34239344538385 40.38160427010013,-120.56165946118642 34.59083932797574,"
                                              "-118.06564090851239 33.679388968040115,-117.25810549441198 32.60369705122281)"))

            vlayer.addFeature(f)

        vlayer.reload()

        features = list(vlayer.getFeatures())

        created_feature = next(iter(filter(
            lambda f: 'Description for line string created via WFS from QGIS' in f.attribute('notes'), features)))

        created_area_id = created_feature.attribute('__uuid')

        asset = self.get_asset_by_type_and_id('water', created_area_id)

        self.assertEqual(asset['attributes']['name'], "Example line string")
        self.assertIn(
            "Description for line string created via WFS from QGIS [created by farmOS_wfs-qgis_tests]", asset['attributes']['notes']['value'])
        self.assertEqual(asset['attributes']['geometry']['value'], "LINESTRING (-124.8195734628067 48.41387902376911, -123.9386257383335 45.84233043499754, "
                         "-124.3423934453838 40.38160427010013, -120.5616594611864 34.59083932797574, -118.0656409085124 33.67938896804012, "
                         "-117.258105494412 32.60369705122281)")

    def test_qgis_create_polygon_equipment_asset(self):
        vlayer = self.get_qgis_wfs_vector_layer(
            'farmos:asset_equipment_polygon')

        with edit(vlayer):
            f = QgsFeature(vlayer.fields())
            f.setAttribute("name", "Example polygon")
            f.setAttribute(
                "notes", "Description for polygon created via WFS from QGIS [created by farmOS_wfs-qgis_tests]")
            f.setAttribute("is_fixed", 1)
            f.setGeometry(QgsGeometry.fromWkt(
                "POLYGON((-104.0556 41.0037,-104.0584 44.9949,-111.0539 44.9998,-111.0457 40.9986,-104.0556 41.0006,-104.0556 41.0037))"))

            vlayer.addFeature(f)

        vlayer.reload()

        features = list(vlayer.getFeatures())

        created_feature = next(iter(filter(
            lambda f: 'Description for polygon created via WFS from QGIS' in f.attribute('notes'), features)))

        created_area_id = created_feature.attribute('__uuid')

        asset = self.get_asset_by_type_and_id('equipment', created_area_id)

        self.assertEqual(asset['attributes']['name'], "Example polygon")
        self.assertIn(
            "Description for polygon created via WFS from QGIS [created by farmOS_wfs-qgis_tests]", asset['attributes']['notes']['value'])
        self.assertEqual(asset['attributes']['geometry']['value'],
                         "POLYGON ((-104.0556 41.0037, -104.0584 44.9949, -111.0539 44.9998, -111.0457 40.9986, -104.0556 41.0006, -104.0556 41.0037))")

    def test_qgis_update_and_delete_point_asset(self):
        south_field_id = self.create_asset('land', {
            "name": "South field",
            "notes": {
                "value": "Sample south field description... [created by farmOS_wfs-qgis_tests]",
            },
            "intrinsic_geometry": {
                "value": "POINT(-98.27361139210143 28.614320347429143)",
            },
            "land_type": "field",
            "is_location": True,
            "is_fixed": True,
        })

        vlayer = self.get_qgis_wfs_vector_layer('farmos:asset_land_point')

        features = list(vlayer.getFeatures())

        south_field_feature = next(
            iter(filter(lambda f: f.attribute('__uuid') == south_field_id, features)))

        with self.subTest("update point feature"):
            with edit(vlayer):
                south_field_feature.setAttribute(
                    "name", "South field (updated)")
                south_field_feature.setAttribute("land_type", "paddock")
                south_field_feature.setAttribute(
                    "notes", "Sample (updated) south field description... [created by farmOS_wfs-qgis_tests]")
                south_field_feature.setGeometry(QgsGeometry.fromWkt(
                    "POINT(-95.32595536400291 29.29726983388369)"))

                vlayer.updateFeature(south_field_feature)

            asset = self.get_asset_by_type_and_id('land', south_field_id)

            self.assertEqual(asset['attributes']['name'],
                             "South field (updated)")
            self.assertEqual(asset['attributes']['land_type'], "paddock")
            self.assertIn(
                "description", "Sample (updated) south field description... [created by farmOS_wfs-qgis_tests]", asset['attributes']['notes']['value'])
            self.assertEqual(asset['attributes']['geometry']['value'],
                             'POINT (-95.32595536400291 29.29726983388369)')

        with self.subTest("delete point feature"):
            with edit(vlayer):
                self.assertTrue(vlayer.deleteFeature(south_field_feature.id()))

            self.assert_asset_does_not_exist('land', south_field_id)

    def test_qgis_update_and_delete_line_string_asset(self):
        east_coast_id = self.create_asset('water', {
            "name": "East coast",
            "notes": {
                "value": "Sample east coast description... [created by farmOS_wfs-qgis_tests]",
            },
            "intrinsic_geometry": {
                "value": "LINESTRING(-67.11339126191727 44.38410924306203,-70.66046174786861 43.05308358586231,"
                "-70.38761017202621 41.38618744731713,-75.02608696134718 38.61744240874938,"
                "-75.4353643251108 35.51577343924532,-81.30167320572264 30.71929617429477,"
                "-80.00562822047117 25.425723522771094)",
            },
            "is_location": True,
            "is_fixed": True,
        })

        vlayer = self.get_qgis_wfs_vector_layer(
            'farmos:asset_water_linestring')

        features = list(vlayer.getFeatures())

        east_coast_feature = next(
            iter(filter(lambda f: f.attribute('__uuid') == east_coast_id, features)))

        with self.subTest("update line string feature"):
            with edit(vlayer):
                east_coast_feature.setAttribute("name", "East coast (updated)")
                east_coast_feature.setGeometry(QgsGeometry.fromWkt(
                    "LINESTRING (-67.113391261917 44.384109243062, -69.15977808073499 44.237678117855, "
                    "-70.66046174786899 43.053083585862, -70.387610172026 41.386187447317, -73.525403294214 40.665750477737, "
                    "-75.026086961347 38.617442408749, -75.435364325111 35.515773439245, -79.255286386905 33.321470318467, "
                    "-81.30167320572301 30.719296174295, -79.86920243255 28.285505376336, -80.005628220471 25.425723522771)"))

                vlayer.updateFeature(east_coast_feature)

            asset = self.get_asset_by_type_and_id('water', east_coast_id)

            self.assertEqual(asset['attributes']['name'],
                             "East coast (updated)")
            self.assertEqual(asset['attributes']['geometry']['value'],
                             "LINESTRING (-67.113391261917 44.384109243062, -69.15977808073499 44.237678117855, "
                             "-70.66046174786899 43.053083585862, -70.387610172026 41.386187447317, -73.525403294214 40.665750477737, "
                             "-75.026086961347 38.617442408749, -75.435364325111 35.515773439245, -79.255286386905 33.321470318467, "
                             "-81.30167320572301 30.719296174295, -79.86920243255 28.285505376336, -80.005628220471 25.425723522771)")

        with self.subTest("delete line string feature"):
            with edit(vlayer):
                self.assertTrue(vlayer.deleteFeature(east_coast_feature.id()))

            self.assert_asset_does_not_exist('water', east_coast_id)

    def test_qgis_update_and_delete_polygon_asset(self):
        self.maxDiff = None

        nevada_id = self.create_asset('land', {
            "name": "Nevada",
            "notes": {
                "value": "Sample Nevada description... [created by farmOS_wfs-qgis_tests]",
            },
            "intrinsic_geometry": {
                "value": "POLYGON((-120.03963847422418 41.96779624409592,-114.05159157669674 41.91964576564612,"
                         "-113.9868559345613 34.79796936594694,-120.00727065315647 38.98953879241205,"
                         "-120.03963847422418 41.96779624409592))",
            },
            "land_type": "property",
            "is_location": True,
            "is_fixed": True,
        })

        vlayer = self.get_qgis_wfs_vector_layer('farmos:asset_land_polygon')

        features = list(vlayer.getFeatures())

        nevada_feature = next(
            iter(filter(lambda f: f.attribute('__uuid') == nevada_id, features)))

        with self.subTest("update polygon feature"):
            with edit(vlayer):
                nevada_feature.setAttribute("name", "Nevada (updated)")
                nevada_feature.setAttribute("land_type", "other")
                nevada_feature.setGeometry(QgsGeometry.fromWkt(
                    "POLYGON((-120.0008182980056 41.99427533301156,-114.04245641767388 41.99250627234076,"
                    "-114.04392482849038 36.21145647925469,-114.20918794522191 35.995762847791,-114.51771008294033 36.14492383224095,"
                    "-114.74513682100782 36.0732214875824,-114.71740185295083 35.7497520266312,-114.55653903822014 35.212237546616066,"
                    "-114.63419694877979 35.00804095771656,-120.00082376388009 38.99997808039316,-120.0008182980056 41.99427533301156))"))

                vlayer.updateFeature(nevada_feature)

            asset = self.get_asset_by_type_and_id('land', nevada_id)

            self.assertEqual(asset['attributes']['name'], "Nevada (updated)")
            self.assertEqual(asset['attributes']['land_type'], "other")
            self.assertEqual(asset['attributes']['geometry']['value'],
                             "POLYGON ((-120.0008182980056 41.99427533301156, -114.0424564176739 41.99250627234076, "
                             "-114.0439248284904 36.21145647925469, -114.2091879452219 35.995762847791, -114.5177100829403 36.14492383224095, "
                             "-114.7451368210078 36.0732214875824, -114.7174018529508 35.7497520266312, -114.5565390382201 35.21223754661607, "
                             "-114.6341969487798 35.00804095771656, -120.0008237638801 38.99997808039316, -120.0008182980056 41.99427533301156))")

        with self.subTest("delete polygon feature"):
            with edit(vlayer):
                self.assertTrue(vlayer.deleteFeature(nevada_feature.id()))

            self.assert_asset_does_not_exist('land', nevada_id)

    def test_qgis_create_nonfixed_point_animal_asset(self):
        vlayer = self.get_qgis_wfs_vector_layer('farmos:asset_animal_point')

        with edit(vlayer):
            f = QgsFeature(vlayer.fields())
            f.setAttribute("name", "Dolly")
            f.setAttribute(
                "notes", "Description for the animal non-fixed point asset created via WFS from QGIS [created by farmOS_wfs-qgis_tests]")
            f.setAttribute("is_fixed", 0)
            f.setGeometry(QgsGeometry.fromPointXY(QgsPointXY(15, 15)))

            vlayer.addFeature(f)

        vlayer.reload()

        features = list(vlayer.getFeatures())

        created_feature = next(iter(filter(
            lambda f: 'Description for the animal non-fixed point asset created via WFS from QGIS' in str(f.attribute('notes')), features)))

        created_asset_id = created_feature.attribute('__uuid')

        asset = self.get_asset_by_type_and_id('animal', created_asset_id)

        self.assertEqual(asset['attributes']['name'], "Dolly")
        self.assertIn(
            "Description for the animal non-fixed point asset created via WFS from QGIS [created by farmOS_wfs-qgis_tests]", asset['attributes']['notes']['value'])
        self.assertEqual(asset['attributes']['is_fixed'], False)
        self.assertEqual(asset['attributes']['geometry']
                         ['value'], 'POINT (15 15)')

        with self.subTest("move nonfixed point animal asset"):
            with edit(vlayer):
                created_feature.setGeometry(QgsGeometry.fromWkt(
                    "POINT(11 14)"))

                vlayer.updateFeature(created_feature)

            asset = self.get_asset_by_type_and_id('animal', created_asset_id)

            self.assertEqual(asset['attributes']['geometry']
                             ['value'], 'POINT (11 14)')
