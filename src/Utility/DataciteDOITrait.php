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
    $host = getDOIHost();

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

  private function getDOIHost() {
    return rtrim($this->getIdentifier()->getServiceData()->getData()['host_doi'], '/');
  }

  protected function buildMetadataRequest(array $data) {
    $availableTypes = DataciteVocabularies::RESOURCE_TYPES;
    $availableContributorTypes = DataciteVocabularies::CONTRIBUTOR_TYPES;

    // Check if all mandatory fields are available.
    $missing = [];

    if (!array_key_exists("datacite.titles", $data) || empty($data["datacite.titles"])) {
      $missing[] = "Title";
    }

    if (!array_key_exists("datacite.author", $data) || empty($data["datacite.author"]) || empty($data["datacite.author"][0]["value"])) {
      $missing[] = "Author";
    }

    if (!array_key_exists("datacite.publisher", $data) || empty($data["datacite.publisher"]) || empty($data["datacite.publisher"][0]["value"])) {
      $missing[] = "Publisher";
    }

    if (!array_key_exists("datacite.year", $data) || empty($data["datacite.year"]) || empty($data["datacite.year"][0]["value"]) || !preg_match('/\b[\dX]{4}\b/', $data["datacite.year"][0]["value"])) {
      $missing[] = "Year";
    }

    if (!array_key_exists("datacite.rtypeGeneral", $data) || empty($data["datacite.rtypeGeneral"]) || empty($data["datacite.rtypeGeneral"][0]["value"])) {
      $missing[] = "Resource Type General";
    }

    // If any of the mandatory fields are missing, log a warning (with what
    // was actually resolved from the data profile's field mappings, for
    // debugging which mapping is off) and return.
    if (!empty($missing)) {
      \Drupal::logger('islandora_datacite_doi')->warning("Could not mint DOI. Missing the following mandatory fields: @fields. Resolved field data: <pre>@data</pre>", [
        '@fields' => implode(', ', $missing),
        '@data' => print_r($data, TRUE),
      ]);
      return NULL;
    }

    // Create XML for Datacite.
    $body = new \SimpleXMLElement('<resource xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://datacite.org/schema/kernel-4" xsi:schemaLocation="http://datacite.org/schema/kernel-4 https://schema.datacite.org/meta/kernel-4/metadata.xsd"></resource>');

    // DOI prefix.
    $body->addChild('identifier', $this->getPrefix())->addAttribute('identifierType', 'DOI');

    // Creator.
    $creators = $body->addChild('creators');
    foreach ($data["datacite.author"] as $auth) {
      $creator = $creators->addChild('creator');
      $creatorName = $creator->addChild('creatorName', $auth["value"]);
      // nameType is optional; only set it if the admin configured one.
      if (!empty($data["datacite.authorNameType"])) {
        $creatorName->addAttribute('nameType', $data["datacite.authorNameType"]);
      }
      // Add ORCID if available.
      if (array_key_exists("orcid", $auth)) {
        $id = $creator->addChild('nameIdentifier', $auth["orcid"]);
        $id->addAttribute('nameIdentifierScheme', 'ORCID');
        $id->addAttribute('schemeURI', 'https://orcid.org');
      }
    }

    // Titles. Each entry's title_type is chosen from DataCite's controlled
    // vocabulary directly in the data profile form; left unset, it's the
    // main title (titleType is omitted, matching DataCite's convention).
    $titles = $body->addChild('titles');
    foreach ($data["datacite.titles"] as $t) {
      $title = $titles->addChild('title', $t['value']);
      if (!empty($t['title_type'])) {
        $title->addAttribute('titleType', $t['title_type']);
      }
    }

    // Publisher.
    $publisher = $body->addChild('publisher', $data["datacite.publisher"][0]["value"]);
    // Add ROR if available.
    if (array_key_exists("ror", $data["datacite.publisher"][0])) {
      $publisher->addAttribute('publisherIdentifier', $data["datacite.publisher"][0]["ror"]);
      $publisher->addAttribute('publisherIdentifierScheme', 'ROR');
      $publisher->addAttribute('schemeURI', 'https://ror.org');
    }
    // Publication Year.
    // If string or EDTF is given, extract just year swapping Xs for 0s.
    $years = array();
    preg_match('/\b[\dX]{4}\b/', $data["datacite.year"][0]["value"], $years);
    $body->addChild('publicationYear', $years[0]);

    // Resource Type.
    // Normalize by stripping spaces and capitalizing each word, e.g.
    // "journal article" or "Journal Article" becomes "JournalArticle" to
    // match DataCite's PascalCase resourceTypeGeneral values. Set to
    // "Other" if the normalized value isn't in DataCite's list.
    $rtypeWords = preg_split('/\s+/', trim($data["datacite.rtypeGeneral"][0]["value"]));
    $rtypeGeneral = implode('', array_map('ucfirst', $rtypeWords));
    if (!in_array($rtypeGeneral, $availableTypes)) {
      $rtypeGeneral = "Other";
    }
    $body->addChild('resourceType', $data["datacite.rtype"][0]["value"])->addAttribute('resourceTypeGeneral', $rtypeGeneral);

    // The following fields are all optional for Datacite.

    // Subject(s).
    if (array_key_exists("datacite.subject", $data)) {
      $subjects = $body->addChild('subjects');
      foreach ($data["datacite.subject"] as $subject) {
        $subject = $subjects->addChild('subject', $subject["value"]);
      }
    }

    // Contributors.
    $contributors = $body->addChild('contributors');

    // Fixed-type contributors (e.g. hosting institution, thesis supervisor).
    // Each entry's contributor_type/name_type are chosen from DataCite's
    // controlled vocabularies directly in the data profile form.
    if (array_key_exists("datacite.contributors", $data)) {
      foreach ($data["datacite.contributors"] as $c) {
        $fixedContributor = $contributors->addChild('contributor');
        $fixedContributor->addAttribute('contributorType', $c['contributor_type']);
        $fixedContributorName = $fixedContributor->addChild('contributorName', $c['value']);
        if (!empty($c['name_type'])) {
          $fixedContributorName->addAttribute('nameType', $c['name_type']);
        }
        // Add ROR or ORCID if available.
        if (array_key_exists("ror", $c)) {
          $id = $fixedContributor->addChild('nameIdentifier', $c["ror"]);
          $id->addAttribute('nameIdentifierScheme', 'ROR');
          $id->addAttribute('schemeURI', 'https://ror.org');
        }
        if (array_key_exists("orcid", $c)) {
          $id = $fixedContributor->addChild('nameIdentifier', $c["orcid"]);
          $id->addAttribute('nameIdentifierScheme', 'ORCID');
          $id->addAttribute('schemeURI', 'https://orcid.org');
        }
      }
    }

    // Contributors (typed relation to a person/organization taxonomy term).
    if (array_key_exists("datacite.contributor", $data)) {
      foreach ($data["datacite.contributor"] as $contrib) {
        $contributor = $contributors->addChild('contributor');
        // Set to other if not in DataCite's list.
        $contributorType = $contrib['rel_type'] ?? '';
        if (!in_array($contributorType, $availableContributorTypes)) {
          $contributorType = "Other";
        }
        $contributor->addAttribute('contributorType', $contributorType);
        $contributorName = $contributor->addChild('contributorName', $contrib["value"]);
        // nameType is optional; only set it if the admin configured one.
        if (!empty($data["datacite.contributorNameType"])) {
          $contributorName->addAttribute('nameType', $data["datacite.contributorNameType"]);
        }
        // Add ORCID if available.
        if (array_key_exists("orcid", $contrib)) {
          $id = $contributor->addChild('nameIdentifier', $contrib["orcid"]);
          $id->addAttribute('nameIdentifierScheme', 'ORCID');
          $id->addAttribute('schemeURI', 'https://orcid.org');
        }
      }
    }

    // Dates. Each entry's date_type is chosen from DataCite's controlled
    // vocabulary directly in the data profile form.
    if (array_key_exists("datacite.dates", $data) && !empty($data["datacite.dates"])) {
      $dates = $body->addChild('dates');
      foreach ($data["datacite.dates"] as $d) {
        $raw = $d['value'];
        $normalized = str_replace('X', '0', $raw);
        // If the date isn't in the form YYYY-MM-DD, just pull the year.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
          $years = array();
          preg_match('/\b[\dX]{4}\b/', $normalized, $years);
          $normalized = $years[0] ?? $normalized;
        }

        $date = $dates->addChild('date', $normalized);
        $date->addAttribute('dateType', $d['date_type']);
        // If our stored date differs from the original, put the original in the dateInformation attribute.
        if ($normalized !== $raw) {
          $date->addAttribute('dateInformation', $raw);
        }
      }
    }

    // Language.
    if (array_key_exists("datacite.language", $data)) {
      $body->addChild('language', $data["datacite.language"][0]["value"]);
    }

    // Alternate identifiers.
    if (array_key_exists("datacite.identifiers", $data)) {
      $altIds = $body->addChild('alternateIdentifiers');
      foreach ($data["datacite.identifiers"] as $type => $value) {
        if (!empty($type) && !empty($value)) {
          $altIds->addChild('alternateIdentifier', $value[0]["value"])
                 ->addAttribute('alternateIdentifierType', $type);
        }
      }
    }

    // Related Identifiers. Each entry's relation_type/identifier_type/
    // resource_type_general are chosen from DataCite's controlled
    // vocabularies directly in the data profile form, so they're already
    // valid values here and don't need fallback validation.
    if (array_key_exists("datacite.relatedIdentifiers", $data) && !empty($data["datacite.relatedIdentifiers"])) {
      $related = $body->addChild('relatedIdentifiers');
      foreach ($data["datacite.relatedIdentifiers"] as $rid) {
        $relatedIdentifier = $related->addChild('relatedIdentifier', $rid['value']);
        $relatedIdentifier->addAttribute('relatedIdentifierType', $rid['identifier_type']);
        $relatedIdentifier->addAttribute('relationType', $rid['relation_type']);
        if (!empty($rid['resource_type_general'])) {
          $relatedIdentifier->addAttribute('resourceTypeGeneral', $rid['resource_type_general']);
        }
      }
    }

    // Sizes.
    if (array_key_exists("datacite.size", $data)) {
      $sizes = $body->addChild('sizes');
      foreach ($data["datacite.size"] as $size) {
        $sizes->addChild('size', $size["value"]);
      }
    }

    // Formats.
    if (array_key_exists("datacite.format", $data)) {
      $formats = $body->addChild('formats');
      foreach ($data["datacite.format"] as $format) {
        $formats->addChild('format', $format["value"]);
      }
    }

    // Version.
    if (array_key_exists("datacite.version", $data)) {
      $body->addChild('version', $data["datacite.version"][0]["value"]);
    }

    // Rights.
    if (array_key_exists("datacite.rights", $data)) {
      $body->addchild('rightsList')->addChild('rights', $data["datacite.rights"][0]["value"]);
    }

    // Descriptions. Each entry's description_type is chosen from DataCite's
    // controlled vocabulary directly in the data profile form.
    if (array_key_exists("datacite.descriptions", $data) && !empty($data["datacite.descriptions"])) {
      $descriptions = $body->addChild('descriptions');
      foreach ($data["datacite.descriptions"] as $desc) {
        $descriptions->addchild('description', $desc['value'])->addAttribute('descriptionType', $desc['description_type']);
      }
    }

    // Geographic Locations.
    if (array_key_exists("datacite.geoLocations", $data) && !empty($data["datacite.geoLocations"])) {
      $geoLocationsEl = $body->addChild('geoLocations');
      foreach ($data["datacite.geoLocations"] as $geo) {
        $geoLocation = $geoLocationsEl->addChild('geoLocation');
        if (!empty($geo['place'])) {
          $geoLocation->addChild('geoLocationPlace', $geo['place']);
        }
        if (!empty($geo['latitude']) && !empty($geo['longitude'])) {
          $point = $geoLocation->addChild('geoLocationPoint');
          $point->addChild('pointLatitude', $geo['latitude']);
          $point->addChild('pointLongitude', $geo['longitude']);
        }
      }
    }

    // Funding References (paragraphs with funder/award sub-fields).
    if (array_key_exists("datacite.funder", $data)) {
      // Scheme URIs conventionally paired with each funderIdentifierType;
      // "Other" and any unrecognized type just omit schemeURI.
      $funderSchemeUris = [
        'ROR' => 'https://ror.org',
        'ISNI' => 'https://isni.org',
        'GRID' => 'https://www.grid.ac',
        'Crossref Funder ID' => 'https://doi.org',
      ];

      $fundingReferences = $body->addChild('fundingReferences');
      foreach ($data["datacite.funder"] as $funder) {
        $fundingReference = $fundingReferences->addChild('fundingReference');
        $fundingReference->addChild('funderName', $funder["value"]);
        if (!empty($funder['identifier'])) {
          $funderIdentifierType = $funder['identifier_type'] ?: 'Other';
          $funderIdentifier = $fundingReference->addChild('funderIdentifier', $funder['identifier']);
          $funderIdentifier->addAttribute('funderIdentifierType', $funderIdentifierType);
          if (!empty($funderSchemeUris[$funderIdentifierType])) {
            $funderIdentifier->addAttribute('schemeURI', $funderSchemeUris[$funderIdentifierType]);
          }
        }
        if (!empty($funder['award_number'])) {
          $awardNumber = $fundingReference->addChild('awardNumber', $funder['award_number']);
          if (!empty($funder['award_uri'])) {
            $awardNumber->addAttribute('awardURI', $funder['award_uri']);
          }
        }
        if (!empty($funder['award_title'])) {
          $fundingReference->addChild('awardTitle', $funder['award_title']);
        }
      }
    }

    // Related Items. Each entry's relation_type/related_item_type/
    // related_identifier_type are chosen from DataCite's controlled
    // vocabularies directly in the data profile form, so they're already
    // valid values here and don't need fallback validation.
    if (array_key_exists("datacite.relatedItems", $data) && !empty($data["datacite.relatedItems"])) {
      $relatedItemsEl = $body->addChild('relatedItems');
      foreach ($data["datacite.relatedItems"] as $ri) {
        $relatedItem = $relatedItemsEl->addChild('relatedItem');
        $relatedItem->addAttribute('relatedItemType', $ri['related_item_type']);
        $relatedItem->addAttribute('relationType', $ri['relation_type']);

        if (!empty($ri['identifier_value'])) {
          $relatedItem->addChild('relatedItemIdentifier', $ri['identifier_value'])
                      ->addAttribute('relatedItemIdentifierType', $ri['related_identifier_type']);
        }
        if (!empty($ri['creators'])) {
          $creatorsEl = $relatedItem->addChild('creators');
          foreach ($ri['creators'] as $creatorName) {
            if (empty($creatorName)) {
              continue;
            }
            $riCreatorName = $creatorsEl->addChild('creator')->addChild('creatorName', $creatorName);
            // nameType is optional; only set it if the admin configured one.
            if (!empty($ri['creators_name_type'])) {
              $riCreatorName->addAttribute('nameType', $ri['creators_name_type']);
            }
          }
        }
        if (!empty($ri['title'])) {
          $relatedItem->addChild('titles')->addChild('title', $ri['title']);
        }
        if (!empty($ri['publication_year'])) {
          $relatedItem->addChild('publicationYear', $ri['publication_year']);
        }
        if (!empty($ri['volume'])) {
          $relatedItem->addChild('volume', $ri['volume']);
        }
        if (!empty($ri['issue'])) {
          $relatedItem->addChild('issue', $ri['issue']);
        }
        if (!empty($ri['number'])) {
          $number = $relatedItem->addChild('number', $ri['number']);
          if (!empty($ri['number_type'])) {
            $number->addAttribute('numberType', $ri['number_type']);
          }
        }
        if (!empty($ri['first_page'])) {
          $relatedItem->addChild('firstPage', $ri['first_page']);
        }
        if (!empty($ri['last_page'])) {
          $relatedItem->addChild('lastPage', $ri['last_page']);
        }
        if (!empty($ri['publisher'])) {
          $relatedItem->addChild('publisher', $ri['publisher']);
        }
        if (!empty($ri['edition'])) {
          $relatedItem->addChild('edition', $ri['edition']);
        }
        // Fixed-type and typed relation contributors share the same single
        // <contributors> wrapper a relatedItem is allowed.
        if (!empty($ri['contributors']) || !empty($ri['typed_contributors'])) {
          $riContributorsEl = $relatedItem->addChild('contributors');

          // Fixed-type contributors.
          if (!empty($ri['contributors'])) {
            foreach ($ri['contributors'] as $contributorName) {
              if (empty($contributorName)) {
                continue;
              }
              $riContributor = $riContributorsEl->addChild('contributor');
              $riContributor->addAttribute('contributorType', $ri['contributor_type'] ?: 'Other');
              $riContributorName = $riContributor->addChild('contributorName', $contributorName);
              // nameType is optional; only set it if the admin configured one.
              if (!empty($ri['contributors_name_type'])) {
                $riContributorName->addAttribute('nameType', $ri['contributors_name_type']);
              }
            }
          }

          // Typed relation contributors: type varies per value via
          // rel_type, same as the top-level "contributor" field.
          if (!empty($ri['typed_contributors'])) {
            foreach ($ri['typed_contributors'] as $tc) {
              $riTypedContributor = $riContributorsEl->addChild('contributor');
              $typedContributorType = $tc['rel_type'] ?? '';
              if (!in_array($typedContributorType, $availableContributorTypes)) {
                $typedContributorType = 'Other';
              }
              $riTypedContributor->addAttribute('contributorType', $typedContributorType);
              $riTypedContributorName = $riTypedContributor->addChild('contributorName', $tc['value']);
              // nameType is optional; only set it if the admin configured one.
              if (!empty($ri['typed_contributors_name_type'])) {
                $riTypedContributorName->addAttribute('nameType', $ri['typed_contributors_name_type']);
              }
              // Add ORCID if available.
              if (!empty($tc['orcid'])) {
                $id = $riTypedContributor->addChild('nameIdentifier', $tc['orcid']);
                $id->addAttribute('nameIdentifierScheme', 'ORCID');
                $id->addAttribute('schemeURI', 'https://orcid.org');
              }
            }
          }
        }
      }
    }

    return new Request($this->getRequestType(), $this->getUri(), $this->getRequestHeaders(), $body->asXML());
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
  protected function doiMetadataRequest($data) {
    try {
      $request = $this->buildMetadataRequest($data);
      if (is_null($request)) {
        return NULL;
      }

      return $this->sendRequest($request);
    } catch (RequestException $e) {
      // Wrap the exception with a bit of extra info for verbosity's sake.
      $message = $e->getMessage();
      $response = $e->getResponse();

      throw new RequestException($message, $e->getRequest(), $response, $e);
    }
  }

  protected function registerDoiUrlRequest($doi) {
    try {
      $request = $this->buildDOIRequest($doi);

      return $this->sendRequest($request);
    } catch (RequestException $e) {
      // Wrap the exception with a bit of extra info for verbosity's sake.
      $message = $e->getMessage();
      $response = $e->getResponse();

      throw new RequestException($message, $e->getRequest(), $response, $e);
    }

  }

  /**
   * @return mixed
   */
  protected function buildDOIRequest($doi) {
    $entity_url = $this->getExternalUrl();
    $body = sprintf("doi=%s\nurl=%s\n", $doi, $entity_url);

    return new Request($this->getRequestType(), $this->getDOIHost() . '/' . $doi, $this->getDOIRequestHeaders(), $body);
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
