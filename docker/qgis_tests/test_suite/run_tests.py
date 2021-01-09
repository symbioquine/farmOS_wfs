import os
import shlex

import pytest


os.environ['OAUTHLIB_INSECURE_TRANSPORT'] = '1'

TEST_RESULTS_FILE = os.environ['TEST_RESULTS_FILE']

PYTEST_EXTRA_ARGS = os.environ.get('PYTEST_EXTRA_ARGS', '')


def run_all():
    """Default function that is called by the runner if nothing else is specified"""

    pytest_args = ['--cache-clear', '--junitxml=' + TEST_RESULTS_FILE]

    if PYTEST_EXTRA_ARGS:
        pytest_args.extend(shlex.split(PYTEST_EXTRA_ARGS))

    pytest.main(pytest_args)
