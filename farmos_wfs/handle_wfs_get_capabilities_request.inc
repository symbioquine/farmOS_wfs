<?php

/**
 * @file
 * handle_wfs_get_capabilities_request.inc
 */

function handle_wfs_get_capabilities_request($query_params) {
  global $base_url;

  $host = $base_url;

  $site_name = filter_xss_admin(variable_get('site_name', 'farmOS'));

  $crs = FARMOS_WFS_DEFAULT_CRS;

  print <<<XML
  <?xml version="1.0" encoding="UTF-8"?>
  <wfs:WFS_Capabilities
      xmlns:ows="http://www.opengis.net/ows"
      xmlns:ogc="http://www.opengis.net/ogc"
      xmlns:wfs="http://www.opengis.net/wfs"
      xmlns:gml="http://www.opengis.net/gml"
      xmlns:xlink="http://www.w3.org/1999/xlink"
      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xmlns:farmos="https://farmos.org/wfs"
      xsi:schemaLocation="http://www.opengis.net/wfs ../wfs.xsd"
      version="1.1.0">
  
     <!-- ================================================================== -->
     <!--    SERVICE IDENTIFICATION SECTION                                  -->
     <!-- ================================================================== -->
     <ows:ServiceIdentification>
        <ows:Title>farmOS OGC WFS API</ows:Title>
        <ows:Abstract>
           Web Feature Service for farmOS
        </ows:Abstract>
        <ows:Keywords>
           <ows:Keyword>farmOS</ows:Keyword>
           <ows:Type>String</ows:Type>
        </ows:Keywords>
        <ows:ServiceType>WFS</ows:ServiceType>
        <ows:ServiceTypeVersion>1.1.0</ows:ServiceTypeVersion>
        <ows:Fees>None</ows:Fees>
        <ows:AccessConstraints>None</ows:AccessConstraints>
     </ows:ServiceIdentification>
  
     <!-- ================================================================== -->
     <!--    SERVICE PROVIDER SECTION                                        -->
     <!-- ================================================================== -->
     <ows:ServiceProvider>
        <ows:ProviderName>$site_name</ows:ProviderName>
        <ows:ProviderSite xlink:href="$host"/>
     </ows:ServiceProvider>
  
     <!-- ================================================================== -->
     <!--    OPERATIONS METADATA SECTION                                     -->
     <!-- ================================================================== -->
     <ows:OperationsMetadata>
  
        <ows:Operation name="GetCapabilities">
           <ows:DCP>
              <ows:HTTP>
                 <ows:Get xlink:href="$host/wfs"/>
              </ows:HTTP>
           </ows:DCP>
           <ows:Parameter name="AcceptVersions">
              <ows:Value>1.1.0</ows:Value>
           </ows:Parameter>
           <ows:Parameter name="AcceptFormats">
              <ows:Value>text/xml</ows:Value>
           </ows:Parameter>
           <ows:Parameter name="Sections">
              <ows:Value>ServiceIdentification</ows:Value>
              <ows:Value>ServiceProvider</ows:Value>
              <ows:Value>OperationsMetadata</ows:Value>
              <ows:Value>FeatureTypeList</ows:Value>
              <ows:Value>Filter_Capabilities</ows:Value>
           </ows:Parameter>
        </ows:Operation>
  
        <ows:Operation name="DescribeFeatureType">
           <ows:DCP>
              <ows:HTTP>
                 <ows:Get xlink:href="$host/wfs"/>
              </ows:HTTP>
           </ows:DCP>
           <ows:Parameter name="outputFormat">
              <ows:Value>text/xml; subtype=gml/3.1.1</ows:Value>
           </ows:Parameter>
        </ows:Operation>
  
        <ows:Operation name="GetFeature">
           <ows:DCP>
              <ows:HTTP>
                 <ows:Get xlink:href="$host/wfs"/>
              </ows:HTTP>
           </ows:DCP>
           <ows:Parameter name="outputFormat">
              <ows:Value>text/xml; subtype=gml/3.1.1</ows:Value>
           </ows:Parameter>
        </ows:Operation>
  
     </ows:OperationsMetadata>
  
     <!-- ================================================================== -->
     <!--    FEATURE TYPE LIST SECTION                                       -->
     <!-- ================================================================== -->
     <wfs:FeatureTypeList>
  
        <wfs:Operations>
           <wfs:Operation>Query</wfs:Operation>
        </wfs:Operations>
  
        <wfs:FeatureType>
           <wfs:Name>farmos:PointArea</wfs:Name>
           <wfs:Title>farmOS Point Area Feature</wfs:Title>
           <wfs:Abstract>
              Point area features in farmOS.
           </wfs:Abstract>
           <wfs:DefaultSRS>$crs</wfs:DefaultSRS>
           <wfs:OutputFormats>
              <wfs:Format>text/xml; subtype=gml/3.1.1</wfs:Format>
           </wfs:OutputFormats>
        </wfs:FeatureType>
  
        <wfs:FeatureType>
           <wfs:Name>farmos:PolygonArea</wfs:Name>
           <wfs:Title>farmOS Polygon Area Feature</wfs:Title>
           <wfs:Abstract>
              Polygon area features in farmOS.
           </wfs:Abstract>
           <wfs:DefaultSRS>$crs</wfs:DefaultSRS>
           <wfs:OutputFormats>
              <wfs:Format>text/xml; subtype=gml/3.1.1</wfs:Format>
           </wfs:OutputFormats>
        </wfs:FeatureType>
  
        <wfs:FeatureType>
           <wfs:Name>farmos:LineStringArea</wfs:Name>
           <wfs:Title>farmOS Line String Area Feature</wfs:Title>
           <wfs:Abstract>
              Line string area features in farmOS.
           </wfs:Abstract>
           <wfs:DefaultSRS>$crs</wfs:DefaultSRS>
           <wfs:OutputFormats>
              <wfs:Format>text/xml; subtype=gml/3.1.1</wfs:Format>
           </wfs:OutputFormats>
        </wfs:FeatureType>
  
     </wfs:FeatureTypeList>
  
     <ogc:Filter_Capabilities>
       <ogc:Spatial_Capabilities>
         <ogc:SpatialOperators>
           <ogc:SpatialOperator name="BBOX"/>
         </ogc:SpatialOperators>
       </ogc:Spatial_Capabilities>
     </ogc:Filter_Capabilities>
  
  </wfs:WFS_Capabilities>
  XML;

  drupal_exit();
}
