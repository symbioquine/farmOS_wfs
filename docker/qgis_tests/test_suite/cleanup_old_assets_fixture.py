import pytest
import requests
import unittest
from urllib.parse import urlparse
from collections import defaultdict


@pytest.fixture(scope="class")
def cleanup_old_assets(request):
    cls = request.cls

    with requests.Session() as s:
        s.auth = cls.requests_oauth2

        tc = unittest.TestCase('__init__')

        def assert_get_json(url):
            response = s.get(url)

            if not response.ok:
                print(response.text)

            tc.assertTrue(response.ok)

            return response.json()

        def asset_delete_json_api_entity(entity):
            delete_response = s.delete(
                urlparse(entity['links']['self']['href'])._replace(query='').geturl())

            if not delete_response.ok:
                print(delete_response.text)

            tc.assertTrue(delete_response.ok)

        log_types = assert_get_json(
            "http://www/api/log_type/log_type")

        for log_type in log_types['data']:

            farm_logs = assert_get_json(
                "http://www/api/log/{}?include=asset".format(log_type['attributes']['drupal_internal__id']))

            includes_by_type_and_id = defaultdict(dict)

            for included_entity in farm_logs.get('included', []):
                includes_by_type_and_id[included_entity['type']
                                        ][included_entity['id']] = included_entity

            for log in farm_logs['data']:

                should_delete_log = False

                for asset_ref in log['relationships']['asset']['data']:
                    asset = includes_by_type_and_id.get(
                        asset_ref['type'], {}).get(asset_ref['id'], None)

                    if asset is None:
                        continue

                    notes = (asset['attributes'].get(
                        'notes', None) or {}).get('value', '')

                    if '[created by farmOS_wfs-qgis_tests]' in notes:
                        should_delete_log = True
                        break

                if should_delete_log:
                    asset_delete_json_api_entity(log)

        asset_types = assert_get_json(
            "http://www/api/asset_type/asset_type")

        for asset_type in asset_types['data']:

            farm_assets = assert_get_json(
                "http://www/api/asset/{}".format(asset_type['attributes']['drupal_internal__id']))

            for asset in farm_assets['data']:
                notes = (asset['attributes'].get(
                    'notes', None) or {}).get('value', '')

                if not '[created by farmOS_wfs-qgis_tests]' in notes:
                    continue

                asset_delete_json_api_entity(asset)
