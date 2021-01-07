#!/bin/bash

# Based on https://github.com/qgis/QGIS/blob/14a60c1c8d18a9547f5d666ef1513d0a817be0c5/.docker/qgis_resources/test_runner/qgis_testrunner.sh#L1
# With some differences;
# - Eliminates use of 'unbuffered' since that was somehow breaking the stderr redirection
# - Starts Xvfb before running the tests
# - Parses test success/failure information from a JUnit XML file

/usr/bin/Xvfb :99 -screen 0 1024x768x24 -ac +extension GLX +render -noreset -nolisten tcp &

TEST_NAME=$1

LOGFILE=/tmp/qgis_testrunner_$$

TEST_RESULTS_FILE=/tmp/qgis_tests_results_$$.xml

QGIS_TEST_MODULE=${TEST_NAME} TEST_RESULTS_FILE="$TEST_RESULTS_FILE" qgis --version-migration --nologo --code /usr/bin/qgis_testrunner.py $1 2>/dev/null | tee ${LOGFILE}

# NOTE: EXIT_CODE will always be 0 if "tee" works,
#       we could `set -o pipefail` to change this
EXIT_CODE="$?"
OUTPUT=$(cat $LOGFILE) # quick hack to avoid changing too many lines
if [ -z "$OUTPUT" ]; then
    echo "ERROR: no output from the test runner! (exit code: ${EXIT_CODE})"
    exit 1
fi

NUMBER_OF_TESTS=$(xmlstarlet sel -t -v "//testsuites/testsuite/@tests" $TEST_RESULTS_FILE)
NUMBER_OF_ERRORS=$(xmlstarlet sel -t -v "//testsuites/testsuite/@errors" $TEST_RESULTS_FILE)
NUMBER_OF_FAILURES=$(xmlstarlet sel -t -v "//testsuites/testsuite/@failures" $TEST_RESULTS_FILE)
NUMBER_OF_SKIPPED=$(xmlstarlet sel -t -v "//testsuites/testsuite/@skipped" $TEST_RESULTS_FILE)

echo "$OUTPUT" | grep -qv "QGIS died on signal"
IS_DEAD="$?"

echo "Finished running $NUMBER_OF_TESTS tests (codes: IS_DEAD=$IS_DEAD NUMBER_OF_ERRORS=$NUMBER_OF_ERRORS NUMBER_OF_FAILURES=$NUMBER_OF_FAILURES NUMBER_OF_SKIPPED=$NUMBER_OF_SKIPPED)."
if [ "$NUMBER_OF_ERRORS" -eq "0" ] && [ "$NUMBER_OF_FAILURES" -eq "0" ] && [ "$IS_DEAD" -eq "0" ]; then
    exit 0;
fi
exit 1
