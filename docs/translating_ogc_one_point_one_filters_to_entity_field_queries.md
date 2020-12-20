# Translating between OGC filters and farmOS area queries using `EntityFieldQuery`

This document serves to explain through a series of examples how OGC 1.1.0 filters can be translated into code leveraging `EntityFieldQuery`.

Although translating directly to SQL might be simpler, using the `EntityFieldQuery` interface decreases the likelihood of breaking compatibility with various DB engines and ensures the standard entity hooks are honored.

## Example `PropertyIsBetween` filter

```xml
<ogc:Filter>
  <ogc:PropertyIsBetween>
    <ogc:PropertyName>farmos:area_id</ogc:PropertyName>
    <ogc:LowerBoundary>
      <ogc:Literal>5000</ogc:Literal>
    </ogc:LowerBoundary>
    <ogc:UpperBoundary>
      <ogc:Literal>10000</ogc:Literal>
    </ogc:UpperBoundary>
  </ogc:PropertyIsBetween>
</ogc:Filter>
```

```php
$query = new EntityFieldQuery();
$query->entityCondition('entity_type', 'taxonomy_term')
    ->entityCondition('bundle', 'farm_areas')
    ->propertyCondition('tid', [5000, 10000], 'BETWEEN')
    ->fieldCondition('field_farm_geofield', 'geo_type', $geo_types, 'IN', 0);

$result = $query->execute();

$area_ids = array_keys($result['taxonomy_term'] ?? []);

return $area_ids;
```

## Example filter with `And` + property criteria

```xml
<ogc:Filter>
   <ogc:And>
     <ogc:PropertyIsEqualTo>
       <ogc:PropertyName>farmos:area_type</ogc:PropertyName>
       <ogc:Literal>building</ogc:Literal>
     </ogc:PropertyIsEqualTo>
     <ogc:PropertyIsLessThan>
       <ogc:PropertyName>farmos:area_id</ogc:PropertyName>
       <ogc:Literal>10000</ogc:Literal>
     </ogc:PropertyIsLessThan>
   </ogc:And>
</ogc:Filter>
```

```php
$query = new EntityFieldQuery();
$query->entityCondition('entity_type', 'taxonomy_term')
    ->entityCondition('bundle', 'farm_areas')
    ->propertyCondition('tid', 10000, '<')
    ->fieldCondition('field_farm_area_type', 'value', 'building', '=')
    ->fieldCondition('field_farm_geofield', 'geo_type', $geo_types, 'IN', 0);

$result = $query->execute();

$area_ids = array_keys($result['taxonomy_term'] ?? []);

return $area_ids;
```

## Example filter with `And` + property criteria + geometry criteria

```xml
<ogc:Filter>
   <ogc:And>
     <ogc:PropertyIsLessThan>
       <ogc:PropertyName>farmos:area_id</ogc:PropertyName>
       <ogc:Literal>10000</ogc:Literal>
     </ogc:PropertyIsLessThan>
     <ogc:DWithin>
       <ogc:PropertyName>farmos:geometry</ogc:PropertyName>
         <gml:Point srsName="EPSG:4326">
            <gml:coordinates>-105.58529223008000031 37.767069878092002</gml:coordinates>
         </gml:Point>
       <ogc:Distance>1500.0</ogc:Distance>
       <ogc:DistanceUnits>meter</ogc:DistanceUnits>
     </ogc:DWithin>
   </ogc:And>
</ogc:Filter>
```

```php
$point = new Point(-105.58529223008000031, 37.767069878092002);

$filter_geom = $point->buffer(1500);

$filter_geom_bbox = $filter_geom->getBBox();

$query = new EntityFieldQuery();
$query->entityCondition('entity_type', 'taxonomy_term')
    ->entityCondition('bundle', 'farm_areas')
    ->propertyCondition('tid', 10000, '<')
    ->fieldCondition('field_farm_geofield', 'top', $filter_geom_bbox['maxy'], '>=', 0)
    ->fieldCondition('field_farm_geofield', 'right', $filter_geom_bbox['maxx'], '>=', 0)
    ->fieldCondition('field_farm_geofield', 'bottom', $filter_geom_bbox['miny'], '<=', 0)
    ->fieldCondition('field_farm_geofield', 'left', $filter_geom_bbox['minx'], '<=', 0)
    ->fieldCondition('field_farm_geofield', 'geo_type', $geo_types, 'IN', 0);

$result = $query->execute();

$area_ids = array_keys($result['taxonomy_term'] ?? []);

$areas = entity_load('taxonomy_term', $area_ids);

geophp_load();

$geofiltered_areas = array_values(array_filter(function($area) use ($filter_geom) {
  $geofield = $area->field_farm_geofield[LANGUAGE_NONE][0];

  // Get WKT from the field. If empty, bail.
  if (empty($geofield['geom'])) {
    return false;
  }

  $wkt = $geofield['geom'];

  $geom = geoPHP::load($wkt, 'wkt');

  return $geom->within($filter_geom);
}, $areas));

return array_map(function($area) { return $area->tid; }, $geofiltered_areas);
```

## Example filter with negation of `And` + property criteria

```xml
<ogc:Filter>
  <ogc:Not>
    <ogc:And>
      <ogc:PropertyIsEqualTo>
        <ogc:PropertyName>farmos:area_type</ogc:PropertyName>
        <ogc:Literal>building</ogc:Literal>
      </ogc:PropertyIsEqualTo>
      <ogc:PropertyIsLessThan>
        <ogc:PropertyName>farmos:area_id</ogc:PropertyName>
        <ogc:Literal>10000</ogc:Literal>
      </ogc:PropertyIsLessThan>
    </ogc:And>
  </ogc:Not>
</ogc:Filter>
```

`EntityFieldQuery` doesn't provide a way to negate a compound condition, but we can apply De Morgan's law to split into two queries which we can take the union of.

```php
// Apply De Morgan's law to split into two queries which we can take the union of

$query0 = new EntityFieldQuery();
$query0->entityCondition('entity_type', 'taxonomy_term')
    ->entityCondition('bundle', 'farm_areas')
    ->fieldCondition('field_farm_area_type', 'value', 'building', '!=')
    ->fieldCondition('field_farm_geofield', 'geo_type', $geo_types, 'IN', 0);

$query1 = new EntityFieldQuery();
$query1->entityCondition('entity_type', 'taxonomy_term')
    ->entityCondition('bundle', 'farm_areas')
    ->propertyCondition('tid', 10000, '>=')
    ->fieldCondition('field_farm_geofield', 'geo_type', $geo_types, 'IN', 0);

$result0 = $query0->execute();
$area_ids0 = array_keys($result0['taxonomy_term'] ?? []);

$result1 = $query1->execute();
$area_ids1 = array_keys($result1['taxonomy_term'] ?? []);

return array_unique(array_merge($area_ids0, $area_ids1));
```

## Example filter with negation of `And` + property criteria

```xml
<ogc:Filter>
  <ogc:And>
    <ogc:PropertyIsLike wildCard="%" singleChar="_" escapeChar="\">
      <ogc:PropertyName>farmos:description</ogc:PropertyName>
      <ogc:Literal>west%</ogc:Literal>
    </ogc:PropertyIsLike>
    <ogc:Not>
      <ogc:And>
        <ogc:PropertyIsEqualTo>
          <ogc:PropertyName>farmos:area_type</ogc:PropertyName>
          <ogc:Literal>building</ogc:Literal>
        </ogc:PropertyIsEqualTo>
        <ogc:PropertyIsLessThan>
          <ogc:PropertyName>farmos:area_id</ogc:PropertyName>
          <ogc:Literal>10000</ogc:Literal>
        </ogc:PropertyIsLessThan>
      </ogc:And>
    </ogc:Not>
  </ogc:And>
</ogc:Filter>
```

We don't need De Morgan's law when there is an outer `And` since we can effectively negate the second query using a set difference.

```php
$query0 = new EntityFieldQuery();
$query0->entityCondition('entity_type', 'taxonomy_term')
    ->entityCondition('bundle', 'farm_areas')
    ->propertyCondition('description', "west%", 'LIKE')
    ->fieldCondition('field_farm_geofield', 'geo_type', $geo_types, 'IN', 0);

$query1 = new EntityFieldQuery();
$query1->entityCondition('entity_type', 'taxonomy_term')
    ->entityCondition('bundle', 'farm_areas')
    ->propertyCondition('tid', 10000, '<')
    ->fieldCondition('field_farm_area_type', 'value', 'building', '=')
    ->fieldCondition('field_farm_geofield', 'geo_type', $geo_types, 'IN', 0);

$result0 = $query0->execute();
$area_ids0 = array_keys($result0['taxonomy_term'] ?? []);

$result1 = $query1->execute();
$area_ids1 = array_keys($result1['taxonomy_term'] ?? []);

return array_diff($area_ids0, $area_ids1);
```

