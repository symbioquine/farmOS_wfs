import pytest
import unittest

from lxml import etree

from ..cleanup_old_assets_fixture import cleanup_old_assets
from ..requests_oauth_fixture import requests_oauth
from ..qgis_oauth_cfg_fixture import qgis_oauth_cfg
from ..qgis_helpers_fixture import qgis_helpers
from ..farmos_asset_helpers_fixture import farmos_asset_helpers


@pytest.mark.usefixtures("requests_oauth", "cleanup_old_assets", 'farmos_asset_helpers')
class EdgeCasesTest(unittest.TestCase):
    maxDiff = None

    def test_should_succeed_to_create_asset_with_formatted_xml(self):
        transaction_xml = '''<?xml version="1.0" encoding="UTF-8"?>
<Transaction xmlns="http://www.opengis.net/wfs" xmlns:farmos="https://farmos.org/wfs" xmlns:gml="http://www.opengis.net/gml" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" service="WFS" version="1.1.0" xsi:schemaLocation="https://farmos.org/wfs http://localhost/wfs?SERVICE=WFS&amp;REQUEST=DescribeFeatureType&amp;VERSION=1.0.0&amp;TYPENAME=farmos:asset_land_linestring">
   <Insert>
      <asset_land_linestring xmlns="https://farmos.org/wfs">
         <name>TestBoundary6</name>
         <is_fixed>1</is_fixed>
         <land_type>other</land_type>
         <geometry>
            <gml:LineString srsName="EPSG:4326">
               <gml:posList srsDimension="2">-1.11684370257966625 -0.24127465857359631 -0.9256449165402123 0.06221547799696503 -0.39757207890743551 -0.13808801213960553 -0.24279210925644934 0.33535660091047037</gml:posList>
            </gml:LineString>
         </geometry>
      </asset_land_linestring>
   </Insert>
</Transaction>
'''

        with self.requests_session() as s:
            response = s.post(
                'http://www/wfs?SERVICE=WFS', data=transaction_xml, headers={'content-type': 'application/xml'})

            self.assertEqual(response.status_code, 200)

            root = etree.fromstring(response.text.encode('utf8'))

            inserted_feature_id_elem = root.find("./{*}InsertResults/{*}Feature/{*}FeatureId")

            created_feature_id = inserted_feature_id_elem.attrib['fid'].split('.')[1]

        asset = self.get_asset_by_type_and_id('land', created_feature_id)

        self.assertEqual(asset['attributes']['name'], "TestBoundary6")
        self.assertEqual(asset['attributes']['geometry']['value'], "LINESTRING (-1.116843702579666 -0.2412746585735963, -0.9256449165402123 0.06221547799696503, "
                         "-0.3975720789074355 -0.1380880121396055, -0.2427921092564493 0.3353566009104704)")

    def test_should_fail_to_create_line_string_asset_in_point_layer(self):

        transaction_xml = '''<?xml version="1.0" encoding="UTF-8"?>
<Transaction xmlns="http://www.opengis.net/wfs" xmlns:farmos="https://farmos.org/wfs" xmlns:gml="http://www.opengis.net/gml" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" service="WFS" version="1.1.0" xsi:schemaLocation="https://farmos.org/wfs http://localhost/wfs?SERVICE=WFS&amp;REQUEST=DescribeFeatureType&amp;VERSION=1.0.0&amp;TYPENAME=farmos:asset_land_point">
   <Insert>
      <asset_land_point xmlns="https://farmos.org/wfs">
         <name>TestBoundary7</name>
         <is_fixed>1</is_fixed>
         <land_type>other</land_type>
         <geometry>
            <gml:LineString srsName="EPSG:4326">
               <gml:posList srsDimension="2">-1.11684370257966625 -0.24127465857359631 -0.9256449165402123 0.06221547799696503 -0.39757207890743551 -0.13808801213960553 -0.24279210925644934 0.33535660091047037</gml:posList>
            </gml:LineString>
         </geometry>
      </asset_land_point>
   </Insert>
</Transaction>
'''

        with self.requests_session() as s:
            response = s.post(
                'http://www/wfs?SERVICE=WFS', data=transaction_xml, headers={'content-type': 'application/xml'})

            # TODO: Determine if this is the right status code in WFS
            self.assertEqual(response.status_code, 200)

            root = etree.fromstring(response.text.encode('utf8'))

            summary_total_inserted_elem = root.find("./{*}TransactionSummary/{*}totalInserted")

            self.assertEqual(summary_total_inserted_elem.text, '0')

            inserted_feature_id_elem = root.find("./{*}InsertResults/{*}Feature/{*}FeatureId")

            self.assertFalse(inserted_feature_id_elem)

            transaction_results_message_elem = root.find("./{*}TransactionResults/{*}Action/{*}Message")

            self.assertEqual(transaction_results_message_elem.text, "Attempted to set geometry of type 'LineString' when expected geometry type should be 'Point'")
