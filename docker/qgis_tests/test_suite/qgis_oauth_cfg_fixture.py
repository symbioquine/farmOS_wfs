import json

import pytest

from qgis.core import QgsAuthMethodConfig, QgsApplication

from .farmos_constants import *


@pytest.fixture(scope="class")
def qgis_oauth_cfg(request):
    cls = request.cls

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
