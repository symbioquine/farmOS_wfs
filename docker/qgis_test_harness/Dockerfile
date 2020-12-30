FROM  qgis/qgis:release-3_16
RUN DEBIAN_FRONTEND=noninteractive apt-get install -y expect
RUN pip3 install pep8 pexpect capturer sphinx requests future six
RUN pip3 install requests_oauthlib git+https://github.com/symbioquine/OWSLib.git@add-authbase-delegate-support
ENV DISPLAY=:99
ENV PYTHONUNBUFFERED=1
WORKDIR /tests_directory
