<?php

/**
 * @file
 * functions used to create Datacite DOIs
 */
define('METADATA_URI', 'mds.datacite.org/metadata');
define('DOI_URI', 'mds.datacite.org/doi');

class DataciteDoi {

  public $prefix;
  public $site;
  public $pid;
  public $testMode = FALSE;
  public $dataciteUser;
  public $datacitePass;

  /**
   * Instantiate an DataciteDoi object.
   *
   * @param string    *   The absolute path to the HOCR file.
   *
   * @throws InvalidArgumentException
   */
  public function __construct($prefix, $site, $pid, $user, $pass) {
    $this->prefix = $prefix;
    $this->site = $site;
    $this->pid = $pid;
    $this->dataciteUser = $user;
    $this->datacitePass = $pass;
  }

  /**
   * Create a DOI following UPEI conventions (prefix/site/pid).
   *
   * @param string pid
   *   the pid of an islandora object
   *
   * @return string
   *   return the doi 
   */
  function getDoi() {
    return "$this->prefix/$this->site/$this->pid";
  }

  /**
   * Transform the MetaData XML to Datacite xml.
   *
   * @param string $xml
   *   The ddi xml as a string.
   * @param string $doi
   *   The doi to register
   * @param string $xsltFileName
   *   The xslt file name.  This file must exist in the xslt directory.
   *
   * @throws Exception 
   *   Throws an Exception if the transform fails.
   */
  function transformMetadataToDacite($xml, $doi, $xsltFileName) {
    $path = drupal_get_path('module', 'islandora_datacite_doi') . '/xslt';
    $xslt_path = "$path/$xsltFileName";
    $datacite_path = tempnam(NULL, 'datacite');
    $input_file = tempnam(NULL, 'datacite_input');
    file_put_contents($input_file, $xml);
    $saxon_path = variable_get('islandora_datacite_doi_saxon_path', '/opt/saxon/saxon9he.jar');
    // Xslt is version 2 so use saxon to tranform
    $command = "java -jar $saxon_path -s:$input_file -xsl:$xslt_path -o:$datacite_path doi=$doi";
    $return_value = 0;
    exec($command, $output, $return_value);
    if (!empty($return_value)) {
      unlink($datacite_path);
      return NULL;
    }
    $datacite_xml = file_get_contents($datacite_path);
    unlink($datacite_path);
    return $datacite_xml;
  }

  /**
   * Sends the datacite xml to register the metadata for the new DOI.  If the
   * DOI exists it will update the metadata.
   *
   * @param string $datacite_xml
   *   The datacite xml
   *
   * @return array|null
   *   array on success or NULL on failure
   */
  function sendXmlToDatacite($datacite_xml) {
    $options = array(
      'method' => 'POST',
      'data' => $datacite_xml,
      'timeout' => 15,
      'headers' => array('Content-Type' => 'application/xml'),
      'testMode' => $this->testMode,
    );
    $result = drupal_http_request("https://$this->dataciteUser:$this->datacitePass@" . METADATA_URI, $options);
    return $result;
  }

  /**
   * Send the doi and url to datacite to actually mint the new doi.  If the 
   * DOI exists it will attempt to update the url for the existing DOI.
   *
   * @param string $url
   *   The url to associate with the doi.
   * @return array
   *   array on success null on failure
   */
  function sendDoiToDatacite($url) {
    $data = sprintf("doi=%s\nurl=%s", $this->getDoi(), $url);
    $options = array(
      'method' => 'POST',
      'data' => $data,
      'timeout' => 15,
      'headers' => array('Content-Type' => 'text/plain;charset=UTF-8'),
      'testMode' => $this->testMode,
    );
    $result = drupal_http_request("https://$this->dataciteUser:$this->datacitePass@" . DOI_URI, $options);
    return $result;
  }

  /**
   * Send a DELETE request to datacite.
   * 
   * This really only marks the doi as inactive.  If we later POST new metadata
   * for this DOI it will become active again.
   *
   * @return array
   *   array on success null on failure
   */
  function deleteDataciteDoi() {
    $options = array(
      'method' => 'DELETE',
      'timeout' => 15,
      'headers' => array('Content-Type' => 'text/plain;charset=UTF-8'),
      'testMode' => $this->testMode,
    );
    $result = drupal_http_request("https://$this->dataciteUser:$this->datacitePass@" .
        METADATA_URI . '/' . $this->getDoi(), $options);
    return $result;
  }

  /**
   * Update the metadata xml with the DOI.
   * 
   * @param string $doi
   *   The DOI
   * @param string $xml
   *   The XML
   * @param string $dsid
   *   The objects dsid
   * @return string
   *   The update xml
   * 
   * @throws Exception
   *   throws an exceptioin if the type of metadata is not supported.
   */
  public static function updateMetaData($doi, $xml, $dsid) {
    $returnValue = -1;
    switch ($dsid) {
      case 'DDI':
        $returnValue = self::updateDDI($doi, $xml);
        break;
      case 'MODS':
        $returnValue = self::updateMODS($doi, $xml);
        break;
      default:
        throw new Exception("Cannot update metadata,  $dsid is not supported.");
    }
    return $returnValue;
  }

  /**
   * Update the DDI xml with the doi so we can send it back to Fedora, Also adds a DDI
   * LifecycleEvent.
   *
   * @param string $doi
   *   The doi.
   * @param string $ddi
   *   The ddi xml
   *
   * @return int|string
   *   the updated ddi xml on success or an int on failure
   */
  public static function updateDDI($doi, $ddi) {
    $xml = new DOMDocument();
    $test = $xml->loadXML($ddi);
    if (empty($test)) {
      return -1;
    }
    $query = "//r:Citation/r:InternationalIdentifier[r:ManagingAgency/text() = 'Datacite']/r:IdentifierContent";
    $xpath = new DOMXPath($xml);
    //$xpath->registerNamespace('s','ddi:studyunit:3_2');
    $xpath->registerNamespace('r', 'ddi:reusable:3_2');
    $results = $xpath->query($query, $xml);
    $found = FALSE;
    foreach ($results as $element) {
      $found = TRUE;
      $element->nodeValue = $doi;
    }
    if (!$found) {
      // We need to add the new identifier
      $study_unit = $xml->getElementsByTagName('StudyUnit');
      $citation = $study_unit->item(0)->getElementsbyTagName('Citation');
      $internationalIdentifier = $xml->createElementNS("ddi:reusable:3_2", "r:InternationalIdentifier");
      $copyright = $citation->item(0)->getElementsbyTagName('Copyright');
      if ($copyright->length > 0) {
        $citation->item(0)->insertBefore($internationalIdentifier, $copyright->item(0));
      }
      else {
        $citation->item(0)->appendChild($internationalIdentifier);
      }
      $managingAgency = $xml->createElementNS("ddi:reusable:3_2", "r:ManagingAgency", "Datacite");
      $identifierContent = $xml->createElementNS("ddi:reusable:3_2", "r:IdentifierContent", $doi);
      $internationalIdentifier->appendChild($identifierContent);
      $internationalIdentifier->appendChild($managingAgency);
    }
    return $xml->saveXML();
  }

  /**
   * Update the MODS xml with the doi so we can send it back to Fedora.
   *
   * @param string $doi
   *   The doi.
   * @param string $mods
   *   The MODS xml
   *
   * @return int|string
   *   the updated ddi xml on success or an int on failure
   */
  public static function updateMODS($doi, $mods) {
    $xml = new DOMDocument();
    $test = $xml->loadXML($mods);
    if (empty($test)) {
      return -1;
    }
    $query = "//mods:identifier[@type='doi']";
    $xpath = new DOMXPath($xml);
    $xpath->registerNamespace('mods', 'http://www.loc.gov/mods/v3');
    $results = $xpath->query($query, $xml);
    $found = FALSE;
    foreach ($results as $element) {
      $found = TRUE;
      $element->nodeValue = $doi;
    }
    if (!$found) {
      // We need to add the new identifier
      $mods = $xml->getElementsByTagName('mods');
      $identifier = $xml->createElement("mods:identifier", $doi);
      $identifier->setAttribute('type', 'doi');
      $mods->item(0)->appendChild($identifier);
    }
    return $xml->saveXML();
  }

  /**
   * Adds a DDI lifecycleinformation element to an existing DDI doc.
   * 
   * Exists here so we can add an event when we mint a DOI
   * 
   * @param object/string $xml_doc
   *   DomDocument or an xml string
   * @param array $values
   *   this array must have certain keys
   *   @code
   *    $arr['agency'] = 'value';
   *    $arr['id'] = 'value';
   *    $arr['version']  = 'value';
   *    $arr['type'] = 'value';
   *    $arr['content']  = 'value';
   *   @endcode
   *   See the DDI spec for lifecycleInformation
   * @return object
   *   DomDocument
   */
  public static function addDDIEvent($xml_doc, $values) {
    if (is_string($xml_doc)) {
      $dom = new DOMDocument();
      $test = $dom->loadXML($xml_doc);
      $xml_doc = $dom;
    }
    $arg_count = extract($values, EXTR_SKIP);
    if ($arg_count < 5) {
      return NULL;
    }
    $study_unit = $xml_doc->getElementsByTagName('StudyUnit');
    $archive = $study_unit->item(0)->getElementsbyTagName('Archive');
    $lifecycle_information_nodes = $archive->item(0)->getElementsbyTagName('LifecycleInformation');
    if ($lifecycle_information_nodes->length > 0) {
      $lifecycle_information = $lifecycle_information_nodes->item(0);
    }
    else {
      $lifecycle_information = $xml_doc->createElementNS("ddi:reusable:3_2", "r:LifecycleInformation");
      $otherMaterial = $archive->item(0)->getElementsbyTagName('OtherMaterial');
      if ($otherMaterial->length > 0) {
        $archive->item(0)->insertBefore($lifecycle_information, $otherMaterial->item(0));
      }
      else {
        $archive->item(0)->appendChild($lifecycle_information);
      }
    }
    $lifecycle_event = $xml_doc->createElementNS("ddi:reusable:3_2", "r:LifecycleEvent");
    $lifecycle_information->appendChild($lifecycle_event);

    $ddi_agency = $xml_doc->createElementNS("ddi:reusable:3_2", "r:Agency", $agency);
    $lifecycle_event->appendChild($ddi_agency);

    $ddi_id = $xml_doc->createElementNS("ddi:reusable:3_2", "r:ID", $id);
    $lifecycle_event->appendChild($ddi_id);

    $version = $xml_doc->createElementNS("ddi:reusable:3_2", "r:Version", $version);
    $lifecycle_event->appendChild($version);

    $event_type = $xml_doc->createElementNS("ddi:reusable:3_2", "r:EventType", $type);
    $lifecycle_event->appendChild($event_type);

    $date = $xml_doc->createElementNS("ddi:reusable:3_2", "r:Date");
    $simple_date = $xml_doc->createElementNS("ddi:reusable:3_2", "r:SimpleDate", date('Y-m-d'));
    $date->appendChild($simple_date);
    $lifecycle_event->appendChild($date);

    $description = $xml_doc->createElementNS("ddi:reusable:3_2", "r:Description");
    $content_element = $xml_doc->createElementNS("ddi:reusable:3_2", "r:Content", $content);
    $description->appendChild($content_element);
    $lifecycle_event->appendChild($description);
    return $xml_doc;
  }

}
