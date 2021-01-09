# farmOS_wfs

farmOS_wfs provides a WFS module for farmOS. This makes FarmOS areas accessible as a [Web Feature Service (WFS)](https://www.opengeospatial.org/standards/wfs)
which can be used in [GIS software](https://en.wikipedia.org/wiki/Geographic_information_system) such as [Quantum GIS](https://qgis.org) (QGIS).

## Limitations & Compatibility

* Only supports WFS 1.1.0 / GML 3.1.1 currently
* Only supports features with single geometries of the types; point, polygon, or line string
* Only supports querying/updating/deleting by simple filters on BBOX or feature id - more complex OGC Filter operations may be supported in the future
* Only supports the [EPSG:4326](https://epsg.io/4326) spatial reference system (SRS) which farmOS uses - QGIS and similar software generally supports reprojection of data sources into other SRS'
* Only supports PHP >= 7.4 - earlier versions will not work
* Only tested against the farmOS 2.x branch - for farmOS 1.x see [farmOS_wfs-7.x-1.x](https://github.com/symbioquine/farmOS_wfs/tree/7.x-1.x)
* Only tested with QGIS 3.16 - earlier versions may work, but no promises

## Getting Started

Use Composer and Drush to install farmOS_wfs in farmOS 2.x;

```sh
composer require symbioquine/farmos_wfs
drush en farmos_wfs
```

*Available released versions can be viewed at https://packagist.org/packages/symbioquine/farmos_wfs*

### QGIS Configuration

#### Configure OAuth2

* Name: `farmOS0-oauth2-resource-owner` (Name as desired)
* Grant Flow: Resource Owner
* Token URL: `https://path-to-your-farmOS-server.example.com/oauth/token`
* Client ID: `farm`
* Username: `your-farmOS-username`
* Password: `your-farmOS-username`
* Scope: `user_access`

![image](https://user-images.githubusercontent.com/30754460/104083652-44521700-51f5-11eb-9e32-0d6dd3d3db2e.png)

#### Configure WFS Server Connection

* Name: `FarmOS0` (Name as desired)
* URL: `https://path-to-your-farmOS-server.example.com/wfs`
* Authentication Config: Choose the OAuth2 configuration created above

![image](https://user-images.githubusercontent.com/30754460/104083679-7bc0c380-51f5-11eb-8200-38c225281212.png)

#### Add Layers

* Click 'Connect' and add the desired layers to your map!

![image](https://user-images.githubusercontent.com/30754460/103485307-4c035d00-4daa-11eb-851f-075d8e918344.png)

## Development Environment

In the `docker/` directory of this repository run;

```sh
cp docker-compose.pgsql.yml docker-compose.yml
docker-compose up -d
```

Once the command completes, farmOS should be running at [http://localhost:80](http://localhost:80) with the farmOS_wfs module installed. The test site's username and password are 'root' and 'test' respectively.


## Running Tests

In the `docker/` directory with the above development environment started;

```sh
docker build -t qgis_test_harness qgis_test_harness
docker run --rm -it --name qgis --network=docker_default -v $(pwd)'/qgis_tests:/tests_directory' qgis_test_harness:latest ./run_tests.sh
```

### More Complex Test Filtering

**Running a single test:**

```sh
docker run --rm -it --name qgis --network=docker_default -v $(pwd)'/qgis_tests:/tests_directory' qgis_test_harness:latest ./run_tests.sh test_suite/test_cases/qgis_basic_crud_test.py::QgisBasicCrudTest::test_qgis_create_line_string_water_asset
```

**Running all 'non-point' qgis tests:**

```sh
docker run --rm -i --name qgis --network=docker_default -v $(pwd)'/qgis_tests:/tests_directory' qgis_test_harness:latest ./run_tests.sh -k 'qgis and not point'
```

*Arguments are passed as-is to pytest. See https://docs.pytest.org/en/stable/usage.html#specifying-tests-selecting-tests for more information.*

## Formatting tests

```sh
autopep8 --in-place --recursive docker/qgis_tests/
```
