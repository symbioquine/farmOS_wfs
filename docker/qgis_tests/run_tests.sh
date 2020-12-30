#!/bin/bash

# Based on https://github.com/qgis/QGIS/blob/14a60c1c8d18a9547f5d666ef1513d0a817be0c5/.docker/qgis_resources/test_runner/qgis_testrunner.sh#L1
# With two main differences;
# - Eliminates use of 'unbuffered' since that was somehow breaking the stderr redirection
# - Starts Xvfb before running the tests

/usr/bin/Xvfb :99 -screen 0 1024x768x24 -ac +extension GLX +render -noreset -nolisten tcp &

TEST_NAME=$1

LOGFILE=/tmp/qgis_testrunner_$$

QGIS_TEST_MODULE=${TEST_NAME} qgis --version-migration --nologo --code /usr/bin/qgis_testrunner.py $1 2>/dev/null | tee ${LOGFILE}

# NOTE: EXIT_CODE will always be 0 if "tee" works,
#       we could `set -o pipefail` to change this
EXIT_CODE="$?"
OUTPUT=$(cat $LOGFILE) # quick hack to avoid changing too many lines
if [ -z "$OUTPUT" ]; then
    echo "ERROR: no output from the test runner! (exit code: ${EXIT_CODE})"
    exit 1
fi
echo "$OUTPUT" | grep -q 'FAILED'
IS_FAILED="$?"
echo "$OUTPUT" | grep -q 'OK' && echo "$OUTPUT" | grep -q 'Ran'
IS_PASSED="$?"
echo "$OUTPUT" | grep "QGIS died on signal"
IS_DEAD="$?"
echo "Finished running test $1 (codes: IS_DEAD=$IS_DEAD IS_FAILED=$IS_FAILED IS_PASSED=$IS_PASSED)."
if [ "$IS_PASSED" -eq "0" ] && [ "$IS_FAILED" -eq "1" ] && [ "$IS_DEAD" -eq "1" ]; then
    exit 0;
fi
exit 1
