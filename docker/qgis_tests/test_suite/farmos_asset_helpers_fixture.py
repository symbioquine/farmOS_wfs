import pytest

from owslib.util import Authentication
from owslib.wfs import WebFeatureService

from .bind_method import bind_method


@pytest.fixture(scope="class")
def farmos_asset_helpers(request):
    cls = request.cls

    @bind_method(cls)
    def get_asset_by_type_and_id(self, asset_type, asset_id):
        self.assertIsInstance(asset_id, str)

        with self.requests_session() as s:
            asset_response = s.get(
                "http://www/api/asset/{}/{}".format(asset_type, asset_id))

        self.assertTrue(asset_response.ok)

        return asset_response.json()['data']

    @bind_method(cls)
    def assert_asset_does_not_exist(self, asset_type, asset_id):
        self.assertIsInstance(asset_id, str)

        with self.requests_session() as s:
            asset_response = s.get(
                "http://www/api/asset/{}/{}".format(asset_type, asset_id))

        self.assertEqual(asset_response.status_code, 404)

    @bind_method(cls)
    def create_asset(self, asset_type, asset_attributes):
        with self.requests_session() as s:
            create_response = s.post(
                'http://www/api/asset/{}'.format(asset_type), json={
                    "data": {
                        "type": "asset--" + asset_type,
                        "attributes": asset_attributes,
                    },
                }, headers={'content-type': 'application/vnd.api+json'})

            self.assertTrue(create_response.ok)

            return create_response.json()['data']['id']
