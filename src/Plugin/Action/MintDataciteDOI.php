<?php

namespace Drupal\islandora_datacite_doi\Plugin\Action;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dgi_actions\Plugin\Action\HttpActionMintTrait;
use Drupal\dgi_actions\Plugin\Action\MintIdentifier;
use Drupal\dgi_actions\Utility\IdentifierUtils;
use Drupal\islandora_datacite_doi\Utility\DataciteDOITrait;
use GuzzleHttp\ClientInterface;
use http\Exception\BadMessageException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mints a Datacite DOI.
 *
 * @Action(
 *   id = "dgi_actions_mint_datacite_doi",
 *   label = @Translation("Mint a Datacite DOI"),
 *   type = "entity"
 * )
 */
class MintDataciteDOI extends MintIdentifier {

  use DataciteDOITrait;
  use HttpActionMintTrait;

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
   * {@inheritdoc}
   */
  protected function getRequestHeaders(): array {
    return [
      'Content-Type' => 'application/xml;charset=UTF-8',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequestType(): string {
    return 'PUT';
  }


  /**
   * @inheritDoc
   */
  protected function mint(): string {
    return $this->getIdentifierFromResponse($this->doiMetadataRequest());
  }

  /**
   * {@inheritdoc}
   */
  protected function getIdentifierFromResponse(ResponseInterface $response): string {
    $body = $response->getBody()->getContents();
    if (substr($body, 0, 4) == "OK (") {
      $doi = substr($body, 4, -1);

      $this->logger->info('Datacite DOI minted for @type/@id: @doi.', [
        '@type' => $this->getEntity()->getEntityTypeId(),
        '@id' => $this->getEntity()->id(),
        '@doi' => $doi,
      ]);
      return $doi;
    }
    throw new BadMessageException("DOI not found in response body.");
  }

}
