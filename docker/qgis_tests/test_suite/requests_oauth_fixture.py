from contextlib import contextmanager

import pytest
import requests

from oauthlib.oauth2 import LegacyApplicationClient
from requests_oauthlib import OAuth2Session, OAuth2

from .bind_method import bind_method
from .farmos_constants import *


@pytest.fixture(scope="class")
def requests_oauth(request):
    cls = request.cls

    oauth_client = LegacyApplicationClient(client_id=OAUTH_CLIENT_ID)

    oauth_session = OAuth2Session(client=oauth_client)

    token = oauth_session.fetch_token(token_url=OAUTH_ENDPOINT,
                                      username=OAUTH_USERNAME, password=OAUTH_PASSWORD, client_id=OAUTH_CLIENT_ID, scope=OAUTH_SCOPE)

    cls.requests_oauth2 = OAuth2(client=LegacyApplicationClient(
        client_id=OAUTH_CLIENT_ID), token=token)

    @bind_method(cls)
    @contextmanager
    def requests_session(self):
        with requests.Session() as s:
            s.auth = self.requests_oauth2
            yield s

    yield

    oauth_session.close()
