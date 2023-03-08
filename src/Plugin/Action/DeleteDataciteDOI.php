<?php

namespace Drupal\islandora_datacite_doi\Plugin\Action;


use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dgi_actions\Plugin\Action\DeleteIdentifier;
use Drupal\dgi_actions\Plugin\Action\HttpActionDeleteTrait;
use Drupal\dgi_actions\Utility\IdentifierUtils;
use Drupal\islandora_datacite_doi\Utility\DataciteDOITrait;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Marks a DOI identifier as 'Archived'.
 *
 * @Action(
 *   id = "dgi_actions_delete_datacite_doi",
 *   label = @Translation("Mark DOI as 'Archived'"),
 *   type = "entity"
 * )
 */
class DeleteDataciteDOI extends DeleteIdentifier {

  use DataciteDOITrait;
  use HttpActionDeleteTrait;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\dgi_actions\Utility\IdentifierUtils $utils
   *   Identifier utils.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client to be used for the request.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, IdentifierUtils $utils, EntityTypeManagerInterface $entity_type_manager, ClientInterface $client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger, $utils, $entity_type_manager);
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.dgi_actions'),
      $container->get('dgi_actions.utils'),
      $container->get('entity_type.manager'),
      $container->get('http_client')
    );
  }

  /**
   * Builds the Guzzle HTTP Request.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   *   Thrown by Guzzle when creating an invalid Request.
   *
   * @return \GuzzleHttp\Psr7\Request
   *   The Guzzle HTTP Request Object.
   */
  protected function buildRequest(): RequestInterface {
    $requestType = $this->getRequestType();
    $uri = $this->getDOIRegistrationUri();
    $headers = $this->getRequestHeaders();
    return new Request($requestType, $uri, $headers);
  }


  /**
   * Headers to attach to the delete request.
   *
   * @return string[]
   *   The array of request headers.
   */
  protected function getRequestHeaders() {
    return ["Content-Type" => "application/plain;charset=UTF-8" ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequestType(): string {
    return 'DELETE';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequestParams(): array {
    return [
      RequestOptions::AUTH => $this->getAuthorizationParams(),
    ];
  }


  /**
   * {@inheritdoc}
   */
  protected function handleDeleteResponse(ResponseInterface $response): void {
    $contents = $response->getBody()->getContents();

    if (array_key_exists('success', $contents)) {
      $this->logger->info('DOI set to Archived: @contents', ['@contents' => $contents]);
    }
    else {
      $this->logger->error('There was an issue setting DOI to Archivedr: @contents', ['@contents' => $contents]);
    }
  }



  /**
   * @inheritDoc
   */
  protected function delete(): void {
    // TODO: Implement delete() method.
  }
}
