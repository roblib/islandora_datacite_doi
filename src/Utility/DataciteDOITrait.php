<?php

namespace Drupal\islandora_datacite_doi\Utility;

use Drupal\Core\Entity\EntityInterface;
use Drupal\dgi_actions\Plugin\Action\HttpActionTrait;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

use GuzzleHttp\Psr7\Response;
use function DI\string;

/**
 * Utilities when interacting with Datacite's DOI and Metadata Service APIs.
 */
trait DataciteDOITrait {

  use HttpActionTrait;

  /**
   * Identifier entity describing the operation to be done.
   *
   * @var \Drupal\dgi_actions\Entity\IdentifierInterface
   */
  protected $identifier;

  /**
   * Current actioned Entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Constructs the auth parameters for Guzzle to connect to Datacite's API.
   *
   * @return array
   *   Authorization parameters to be passed to Guzzle.
   */
  protected function getAuthorizationParams(): array {
    return [
      $this->getIdentifier()->getServiceData()->getData()['username'],
      $this->getIdentifier()->getServiceData()->getData()['password'],
    ];
  }

  /**
   * Gets the entity being used.
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

  /**
   * Gets the DOI prefix.
   */
  public function getPrefix(): string {
    return $this->getIdentifier()->getServiceData()->getData()['prefix'];
  }

  /**
   * Returns the Datacite MDS API endpoint.
   *
   * @return string
   *   The URL to be used for DOI MDS requests.
   */
  protected function getUri(): string {
    $host = rtrim($this->getIdentifier()->getServiceData()->getData()['host_mds'], '/');

    // If an identifier already exists, attach it to the URI to update the metadata.
    $existing_doi = $this->getDOI();

    $url_slug = $existing_doi ? $existing_doi : $this->getPrefix();

    return "{$host}/{$url_slug}";
  }

  /**
   * Construct a URL for DOI registration.
   * @return false|string
   *   The registration URL, or FALSE if no DOI is set.
   */
  public function getDOIRegistrationUri() {
    $host = rtrim($this->getIdentifier()->getServiceData()->getData()['host_doi'], '/');

    // If an identifier already exists, attach it to the URI to update the metadata.
    $existing_doi = $this->getDOI();
    if (!empty($existing_doi)) {
      return "{$host}/{$existing_doi}";
    }
    else {
      // We can't register a non-existant DOI.
      return FALSE;
    }

  }

  protected function buildMetadataRequest() {
    $body = $this->getFieldData()['xml'];
    return new Request($this->getRequestType(), $this->getUri(), $this->getRequestHeaders(), $body);
  }

  /**
   * @{@inheritdoc }
   */
  protected function getRequestParams(): array {
    return [
      'auth' => $this->getAuthorizationParams(),
    ];
  }

  /**
   * Helper that wraps the normal requests to get more verbosity for errors.
   */
  protected function doiMetadataRequest() {
    try {
      $request = $this->buildMetadataRequest();

      return $this->sendRequest($request);
    } catch (RequestException $e) {
      // Wrap the exception with a bit of extra info for verbosity's sake.
      $message = $e->getMessage();
      $response = $e->getResponse();

      throw new RequestException($message, $e->getRequest(), $response, $e);
    }
  }

  protected function registerDoiUrlRequest() {
    try {
      $request = $this->buildDOIRequest();

      return $this->sendRequest($request);
    } catch (RequestException $e) {
      // Wrap the exception with a bit of extra info for verbosity's sake.
      $message = $e->getMessage();
      $response = $e->getResponse();

      throw new RequestException($message, $e->getRequest(), $response, $e);
    }

  }

  public function registerDoiUrl() {
    $request = $this->buildDOIRequest();
    $result = $request->getBody()->getContents();
    return $result;
  }

  /**
   * @return mixed
   */
  protected function buildDOIRequest() {
    $entity_url = $this->getExternalUrl();
    $body = sprintf("doi=%s\nurl=%s\n", $this->getDOI(), $entity_url);

    return new Request($this->getRequestType(), $this->getDOIRegistrationUri(), $this->getDOIRequestHeaders(), $body);
  }

  /**
   * Retrieves the DOI identifier.
   *
   * @return string
   *   The existing DOI for the entity
   */
  protected function getDOI(): string {
    $existing_doi = '';
    $identifier = $this->getIdentifier();
    $field = $identifier->get('field');
    if (!empty($field) && $this->entity->hasField($field)) {
      $existing_doi = $this->entity->get($field)->getString();
    }
    return $existing_doi;
  }

}
