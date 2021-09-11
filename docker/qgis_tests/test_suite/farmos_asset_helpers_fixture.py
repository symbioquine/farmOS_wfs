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
    def get_asset_ref_field_by_type_id_and_field(self, asset_type, asset_id, field_name):
        self.assertIsInstance(asset_id, str)

        with self.requests_session() as s:
            asset_response = s.get(
                "http://www/api/asset/{}/{}/{}".format(asset_type, asset_id, field_name))

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
    def create_entity(self, entity_type, entity_bundle, entity_attributes, relationships=None):
        relationships = relationships or {}

        with self.requests_session() as s:
            create_response = s.post(
                'http://www/api/{}/{}'.format(entity_type, entity_bundle), json={
                    "data": {
                        "type": '{}--{}'.format(entity_type, entity_bundle),
                        "attributes": entity_attributes,
                        "relationships": relationships,
                    },
                }, headers={'content-type': 'application/vnd.api+json'})

            create_response.raise_for_status()

            return create_response.json()['data']['id']

    @bind_method(cls)
    def create_asset(self, asset_type, asset_attributes, relationships=None):
        return self.create_entity('asset', asset_type, asset_attributes, relationships)

    @bind_method(cls)
    def create_taxonomy_term(self, term_type, term_attributes):
        return self.create_entity('taxonomy_term', term_type, term_attributes)
