<?php

namespace Drupal\farm_wfs\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\State\State;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines FarmWfsController class.
 */
class FarmWfsController extends ControllerBase {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The state store.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * Constructs a new FarmWfsController object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\State\State $state
   *   The object State.
   */
  public function __construct(RequestStack $requestStack, State $state) {
    $this->requestStack = $requestStack;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('request_stack'), $container->get('state'));
  }

  /**
   * Top-level handler for WFS requests.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The XML encoded WFS Response.
   */
  public function content() {
    $host = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();

    $site_name = Xss::filterAdmin($this->state->get('site_name', 'farmOS'));

    $crs = "EPSG:4326";

    $response = new Response();
    $response->headers->set('Content-Type', 'application/xml');
    $response->setContent(<<<XML
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
        version="1.1.0"
        updateSequence="0">
    
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
                   <ows:Post xlink:href="$host/wfs"/>
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
                   <ows:Post xlink:href="$host/wfs"/>
                </ows:HTTP>
             </ows:DCP>
             <ows:Parameter name="resultType">
                <ows:Value>results</ows:Value>
                <ows:Value>hits</ows:Value>
             </ows:Parameter>
             <ows:Parameter name="outputFormat">
                <ows:Value>text/xml; subtype=gml/3.1.1</ows:Value>
             </ows:Parameter>
          </ows:Operation>
    
          <ows:Operation name="Transaction">
             <ows:DCP>
                <ows:HTTP>
                   <ows:Post xlink:href="$host/wfs"/>
                </ows:HTTP>
             </ows:DCP>
             <ows:Parameter name="inputFormat">
                <ows:Value>text/xml; subtype=gml/3.1.1</ows:Value>
             </ows:Parameter>
             <ows:Parameter name="idgen">
                <ows:Value>GenerateNew</ows:Value>
                <ows:Value>UseExisting</ows:Value>
                <ows:Value>ReplaceDuplicate</ows:Value>
             </ows:Parameter>
          </ows:Operation>
    
          <ows:Parameter name="srsName">
             <ows:Value>$crs</ows:Value>
          </ows:Parameter>
    
          <ows:Constraint name="DefaultMaxFeatures">
             <ows:Value>10000</ows:Value>
          </ows:Constraint>
    
       </ows:OperationsMetadata>
    
       <!-- ================================================================== -->
       <!--    FEATURE TYPE LIST SECTION                                       -->
       <!-- ================================================================== -->
       <wfs:FeatureTypeList>
    
          <wfs:Operations>
             <wfs:Operation>Query</wfs:Operation>
          </wfs:Operations>
    
          <wfs:FeatureType>
             <wfs:Name>farmos:PointAreaType</wfs:Name>
             <wfs:Title>farmOS Point Area Feature</wfs:Title>
             <wfs:Abstract>
                Point area features in farmOS.
             </wfs:Abstract>
             <wfs:DefaultSRS>$crs</wfs:DefaultSRS>
             <wfs:OutputFormats>
                <wfs:Format>text/xml; subtype=gml/3.1.1</wfs:Format>
             </wfs:OutputFormats>
            <wfs:Operations>
               <wfs:Operation>Insert</wfs:Operation>
               <wfs:Operation>Update</wfs:Operation>
               <wfs:Operation>Delete</wfs:Operation>
            </wfs:Operations>
          </wfs:FeatureType>
    
          <wfs:FeatureType>
             <wfs:Name>farmos:PolygonAreaType</wfs:Name>
             <wfs:Title>farmOS Polygon Area Feature</wfs:Title>
             <wfs:Abstract>
                Polygon area features in farmOS.
             </wfs:Abstract>
             <wfs:DefaultSRS>$crs</wfs:DefaultSRS>
             <wfs:OutputFormats>
                <wfs:Format>text/xml; subtype=gml/3.1.1</wfs:Format>
             </wfs:OutputFormats>
            <wfs:Operations>
               <wfs:Operation>Insert</wfs:Operation>
               <wfs:Operation>Update</wfs:Operation>
               <wfs:Operation>Delete</wfs:Operation>
            </wfs:Operations>
          </wfs:FeatureType>
    
          <wfs:FeatureType>
             <wfs:Name>farmos:LineStringAreaType</wfs:Name>
             <wfs:Title>farmOS Line String Area Feature</wfs:Title>
             <wfs:Abstract>
                Line string area features in farmOS.
             </wfs:Abstract>
             <wfs:DefaultSRS>$crs</wfs:DefaultSRS>
             <wfs:OutputFormats>
                <wfs:Format>text/xml; subtype=gml/3.1.1</wfs:Format>
             </wfs:OutputFormats>
            <wfs:Operations>
               <wfs:Operation>Insert</wfs:Operation>
               <wfs:Operation>Update</wfs:Operation>
               <wfs:Operation>Delete</wfs:Operation>
            </wfs:Operations>
          </wfs:FeatureType>
    
       </wfs:FeatureTypeList>
    
    </wfs:WFS_Capabilities>
    XML);

    return $response;
  }

}
