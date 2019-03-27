<?php

use GuzzleHttp\Client;

/**
 * @file
 * functions used to create Datacite DOIs
 */
define('METADATA_URI', 'mds.datacite.org/metadata');
define('DOI_URI', 'mds.datacite.org/doi');

class DataciteDoi
{

  public $prefix;
  public $site;
  public $pid;
  public $testMode = FALSE;
  public $dataciteUser;
  public $datacitePass;

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
  public function __construct($prefix, $site, $pid, $user, $pass, Client $http_client)
  {
    $this->prefix = $prefix;
    $this->site = $site;
    $this->pid = $pid;
    $this->dataciteUser = $user;
    $this->datacitePass = $pass;
    $this->guzzle = $http_client;
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
  function getDoi()
  {
    return "$this->prefix/$this->site/$this->pid";
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
  function sendXmlToDatacite($datacite_xml)
  {
    $options = array(
      'method' => 'POST',
      'data' => $datacite_xml,
      'timeout' => 15,
      'headers' => array('Content-Type' => 'application/xml'),
      'testMode' => $this->testMode,
    );

    $result = $this->http_client->get("https://$this->dataciteUser:$this->datacitePass@" . METADATA_URI, $options);
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
  function sendDoiToDatacite($url)
  {
    $data = sprintf("doi=%s\nurl=%s", $this->getDoi(), $url);
    $options = array(
      'method' => 'POST',
      'data' => $data,
      'timeout' => 15,
      'headers' => array('Content-Type' => 'text/plain;charset=UTF-8'),
      'testMode' => $this->testMode,
    );

    $result = $this->client->get("https://$this->dataciteUser:$this->datacitePass@" . DOI_URI, $options);

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
  function deleteDataciteDoi()
  {
    $options = array(
      'method' => 'DELETE',
      'timeout' => 15,
      'headers' => array('Content-Type' => 'text/plain;charset=UTF-8'),
      'testMode' => $this->testMode,
    );

    $result = $this->http_client->get("https://$this->dataciteUser:$this->datacitePass@METADATA_URI . '/' . $this->getDoi()", $options);

    return $result;
  }
}