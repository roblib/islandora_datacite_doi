<?php

use GuzzleHttp\Client;

namespace Drupal\islandora_datacite_doi;
use GuzzleHttp\Client;

/**
 * @file
 * functions used to create Datacite DOIs
 */
define('METADATA_URI', 'https://mds.datacite.org/metadata');
define('DOI_URI', 'https://mds.datacite.org/doi');

class DataciteDoi
{

  public $prefix;
  public $site;
  public $pid;
  public $testMode = FALSE;
  public $dataciteUser;
  public $datacitePass;
  protected $datacite_xml;
  protected $doi;

  /**
   * @var \GuzzleHttp\Client
   */
  protected $http_client;

  /**
   * Instantiate a DataciteDoi object.
   *
   * @param string    *   The absolute path to the HOCR file.
   *
   * @throws InvalidArgumentException
   */
  public function __construct($prefix, $site, $user, $pass, Client $http_client, $doi = FALSE)
  {
    $this->prefix = $prefix;
    $this->site = $site;
    $this->dataciteUser = $user;
    $this->datacitePass = $pass;
    $this->doi = $doi;
    $this->http_client = $http_client;
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
  function sendXmlToDatacite($datacite_xml = NULL) {

    if (empty($datacite_xml) && !empty($this->datacite_xml)) {
      $datacite_xml = $this->getDataciteXml();
    }
    $options = array(
      'body' => $datacite_xml,
      'timeout' => 15,
      'headers' => array('Content-Type' => 'application/xml;charset=UTF-8'),
      'testMode' => $this->testMode,
      'auth' => [$this->dataciteUser, $this->datacitePass],
    );

    $request = $this->http_client->put(METADATA_URI . '/' . (!empty($this->doi) ? $this->doi : $this->prefix), $options);
    $result = $request->getBody()->getContents();
    // Extract DOI from result, which is of the form: "OK (10.5072/MDFR-5T34)'".
    if (substr($result, 0, 4) == "OK (") {
      $this->doi = substr($result, 4, -1);
        return $this->doi;
    }
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
  public function registerDoiUrl($url) {
    $data = sprintf("doi=%s\nurl=%s\n", $this->doi, $url);
    $options = array(
      'body' => $data,
      'timeout' => 15,
      'headers' => array('Content-Type' => 'text/plain;charset=UTF-8'),
      'auth' => [$this->dataciteUser, $this->datacitePass],
    );

    $request = $this->http_client->put(DOI_URI . '/' . $this->doi, $options);
    $result = $request->getBody()->getContents();
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
      'timeout' => 15,
      'headers' => array('Content-Type' => 'text/plain;charset=UTF-8'),
      'testMode' => $this->testMode,
      'auth' => [$this->dataciteUser, $this->datacitePass],
    );

    $request = $this->http_client->delete(METADATA_URI . '/' . $this->doi, $options);
    $result = $request->getBody()->getContents();
    return $result;
  }

  public function getDataciteXml() {
    return $this->datacite_xml;
  }

  public function setDataciteXml($xml) {
    $this->datacite_xml = $xml;
  }

  public function getDoi() {
    return $this->doi;
  }

  public function setDoi(string $doi) {
    $this->doi = $doi;
  }
}