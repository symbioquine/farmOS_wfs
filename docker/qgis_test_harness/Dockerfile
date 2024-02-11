FROM  qgis/qgis:release-3_34
RUN DEBIAN_FRONTEND=noninteractive apt-get install -y expect xmlstarlet
RUN pip3 install pep8 pexpect capturer sphinx requests future six pytest
RUN pip3 install requests_oauthlib git+https://github.com/symbioquine/OWSLib.git@add-authbase-delegate-support
ENV DISPLAY=:99
ENV PYTHONUNBUFFERED=1
ENV QGIS_NO_OVERRIDE_IMPORT=1
WORKDIR /tests_directory
