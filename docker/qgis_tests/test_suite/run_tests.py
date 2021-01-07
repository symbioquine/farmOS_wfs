
import os

import pytest


os.environ['OAUTHLIB_INSECURE_TRANSPORT'] = '1'

TEST_RESULTS_FILE = os.environ['TEST_RESULTS_FILE']


def run_all():
    """Default function that is called by the runner if nothing else is specified"""
    pytest.main(['--junitxml=' + TEST_RESULTS_FILE])
