# farmOS_wfs

farmOS_wfs provides a WFS module for farmOS. This makes FarmOS areas accessible as a [Web Feature Service (WFS)](https://www.opengeospatial.org/standards/wfs)
which can be used in [GIS software](https://en.wikipedia.org/wiki/Geographic_information_system) such as [Quantum GIS](https://qgis.org) (QGIS).

## Limitations & Compatibility

* Only supports WFS 1.1.0 / GML 3.1.1 currently
* Only supports features with single geometries of the types; point, polygon, or line string
* Only supports the area name, type, and description fields
* Only supports the [EPSG:4326](https://epsg.io/4326) spatial reference system (SRS) which farmOS uses - QGIS and similar software generally supports reprojection of data sources into other SRS'
* Only supports viewing areas - WFS transactions are a work in progress
* Only supports PHP >= 7.4 - earlier versions will not work
* Only tested against farmOS 7.x-1.6 - earlier versions may work, but no promises


## Development Environment

In the `docker/` directory of this repository run;

```sh
docker-compose up -d
```

Once the command completes, farmOS should be running at [http://localhost:80](http://localhost:80) with the farmOS_wfs module installed. The test site's username and password are 'root' and 'test' respectively.


## Running tests

In the `docker/` directory with the above development environment started;

```sh
docker run --rm -it --name qgis --network=docker_default -v $(pwd)'/qgis_tests:/tests_directory' qgis_test_harness:latest ./run_tests.sh test_suite.run_tests
```
