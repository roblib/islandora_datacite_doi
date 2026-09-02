<?php

namespace Drupal\islandora_datacite_doi\Plugin\Action;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dgi_actions\Plugin\Action\HttpActionMintTrait;
use Drupal\dgi_actions\Plugin\Action\MintIdentifier;
use Drupal\dgi_actions\Utility\IdentifierUtils;
use Drupal\islandora_datacite_doi\Utility\DataciteDOITrait;
use Drupal\islandora_datacite_doi\Utility\DataciteFieldSelector;
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
   * Gets the External URL of the Entity.
   *
   * @return string
   *   Entity's external URL as a string.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\Exception\UndefinedLinkTemplateException
   */
  public function getExternalUrl(): string {
    return $this->entity->toUrl()->setAbsolute()->toString(TRUE)->getGeneratedUrl();
  }


  /**
   * {@inheritdoc}
   */
  protected function getRequestHeaders(): array {
    return [
      'Content-Type' => 'application/xml;charset=UTF-8',
    ];
  }

  protected function getDOIRequestHeaders(): array {
    return [
      'Content-Type' => 'text/plain;charset=UTF-8',
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
  protected function getFieldData(): array {

    $data = [];
    $data_profile = $this->getIdentifier()->getDataProfile();
    if ($data_profile) {
      $profile_data = $data_profile->getData();
      foreach ($profile_data as $key => $field) {
        // Deal with identifiers being an array
        if ($key === 'datacite.identifiers') {
          if ($field[0]['identifier_type'] && $field[0]['identifier_value']) {
            $data['datacite.identifiers'] = [];
            foreach ($field as $identifier) {
              $values = DataciteFieldSelector::resolveValues($this->entity, $identifier['identifier_value']);
              if (!empty($values)) {
                $data['datacite.identifiers'][$identifier['identifier_type']] = $values;
              }
            }
          }
        }
        // Deal with fixed-type contributors: a repeatable set of a
        // profile-level contributor type/nameType value plus one Drupal
        // field selection, where every value in that field gets the same
        // fixed type. Replaces the old hardcoded hostInstitution/supervisor
        // fields.
        else if ($key === 'datacite.contributors') {
          $contributorsList = [];
          foreach ($field as $c) {
            if (empty($c['contributor_type']) || empty($c['field'])) {
              continue;
            }
            foreach (DataciteFieldSelector::resolveValues($this->entity, $c['field']) as $field_item) {
              if (empty($field_item['value'])) {
                continue;
              }
              $entry = [
                'contributor_type' => $c['contributor_type'],
                'name_type' => $c['name_type'] ?? '',
                'value' => $field_item['value'],
              ];
              if (!empty($field_item['ror'])) {
                $entry['ror'] = $field_item['ror'];
              }
              if (!empty($field_item['orcid'])) {
                $entry['orcid'] = $field_item['orcid'];
              }
              $contributorsList[] = $entry;
            }
          }
          if (!empty($contributorsList)) {
            $data[$key] = $contributorsList;
          }
        }
        // Deal with descriptions being a repeatable set of a profile-level
        // description-type value plus one Drupal field selection.
        else if ($key === 'datacite.descriptions') {
          $descriptions = [];
          foreach ($field as $desc) {
            if (empty($desc['description_type']) || empty($desc['description_value'])) {
              continue;
            }
            $value = DataciteFieldSelector::resolveString($this->entity, $desc['description_value']);
            if ($value === '') {
              continue;
            }
            $descriptions[] = [
              'description_type' => $desc['description_type'],
              'value' => $value,
            ];
          }
          if (!empty($descriptions)) {
            $data[$key] = $descriptions;
          }
        }
        // Deal with titles being a repeatable set of a profile-level
        // title-type value plus one Drupal field selection for the title.
        else if ($key === 'datacite.titles') {
          $titles = [];
          foreach ($field as $t) {
            if (empty($t['title_value'])) {
              continue;
            }
            $value = DataciteFieldSelector::resolveString($this->entity, $t['title_value']);
            if ($value === '') {
              continue;
            }
            $titles[] = [
              'title_type' => $t['title_type'] ?? '',
              'value' => $value,
            ];
          }
          if (!empty($titles)) {
            $data[$key] = $titles;
          }
        }
        // Deal with the author/contributor nameType selections being
        // literal DataCite enum values, not Drupal field names.
        else if ($key === 'datacite.authorNameType' || $key === 'datacite.contributorNameType') {
          if (!empty($field)) {
            $data[$key] = $field;
          }
        }
        // Deal with the funder sub-fields as a set of paragraph sub-field
        // selectors that must resolve correlated per paragraph, so e.g. a
        // name and award number from the same paragraph stay paired even
        // when one is blank for that paragraph.
        else if ($key === 'datacite.funderName') {
          $rows = DataciteFieldSelector::resolveParagraphRows($this->entity, [
            'value' => $field,
            'identifier' => $profile_data['datacite.funderIdentifier'] ?? '',
            'award_number' => $profile_data['datacite.funderAwardNumber'] ?? '',
            'award_uri' => $profile_data['datacite.funderAwardURI'] ?? '',
            'award_title' => $profile_data['datacite.funderAwardTitle'] ?? '',
          ]);
          $funders = array_values(array_filter($rows, fn(array $row) => !empty($row['value'])));
          if (!empty($profile_data['datacite.funderIdentifierType'])) {
            foreach ($funders as &$funder) {
              if (!empty($funder['identifier'])) {
                $funder['identifier_type'] = $profile_data['datacite.funderIdentifierType'];
              }
            }
            unset($funder);
          }
          if (!empty($funders)) {
            $data['datacite.funder'] = $funders;
          }
        }
        // Resolved together with funderName above.
        else if (in_array($key, ['datacite.funderIdentifier', 'datacite.funderIdentifierType', 'datacite.funderAwardNumber', 'datacite.funderAwardURI', 'datacite.funderAwardTitle'])) {
        }
        // Deal with geolocations being a repeatable set of a place name
        // field and a Geolocation-module field (holding lat/lng).
        else if ($key === 'datacite.geoLocations') {
          $geoLocations = [];
          foreach ($field as $geo) {
            $entry = [];
            if (!empty($geo['place'])) {
              $place = DataciteFieldSelector::resolveString($this->entity, $geo['place']);
              if ($place !== '') {
                $entry['place'] = $place;
              }
            }
            if (!empty($geo['point'])) {
              $point_values = DataciteFieldSelector::resolveValues($this->entity, $geo['point']);
              $point_value = $point_values[0] ?? [];
              if (!empty($point_value['lat']) && !empty($point_value['lng'])) {
                $entry['latitude'] = $point_value['lat'];
                $entry['longitude'] = $point_value['lng'];
              }
            }
            if (!empty($entry)) {
              $geoLocations[] = $entry;
            }
          }
          if (!empty($geoLocations)) {
            $data[$key] = $geoLocations;
          }
        }
        // Deal with dates being a repeatable set of a profile-level
        // date-type value plus one Drupal field selection for the date.
        else if ($key === 'datacite.dates') {
          $dates = [];
          foreach ($field as $d) {
            if (empty($d['date_type']) || empty($d['date_value'])) {
              continue;
            }
            $value = DataciteFieldSelector::resolveString($this->entity, $d['date_value']);
            if ($value === '') {
              continue;
            }
            $dates[] = [
              'date_type' => $d['date_type'],
              'value' => $value,
            ];
          }
          if (!empty($dates)) {
            $data[$key] = $dates;
          }
        }
        // Deal with related identifiers being a repeatable set of
        // profile-level relation/identifier-type values plus one Drupal
        // field selection for the identifier's value.
        else if ($key === 'datacite.relatedIdentifiers') {
          $relatedIdentifiers = [];
          foreach ($field as $rid) {
            if (empty($rid['relation_type']) || empty($rid['identifier_type']) || empty($rid['identifier_value'])) {
              continue;
            }
            $value = DataciteFieldSelector::resolveString($this->entity, $rid['identifier_value']);
            if ($value === '') {
              continue;
            }
            $relatedIdentifiers[] = [
              'relation_type' => $rid['relation_type'],
              'identifier_type' => $rid['identifier_type'],
              'resource_type_general' => $rid['resource_type_general'] ?? '',
              'value' => $value,
            ];
          }
          if (!empty($relatedIdentifiers)) {
            $data[$key] = $relatedIdentifiers;
          }
        }
        // Deal with related items being a repeatable set of Drupal field
        // selections plus profile-level relation/type/identifier-type values.
        else if ($key === 'datacite.relatedItems') {
          $relatedItems = [];
          foreach ($field as $ri) {
            if (empty($ri['relation_type']) || empty($ri['related_item_type'])) {
              continue;
            }
            $entry = [
              'relation_type' => $ri['relation_type'],
              'related_item_type' => $ri['related_item_type'],
              'related_identifier_type' => $ri['related_identifier_type'] ?? '',
              'number_type' => $ri['number_type'] ?? '',
              'contributor_type' => $ri['contributor_type'] ?? '',
              'creators_name_type' => $ri['creators_name_type'] ?? '',
              'contributors_name_type' => $ri['contributors_name_type'] ?? '',
              'typed_contributors_name_type' => $ri['typed_contributors_name_type'] ?? '',
            ];
            foreach (['identifier_value', 'creators', 'title', 'publication_year', 'volume', 'issue', 'number', 'first_page', 'last_page', 'publisher', 'edition', 'contributors'] as $sub_key) {
              $selector = $ri[$sub_key] ?? '';
              if (empty($selector)) {
                continue;
              }
              if ($sub_key === 'creators' || $sub_key === 'contributors') {
                $values = array_column(DataciteFieldSelector::resolveValues($this->entity, $selector), 'value');
                if (!empty($values)) {
                  $entry[$sub_key] = $values;
                }
              }
              else {
                $value = DataciteFieldSelector::resolveString($this->entity, $selector);
                if ($value !== '') {
                  $entry[$sub_key] = $value;
                }
              }
            }
            // Typed relation contributors: type varies per value via the
            // field's own rel_type property, same as the top-level
            // "contributor" field.
            if (!empty($ri['typed_contributors'])) {
              $typedContributors = array_filter(
                DataciteFieldSelector::resolveValues($this->entity, $ri['typed_contributors']),
                fn(array $item) => !empty($item['value'])
              );
              if (!empty($typedContributors)) {
                $entry['typed_contributors'] = array_values($typedContributors);
              }
            }
            // Skip entries with no actual per-node content: relation_type/
            // related_item_type are profile-level config and would always
            // be set even when every field selector resolved to nothing
            // on this specific node (e.g. an empty host journal field).
            $content_keys = ['identifier_value', 'creators', 'title', 'publication_year', 'volume', 'issue', 'number', 'first_page', 'last_page', 'publisher', 'edition', 'contributors', 'typed_contributors'];
            $has_content = FALSE;
            foreach ($content_keys as $content_key) {
              if (!empty($entry[$content_key])) {
                $has_content = TRUE;
                break;
              }
            }
            if (!$has_content) {
              continue;
            }
            $relatedItems[] = $entry;
          }
          if (!empty($relatedItems)) {
            $data[$key] = $relatedItems;
          }
        }
        // A plain field selector is expected here; anything else (e.g. a
        // stale array-shaped value left over in saved config from a field
        // whose shape changed since it was configured) is skipped rather
        // than crashing.
        else if (is_string($field) && $field !== '') {
          $values = DataciteFieldSelector::resolveValues($this->entity, $field);
          if (!empty($values)) {
            $data[$key] = $values;
          }
        }
      }
    }
    return $data;
  }


  /**
   * @inheritDoc
   */
  protected function mint(): string {
    $data = $this->getFieldData();
    $request = $this->doiMetadataRequest($data);
    if (is_null($request)) {
      return '';
    }
    $doi = $this->getIdentifierFromResponse($request);

    $entity = $this->getEntity();
    if ($entity->get('status')->getString()) {
      $this->registerDoiUrlRequest($doi);
    }
    return $doi;
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
