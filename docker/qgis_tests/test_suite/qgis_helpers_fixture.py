from urllib.parse import urlencode

import pytest

from qgis.core import QgsVectorLayer

from .farmos_constants import *


@pytest.fixture(scope="class")
def qgis_helpers(request):
    cls = request.cls

    def get_qgis_wfs_vector_layer(self, type_name):

        vlayer = QgsVectorLayer(
            WFS_ENDPOINT + '?' + urlencode(dict(
                service='WFS',
                request='GetFeature',
                version='1.1.0',
                typename=type_name,
                authcfg=self.cfg.id(),
                # This should actually be `restrictToRequestBBOX=1` once
                # https://github.com/qgis/QGIS/issues/40826 is addressed
                bbox='1',
            ), safe=':'), type_name, "WFS")

        self.assertTrue(vlayer.isValid())

        vlayer.reload()

        return vlayer

    cls.get_qgis_wfs_vector_layer = get_qgis_wfs_vector_layer
