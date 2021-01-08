import pytest

from owslib.util import Authentication
from owslib.wfs import WebFeatureService

from .farmos_constants import *


@pytest.fixture(scope="class")
def owslib(request):
    cls = request.cls

    cls.owslib_auth = Authentication(auth_delegate=cls.requests_oauth2)

    cls.wfs11 = WebFeatureService(
        url=WFS_ENDPOINT, version='1.1.0', auth=cls.owslib_auth)
