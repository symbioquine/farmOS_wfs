# farmOS_wfs

farmOS_wfs provides a WFS module for farmOS. This makes FarmOS areas accessible as a [Web Feature Service (WFS)](https://www.opengeospatial.org/standards/wfs)
which can be used in [GIS software](https://en.wikipedia.org/wiki/Geographic_information_system) such as [Quantum GIS](https://qgis.org) (QGIS).

## Limitations

* Only supports WFS 1.1.0 / GML 3.1.1 currently
* Only supports features with single geometries of the types; point, polygon, or line string
* Only supports the area name, type, and description fields
* Only supports the [EPSG:4326](https://epsg.io/4326) spatial reference system (SRS) which farmOS uses - QGIS and similar software generally supports reprojection of data sources into other SRS'
* Only supports viewing areas - WFS transactions to create/modify areas are not yet supported
