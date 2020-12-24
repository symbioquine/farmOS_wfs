# farmOS_wfs

farmOS_wfs provides a WFS module for farmOS. This makes FarmOS areas accessible as a [Web Feature Service (WFS)](https://www.opengeospatial.org/standards/wfs)
which can be used in [GIS software](https://en.wikipedia.org/wiki/Geographic_information_system) such as [Quantum GIS](https://qgis.org) (QGIS).

## Limitations & Compatibility

* Only supports WFS 1.1.0 / GML 3.1.1 currently
* Only supports features with single geometries of the types; point, polygon, or line string
* Only supports the area id, name, type, and description fields
* Only supports querying/updating/deleting by simple filters on BBOX or feature id - more complex OGC Filter operations may be supported in the future
* Only supports the [EPSG:4326](https://epsg.io/4326) spatial reference system (SRS) which farmOS uses - QGIS and similar software generally supports reprojection of data sources into other SRS'
* Only supports PHP >= 7.4 - earlier versions will not work
* Only tested against farmOS 7.x-1.6 - earlier versions may work, but no promises
* Only tested with QGIS 3.16 - earlier versions may work, but no promises

## Getting Started

Download the latest release from https://github.com/symbioquine/farmOS_wfs/releases to install in your farmOS instance.

### QGIS Configuration

#### Configure OAuth2

* Name: `farmOS0-oauth2-resource-owner` (Name as desired)
* Grant Flow: Resource Owner
* Token URL: `https://path-to-your-server.example.com/oauth/token`
* Client ID: `farm`
* Username: `your-farm-os-username`
* Password: `your-farm-os-username`
* Scope: `user_access`

![image](https://user-images.githubusercontent.com/30754460/103063679-e66fcf00-4566-11eb-9bc8-cddb19c1067c.png)

#### Configure WFS Server Connection

* Name: `FarmOS0` (Name as desired)
* URL: `https://path-to-your-server.example.com/wfs`
* Authentication Config: Choose the OAuth2 configuration created above

![image](https://user-images.githubusercontent.com/30754460/103063809-41092b00-4567-11eb-939f-c02b1cc79b9b.png)

#### Add Layers

* Click 'Connect' and add the desired layers to your map!

![image](https://user-images.githubusercontent.com/30754460/103063952-947b7900-4567-11eb-87e2-de1b472f861b.png)

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

## Formatting tests

```sh
autopep8 --in-place --recursive docker/qgis_tests/
```
