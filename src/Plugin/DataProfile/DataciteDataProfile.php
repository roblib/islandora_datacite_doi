<?php

namespace Drupal\islandora_datacite_doi\Plugin\DataProfile;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\dgi_actions\Plugin\DataProfileBase;
use Drupal\islandora_datacite_doi\Utility\DataciteFieldSelector;
use Drupal\islandora_datacite_doi\Utility\DataciteVocabularies;

/**
 * Datacite Data profile.
 *
 * @DataProfile(
 *   id = "datacite",
 *   label = @Translation("Datacite"),
 *   description = @Translation("Datacite Data Profile for interacting with Datacite API.")
 * )
 */
class DataciteDataProfile extends DataProfileBase {

  /**
   * Keys of the sub-fields stored for each repeatable related item entry.
   */
  const RELATED_ITEM_KEYS = [
    'relation_type',
    'related_item_type',
    'related_identifier_type',
    'identifier_value',
    'creators',
    'creators_name_type',
    'title',
    'publication_year',
    'volume',
    'issue',
    'number_type',
    'number',
    'first_page',
    'last_page',
    'publisher',
    'edition',
    'contributor_type',
    'contributors',
    'contributors_name_type',
    'typed_contributors',
    'typed_contributors_name_type',
  ];

  /**
   * Keys of the sub-fields stored for each repeatable fixed-type
   * contributor entry.
   */
  const CONTRIBUTOR_KEYS = [
    'contributor_type',
    'name_type',
    'field',
  ];

  /**
   * Keys of the sub-fields stored for each repeatable title entry.
   */
  const TITLE_KEYS = [
    'title_type',
    'title_value',
  ];

  /**
   * Keys of the sub-fields stored for each repeatable description entry.
   */
  const DESCRIPTION_KEYS = [
    'description_type',
    'description_value',
  ];

  /**
   * Keys of the sub-fields stored for each repeatable date entry.
   */
  const DATE_KEYS = [
    'date_type',
    'date_value',
  ];

  /**
   * Keys of the sub-fields stored for each repeatable geolocation entry.
   */
  const GEOLOCATION_KEYS = [
    'place',
    'point',
  ];

  /**
   * Keys of the sub-fields stored for each repeatable related identifier
   * entry.
   */
  const RELATED_IDENTIFIER_KEYS = [
    'relation_type',
    'identifier_type',
    'resource_type_general',
    'identifier_value',
  ];

  /**
   * Datacite data profile constructor.
   *
   * @param array $configuration
   *   Array containing default configuration for the plugin.
   * @param string $plugin_id
   *   The ID of the plugin being instantiated.
   * @param array $plugin_definition
   *   Array describing the plugin definition.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function modifyData(array $data): array {
    $datacite_data = [];
    foreach ($data as $field => $value) {
      $datacite_data["datacite.$field"] = $value;
    }

    return $datacite_data;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'author' => NULL,
      'authorNameType' => NULL,
      'titles' => [],
      'publisher' => NULL,
      'year' => NULL,
      'rtypeGeneral' => NULL,
      'rtype' => NULL,
      'subject' => NULL,
      'contributors' => [],
      'contributor' => NULL,
      'contributorNameType' => NULL,
      'dates' => [],
      'language' => NULL,
      'identifiers' => [],
      'relatedIdentifiers' => [],
      'size' => NULL,
      'format' => NULL,
      'version' => NULL,
      'rights' => NULL,
      'descriptions' => [],
      'geoLocations' => [],
      'funderName' => NULL,
      'funderIdentifier' => NULL,
      'funderIdentifierType' => NULL,
      'funderAwardNumber' => NULL,
      'funderAwardURI' => NULL,
      'funderAwardTitle' => NULL,
      'relatedItems' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    // The available fields from the entity/bundle are passed through a
    // temporary value in the form state. Expand it to also offer fields
    // from within any referenced paragraphs.
    $available_fields = $form_state->getTemporaryValue('available_fields');
    $field_options = DataciteFieldSelector::buildFieldOptions($available_fields);

    $name_type_options = array_combine(DataciteVocabularies::NAME_TYPES, DataciteVocabularies::NAME_TYPES);

    $form['author'] = [
      '#prefix' => Markup::create('<fieldset><legend>' . $this->t('Author') . '</legend>'),
      '#title' => $this->t('Author(s)'),
      '#description' => $this->t('Author(s) of the object. If author is a taxonomy term and the taxonomy has a URL field called field_orcid, that value is automatically pulled as well.'),
      '#type' => 'select',
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['author'],
      '#required' => TRUE,
    ];
    $form['authorNameType'] = [
      '#suffix' => Markup::create('</fieldset>'),
      '#title' => $this->t('Author Name Type'),
      '#description' => $this->t('Left unset, the nameType attribute is omitted (unknown).'),
      '#type' => 'select',
      '#options' => $name_type_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['authorNameType'],
    ];
    $title_type_options = array_combine(DataciteVocabularies::TITLE_TYPES, DataciteVocabularies::TITLE_TYPES);

    $form['titles'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Titles'),
      '#description' => $this->t('At least one title is required. Leave Title Type unset for the main title; use it to add a subtitle, translated title, alternative title, or other title.'),
      '#prefix' => '<div id="titles-wrapper">',
      '#suffix' => '</div>',
    ];

    $title_count = $form_state->get('title_count');
    $title_values = $form_state->get('title_values');

    if ($title_count === NULL || $title_values === NULL) {
      $saved = $this->configuration['titles'] ?? [];
      $title_values = !empty($saved) ? $saved : [[]];
      $form_state->set('title_values', $title_values);
      $form_state->set('title_count', count($title_values));
      $title_count = count($title_values);
    }

    $form['titles'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Titles'),
      '#description' => $this->t('At least one title is required. Leave Title Type unset for the main title; use it to add a subtitle, translated title, alternative title, or other title.'),
      '#prefix' => '<div id="titles-wrapper">',
      '#suffix' => '</div>',
    ];

    for ($i = 0; $i < $title_count; $i++) {
      $saved_value = $title_values[$i] ?? [];
      $form['titles'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Title @num', ['@num' => $i + 1]),
      ];
      $form['titles'][$i]['title_value'] = [
        '#type' => 'select',
        '#title' => $this->t('Title'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['title_value'] ?? '',
      ];
      $form['titles'][$i]['title_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Title Type'),
        '#options' => $title_type_options,
        '#empty_option' => $this->t('- Main Title -'),
        '#default_value' => $saved_value['title_type'] ?? '',
      ];
      if ($title_count > 1) {
        $form['titles'][$i]['remove_title'] = [
          '#type' => 'button',
          '#value' => $this->t('Remove'),
          '#name' => 'remove_title_' . $i,
          '#ajax' => [
            'callback' => [$this, 'addTitleCallback'],
            'wrapper' => 'titles-wrapper',
            'event' => 'click',
          ],
          '#executes_submit_callback' => TRUE,
          '#submit' => [[$this, 'removeTitleSubmit']],
          '#limit_validation_errors' => [['data', 'titles']],
        ];
      }
    }

    $form['titles']['add_title'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another title'),
      '#submit' => [[$this, 'addTitleSubmit']],
      '#ajax' => [
        'callback' => [$this, 'addTitleCallback'],
        'wrapper' => 'titles-wrapper',
      ],
      '#limit_validation_errors' => [['data', 'titles']],
    ];

    $form['publisher'] = [
      '#title' => $this->t('Publisher'),
      '#description' => $this->t('Name of the publisher. If publisher is a taxonomy term and the taxonomy has a URL field called field_ror, that value is automatically pulled as well.'),
      '#type' => 'select',
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['publisher'],
      '#required' => TRUE,
    ];
    $form['year'] = [
      '#title' => $this->t('Year'),
      '#description' => $this->t('Year of publication. If field contains more than just a 4 digit year it will extract the first 4 digit number from the field. If the date is an EDTF date, like 199X, it will replace the X\'s with 0\'s.'),
      '#type' => 'select',
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['year'],
      '#required' => TRUE,
    ];
    $form['rtypeGeneral'] = [
      '#title' => $this->t('Resource Type General'),
      '#description' => $this->t('General resource type. If your selected type is not in DataCite\'s list, it will be set to "Other".'),
      '#type' => 'select',
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['rtypeGeneral'],
      '#required' => TRUE,
    ];
    $form['rtype'] = [
      '#title' => $this->t('Resource Type'),
      '#description' => $this->t('Resource type.'),
      '#type' => 'select',
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['rtype'],
    ];
    $form['subject'] = [
      '#title' => $this->t('Subject(s)'),
      '#description' => $this->t('Subject(s) for the resource.'),
      '#type' => 'select',
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['subject'],
    ];
    $contributor_type_options = array_combine(DataciteVocabularies::CONTRIBUTOR_TYPES, DataciteVocabularies::CONTRIBUTOR_TYPES);

    $form['contributors'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contributors (Fixed Type)'),
      '#description' => $this->t('Each entry picks a Drupal field and a single contributor type applied to every value in that field. If a value is a taxonomy term and the taxonomy has a URL field called field_ror or field_orcid, that value is automatically pulled as well. Use this for contributors that are always one type, e.g. a hosting institution field (always "HostingInstitution") or a thesis supervisor field (always "Supervisor").'),
      '#prefix' => '<div id="contributors-wrapper">',
      '#suffix' => '</div>',
    ];

    $contributor_count = $form_state->get('contributor_count');
    $contributor_values = $form_state->get('contributor_values');

    if ($contributor_count === NULL || $contributor_values === NULL) {
      $saved = $this->configuration['contributors'] ?? [];
      $contributor_values = !empty($saved) ? $saved : [[]];
      $form_state->set('contributor_values', $contributor_values);
      $form_state->set('contributor_count', count($contributor_values));
      $contributor_count = count($contributor_values);
    }

    $form['contributors'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contributors (Fixed Type)'),
      '#description' => $this->t('Each entry picks a Drupal field and a single contributor type applied to every value in that field. If a value is a taxonomy term and the taxonomy has a URL field called field_ror or field_orcid, that value is automatically pulled as well. Use this for contributors that are always one type, e.g. a hosting institution field (always "HostingInstitution") or a thesis supervisor field (always "Supervisor").'),
      '#prefix' => '<div id="contributors-wrapper">',
      '#suffix' => '</div>',
    ];

    for ($i = 0; $i < $contributor_count; $i++) {
      $saved_value = $contributor_values[$i] ?? [];
      $form['contributors'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Contributor @num', ['@num' => $i + 1]),
      ];
      $form['contributors'][$i]['field'] = [
        '#type' => 'select',
        '#title' => $this->t('Field'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['field'] ?? '',
      ];
      $form['contributors'][$i]['contributor_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Contributor Type'),
        '#options' => $contributor_type_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['contributor_type'] ?? '',
      ];
      $form['contributors'][$i]['name_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Name Type'),
        '#description' => $this->t('Left unset, the nameType attribute is omitted (unknown).'),
        '#options' => $name_type_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['name_type'] ?? '',
      ];
      if ($contributor_count > 1) {
        $form['contributors'][$i]['remove_contributor'] = [
          '#type' => 'button',
          '#value' => $this->t('Remove'),
          '#name' => 'remove_contributor_' . $i,
          '#ajax' => [
            'callback' => [$this, 'addContributorCallback'],
            'wrapper' => 'contributors-wrapper',
            'event' => 'click',
          ],
          '#executes_submit_callback' => TRUE,
          '#submit' => [[$this, 'removeContributorSubmit']],
          '#limit_validation_errors' => [['data', 'contributors']],
        ];
      }
    }

    $form['contributors']['add_contributor'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another contributor'),
      '#submit' => [[$this, 'addContributorSubmit']],
      '#ajax' => [
        'callback' => [$this, 'addContributorCallback'],
        'wrapper' => 'contributors-wrapper',
      ],
      '#limit_validation_errors' => [['data', 'contributors']],
    ];

    $form['contributor'] = [
      '#prefix' => Markup::create('<fieldset><legend>' . $this->t('Contributor (Typed Relation)') . '</legend>'),
      '#title' => $this->t('Contributor(s) (Typed Relation)'),
      '#description' => $this->t('Use this for a typed relation field to a person taxonomy term, where the contributor type varies per value (mapped from the field\'s relation type to a DataCite contributorType; unrecognized types fall back to "Other"). If the term has a URL field called field_orcid, that value is automatically pulled as well.'),
      '#type' => 'select',
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['contributor'],
    ];
    $form['contributorNameType'] = [
      '#suffix' => Markup::create('</fieldset>'),
      '#title' => $this->t('Typed Relation Contributor Name Type'),
      '#description' => $this->t('Applied to every value from the Contributor(s) (Typed Relation) field above. Left unset, the nameType attribute is omitted (unknown).'),
      '#type' => 'select',
      '#options' => $name_type_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['contributorNameType'],
    ];
    $date_type_options = array_combine(DataciteVocabularies::DATE_TYPES, DataciteVocabularies::DATE_TYPES);

    $form['dates'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Dates'),
      '#prefix' => '<div id="dates-wrapper">',
      '#suffix' => '</div>',
    ];

    $date_count = $form_state->get('date_count');
    $date_values = $form_state->get('date_values');

    if ($date_count === NULL || $date_values === NULL) {
      $saved = $this->configuration['dates'] ?? [];
      $date_values = !empty($saved) ? $saved : [[]];
      $form_state->set('date_values', $date_values);
      $form_state->set('date_count', count($date_values));
      $date_count = count($date_values);
    }

    $form['dates'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Dates'),
      '#prefix' => '<div id="dates-wrapper">',
      '#suffix' => '</div>',
    ];

    for ($i = 0; $i < $date_count; $i++) {
      $saved_value = $date_values[$i] ?? [];
      $form['dates'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Date @num', ['@num' => $i + 1]),
      ];
      $form['dates'][$i]['date_value'] = [
        '#type' => 'select',
        '#title' => $this->t('Date'),
        '#description' => $this->t('X\'s in the date will be replaced with 0\'s. If the result doesn\'t match the format YYYY-MM-DD, just the first 4 digit year will be used, and the full original text will be added to the dateInformation attribute.'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['date_value'] ?? '',
      ];
      $form['dates'][$i]['date_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Date Type'),
        '#options' => $date_type_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['date_type'] ?? '',
      ];
      if ($date_count > 1) {
        $form['dates'][$i]['remove_date'] = [
          '#type' => 'button',
          '#value' => $this->t('Remove'),
          '#name' => 'remove_date_' . $i,
          '#ajax' => [
            'callback' => [$this, 'addDateCallback'],
            'wrapper' => 'dates-wrapper',
            'event' => 'click',
          ],
          '#executes_submit_callback' => TRUE,
          '#submit' => [[$this, 'removeDateSubmit']],
          '#limit_validation_errors' => [['data', 'dates']],
        ];
      }
    }

    $form['dates']['add_date'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another date'),
      '#submit' => [[$this, 'addDateSubmit']],
      '#ajax' => [
        'callback' => [$this, 'addDateCallback'],
        'wrapper' => 'dates-wrapper',
      ],
      '#limit_validation_errors' => [['data', 'dates']],
    ];

    $form['language'] = [
      '#title' => $this->t('Language'),
      '#description' => $this->t('The primary language of the resource.'),
      '#type' => 'select',
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['language'],
    ];
    $form['identifiers'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Identifiers'),
      '#prefix' => '<div id="identifiers-wrapper">',
      '#suffix' => '</div>',
    ];

    $identifier_count = $form_state->get('identifier_count');
    $identifier_values = $form_state->get('identifier_values');

    if ($identifier_count === NULL || $identifier_values === NULL) {
      $saved = $this->configuration['identifiers'] ?? [];
      $identifier_values = !empty($saved) ? $saved : [[]];
      $form_state->set('identifier_values', $identifier_values);
      $form_state->set('identifier_count', count($identifier_values));
      $identifier_count = count($identifier_values);
    }

    $form['identifiers'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Identifiers'),
      '#prefix' => '<div id="identifiers-wrapper">',
      '#suffix' => '</div>',
    ];

    for ($i = 0; $i < $identifier_count; $i++) {
      $saved_value = $identifier_values[$i] ?? [];
      $form['identifiers'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Identifier @num', ['@num' => $i + 1]),
      ];
      $form['identifiers'][$i]['identifier_type'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Type'),
        '#default_value' => $saved_value['identifier_type'] ?? '',
      ];
      $form['identifiers'][$i]['identifier_value'] = [
        '#type' => 'select',
        '#title' => $this->t('Value'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['identifier_value'] ?? '',
      ];
      if ($identifier_count > 1) {
        $form['identifiers'][$i]['remove_identifier'] = [
          '#type' => 'button',
          '#value' => $this->t('Remove'),
          '#name' => 'remove_identifier_' . $i,
          '#ajax' => [
            'callback' => [$this, 'addIdentifierCallback'],
            'wrapper' => 'identifiers-wrapper',
            'event' => 'click',
          ],
          '#executes_submit_callback' => TRUE,
          '#submit' => [[$this, 'removeIdentifierSubmit']],
          '#limit_validation_errors' => [['data', 'identifiers']],
        ];
      }
    }

    $form['identifiers']['add_identifier'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another identifier'),
      '#submit' => [[$this, 'addIdentifierSubmit']],
      '#ajax' => [
        'callback' => [$this, 'addIdentifierCallback'],
        'wrapper' => 'identifiers-wrapper',
      ],
      '#limit_validation_errors' => [['data', 'identifiers']],
    ];

    $relation_type_options = array_combine(DataciteVocabularies::RELATION_TYPES, DataciteVocabularies::RELATION_TYPES);
    $related_identifier_type_options = array_combine(DataciteVocabularies::IDENTIFIER_TYPES, DataciteVocabularies::IDENTIFIER_TYPES);
    $related_identifier_resource_type_options = array_combine(DataciteVocabularies::RESOURCE_TYPES, DataciteVocabularies::RESOURCE_TYPES);

    $form['relatedIdentifiers'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Related Identifiers'),
      '#prefix' => '<div id="related-identifiers-wrapper">',
      '#suffix' => '</div>',
    ];

    $related_identifier_count = $form_state->get('related_identifier_count');
    $related_identifier_values = $form_state->get('related_identifier_values');

    if ($related_identifier_count === NULL || $related_identifier_values === NULL) {
      $saved = $this->configuration['relatedIdentifiers'] ?? [];
      $related_identifier_values = !empty($saved) ? $saved : [[]];
      $form_state->set('related_identifier_values', $related_identifier_values);
      $form_state->set('related_identifier_count', count($related_identifier_values));
      $related_identifier_count = count($related_identifier_values);
    }

    $form['relatedIdentifiers'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Related Identifiers'),
      '#prefix' => '<div id="related-identifiers-wrapper">',
      '#suffix' => '</div>',
    ];

    for ($i = 0; $i < $related_identifier_count; $i++) {
      $saved_value = $related_identifier_values[$i] ?? [];
      $form['relatedIdentifiers'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Related Identifier @num', ['@num' => $i + 1]),
      ];
      $form['relatedIdentifiers'][$i]['relation_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Relation Type'),
        '#description' => $this->t('How the resource relates to this identifier, e.g. "IsIdenticalTo" for a publisher\'s DOI of the same object.'),
        '#options' => $relation_type_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['relation_type'] ?? '',
      ];
      $form['relatedIdentifiers'][$i]['identifier_value'] = [
        '#type' => 'select',
        '#title' => $this->t('Identifier'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['identifier_value'] ?? '',
      ];
      $form['relatedIdentifiers'][$i]['identifier_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Identifier Type'),
        '#options' => $related_identifier_type_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['identifier_type'] ?? '',
      ];
      $form['relatedIdentifiers'][$i]['resource_type_general'] = [
        '#type' => 'select',
        '#title' => $this->t('Resource Type General'),
        '#description' => $this->t('The general resource type of the identified item, if known.'),
        '#options' => $related_identifier_resource_type_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['resource_type_general'] ?? '',
      ];
      if ($related_identifier_count > 1) {
        $form['relatedIdentifiers'][$i]['remove_related_identifier'] = [
          '#type' => 'button',
          '#value' => $this->t('Remove'),
          '#name' => 'remove_related_identifier_' . $i,
          '#ajax' => [
            'callback' => [$this, 'addRelatedIdentifierCallback'],
            'wrapper' => 'related-identifiers-wrapper',
            'event' => 'click',
          ],
          '#executes_submit_callback' => TRUE,
          '#submit' => [[$this, 'removeRelatedIdentifierSubmit']],
          '#limit_validation_errors' => [['data', 'relatedIdentifiers']],
        ];
      }
    }

    $form['relatedIdentifiers']['add_related_identifier'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another related identifier'),
      '#submit' => [[$this, 'addRelatedIdentifierSubmit']],
      '#ajax' => [
        'callback' => [$this, 'addRelatedIdentifierCallback'],
        'wrapper' => 'related-identifiers-wrapper',
      ],
      '#limit_validation_errors' => [['data', 'relatedIdentifiers']],
    ];

    $form['size'] = [
      '#title' => $this->t('Size(s)'),
      '#description' => $this->t('Size(s) of the resource, e.g. "90 pages" or "1 MB".'),
      '#type' => 'select',
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['size'],
    ];
    $form['format'] = [
      '#title' => $this->t('Format(s)'),
      '#description' => $this->t('Technical format(s) of the resource, e.g. a MIME type like "application/pdf".'),
      '#type' => 'select',
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['format'],
    ];
    $form['version'] = [
      '#title' => $this->t('Version'),
      '#description' => $this->t('Version number of the resource, e.g. "1.0".'),
      '#type' => 'select',
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['version'],
    ];
    $form['rights'] = [
      '#title' => $this->t('Rights'),
      '#description' => $this->t('Rights information for the resource.'),
      '#type' => 'select',
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['rights'],
    ];
    $description_type_options = array_combine(DataciteVocabularies::DESCRIPTION_TYPES, DataciteVocabularies::DESCRIPTION_TYPES);

    $form['descriptions'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Descriptions'),
      '#prefix' => '<div id="descriptions-wrapper">',
      '#suffix' => '</div>',
    ];

    $description_count = $form_state->get('description_count');
    $description_values = $form_state->get('description_values');

    if ($description_count === NULL || $description_values === NULL) {
      $saved = $this->configuration['descriptions'] ?? [];
      $description_values = !empty($saved) ? $saved : [[]];
      $form_state->set('description_values', $description_values);
      $form_state->set('description_count', count($description_values));
      $description_count = count($description_values);
    }

    $form['descriptions'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Descriptions'),
      '#prefix' => '<div id="descriptions-wrapper">',
      '#suffix' => '</div>',
    ];

    for ($i = 0; $i < $description_count; $i++) {
      $saved_value = $description_values[$i] ?? [];
      $form['descriptions'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Description @num', ['@num' => $i + 1]),
      ];
      $form['descriptions'][$i]['description_value'] = [
        '#type' => 'select',
        '#title' => $this->t('Description'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['description_value'] ?? '',
      ];
      $form['descriptions'][$i]['description_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Description Type'),
        '#options' => $description_type_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['description_type'] ?? '',
      ];
      if ($description_count > 1) {
        $form['descriptions'][$i]['remove_description'] = [
          '#type' => 'button',
          '#value' => $this->t('Remove'),
          '#name' => 'remove_description_' . $i,
          '#ajax' => [
            'callback' => [$this, 'addDescriptionCallback'],
            'wrapper' => 'descriptions-wrapper',
            'event' => 'click',
          ],
          '#executes_submit_callback' => TRUE,
          '#submit' => [[$this, 'removeDescriptionSubmit']],
          '#limit_validation_errors' => [['data', 'descriptions']],
        ];
      }
    }

    $form['descriptions']['add_description'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another description'),
      '#submit' => [[$this, 'addDescriptionSubmit']],
      '#ajax' => [
        'callback' => [$this, 'addDescriptionCallback'],
        'wrapper' => 'descriptions-wrapper',
      ],
      '#limit_validation_errors' => [['data', 'descriptions']],
    ];

    $form['geoLocations'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Geographic Locations'),
      '#description' => $this->t('Each entry becomes one DataCite geoLocation. A place name and a point are independent — fill in either or both. If you fill in both on the same entry, they are combined together in that one geoLocation; add separate entries if you want an unpaired place and point instead.'),
      '#prefix' => '<div id="geolocations-wrapper">',
      '#suffix' => '</div>',
    ];

    $geolocation_count = $form_state->get('geolocation_count');
    $geolocation_values = $form_state->get('geolocation_values');

    if ($geolocation_count === NULL || $geolocation_values === NULL) {
      $saved = $this->configuration['geoLocations'] ?? [];
      $geolocation_values = !empty($saved) ? $saved : [[]];
      $form_state->set('geolocation_values', $geolocation_values);
      $form_state->set('geolocation_count', count($geolocation_values));
      $geolocation_count = count($geolocation_values);
    }

    $form['geoLocations'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Geographic Locations'),
      '#description' => $this->t('Each entry becomes one DataCite geoLocation. A place name and a point are independent — fill in either or both. If you fill in both on the same entry, they are combined together in that one geoLocation; add separate entries if you want an unpaired place and point instead.'),
      '#prefix' => '<div id="geolocations-wrapper">',
      '#suffix' => '</div>',
    ];

    for ($i = 0; $i < $geolocation_count; $i++) {
      $saved_value = $geolocation_values[$i] ?? [];
      $form['geoLocations'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Geographic Location @num', ['@num' => $i + 1]),
      ];
      $form['geoLocations'][$i]['place'] = [
        '#type' => 'select',
        '#title' => $this->t('Place Name'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['place'] ?? '',
      ];
      $form['geoLocations'][$i]['point'] = [
        '#type' => 'select',
        '#title' => $this->t('Point'),
        '#description' => $this->t('A Geolocation-module field holding a latitude/longitude point.'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['point'] ?? '',
      ];
      if ($geolocation_count > 1) {
        $form['geoLocations'][$i]['remove_geolocation'] = [
          '#type' => 'button',
          '#value' => $this->t('Remove'),
          '#name' => 'remove_geolocation_' . $i,
          '#ajax' => [
            'callback' => [$this, 'addGeoLocationCallback'],
            'wrapper' => 'geolocations-wrapper',
            'event' => 'click',
          ],
          '#executes_submit_callback' => TRUE,
          '#submit' => [[$this, 'removeGeoLocationSubmit']],
          '#limit_validation_errors' => [['data', 'geoLocations']],
        ];
      }
    }

    $form['geoLocations']['add_geolocation'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another geographic location'),
      '#submit' => [[$this, 'addGeoLocationSubmit']],
      '#ajax' => [
        'callback' => [$this, 'addGeoLocationCallback'],
        'wrapper' => 'geolocations-wrapper',
      ],
      '#limit_validation_errors' => [['data', 'geoLocations']],
    ];

    $funder_identifier_type_options = array_combine(DataciteVocabularies::FUNDER_IDENTIFIER_TYPES, DataciteVocabularies::FUNDER_IDENTIFIER_TYPES);

    $form['funderName'] = [
      '#prefix' => Markup::create('<fieldset><legend>' . $this->t('Funder(s)') . '</legend><div class="description">' . $this->t('Pick fields from within a paragraph field (e.g. "field_funder (paragraph) → field_funder_name"). All of these should come from the same paragraph field so they stay paired to the same funder.') . '</div>'),
      '#title' => $this->t('Funder Name'),
      '#type' => 'select',
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['funderName'],
    ];
    $form['funderIdentifier'] = [
      '#title' => $this->t('Funder Identifier'),
      '#description' => $this->t('An external identifier for the funder itself, e.g. a ROR or Crossref Funder ID.'),
      '#type' => 'select',
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['funderIdentifier'],
    ];
    $form['funderIdentifierType'] = [
      '#title' => $this->t('Funder Identifier Type'),
      '#type' => 'select',
      '#options' => $funder_identifier_type_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['funderIdentifierType'],
    ];
    $form['funderAwardNumber'] = [
      '#title' => $this->t('Award Number'),
      '#type' => 'select',
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['funderAwardNumber'],
    ];
    $form['funderAwardURI'] = [
      '#title' => $this->t('Award URI'),
      '#description' => $this->t('A URI for the award selected above.'),
      '#type' => 'select',
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['funderAwardURI'],
    ];
    $form['funderAwardTitle'] = [
      '#suffix' => Markup::create('</fieldset>'),
      '#title' => $this->t('Award Title'),
      '#type' => 'select',
      '#options' => $field_options,
      '#empty_option' => $this->t('- None -'),
      '#default_value' => $this->configuration['funderAwardTitle'],
    ];

    $related_item_type_options = array_combine(DataciteVocabularies::RESOURCE_TYPES, DataciteVocabularies::RESOURCE_TYPES);
    $number_type_options = array_combine(DataciteVocabularies::NUMBER_TYPES, DataciteVocabularies::NUMBER_TYPES);
    $contributor_type_options = array_combine(DataciteVocabularies::CONTRIBUTOR_TYPES, DataciteVocabularies::CONTRIBUTOR_TYPES);

    $form['relatedItems'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Related Items'),
      '#prefix' => '<div id="related-items-wrapper">',
      '#suffix' => '</div>',
    ];

    $related_item_count = $form_state->get('related_item_count');
    $related_item_values = $form_state->get('related_item_values');

    if ($related_item_count === NULL || $related_item_values === NULL) {
      $saved = $this->configuration['relatedItems'] ?? [];
      $related_item_values = !empty($saved) ? $saved : [[]];
      $form_state->set('related_item_values', $related_item_values);
      $form_state->set('related_item_count', count($related_item_values));
      $related_item_count = count($related_item_values);
    }

    $form['relatedItems'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Related Items'),
      '#prefix' => '<div id="related-items-wrapper">',
      '#suffix' => '</div>',
    ];

    for ($i = 0; $i < $related_item_count; $i++) {
      $saved_value = $related_item_values[$i] ?? [];
      $form['relatedItems'][$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Related Item @num', ['@num' => $i + 1]),
      ];
      $form['relatedItems'][$i]['relation_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Relation Type'),
        '#description' => $this->t('How the resource relates to this related item, e.g. "IsPublishedIn" for a journal, "Reviews" for a book being reviewed.'),
        '#options' => $relation_type_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['relation_type'] ?? '',
      ];
      $form['relatedItems'][$i]['related_item_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Related Item Type'),
        '#options' => $related_item_type_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['related_item_type'] ?? '',
      ];
      $form['relatedItems'][$i]['identifier_value'] = [
        '#type' => 'select',
        '#title' => $this->t('Identifier'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['identifier_value'] ?? '',
      ];
      $form['relatedItems'][$i]['related_identifier_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Related Identifier Type'),
        '#description' => $this->t('The type of the identifier selected above, e.g. "ISSN" or "DOI".'),
        '#options' => $related_identifier_type_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['related_identifier_type'] ?? '',
      ];
      $form['relatedItems'][$i]['creators'] = [
        '#prefix' => Markup::create('<fieldset><legend>' . $this->t('Creator') . '</legend>'),
        '#type' => 'select',
        '#title' => $this->t('Creator(s)'),
        '#description' => $this->t('Field holding the related item\'s creator(s). If the field has multiple values, each becomes a separate creator.'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['creators'] ?? '',
      ];
      $form['relatedItems'][$i]['creators_name_type'] = [
        '#suffix' => Markup::create('</fieldset>'),
        '#type' => 'select',
        '#title' => $this->t('Creator Name Type'),
        '#description' => $this->t('Applied to every value in the Creator(s) field above. Left unset, the nameType attribute is omitted (unknown).'),
        '#options' => $name_type_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['creators_name_type'] ?? '',
      ];
      $form['relatedItems'][$i]['title'] = [
        '#type' => 'select',
        '#title' => $this->t('Title'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['title'] ?? '',
      ];
      $form['relatedItems'][$i]['publication_year'] = [
        '#type' => 'select',
        '#title' => $this->t('Publication Year'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['publication_year'] ?? '',
      ];
      $form['relatedItems'][$i]['volume'] = [
        '#type' => 'select',
        '#title' => $this->t('Volume'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['volume'] ?? '',
      ];
      $form['relatedItems'][$i]['issue'] = [
        '#type' => 'select',
        '#title' => $this->t('Issue'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['issue'] ?? '',
      ];
      $form['relatedItems'][$i]['number'] = [
        '#prefix' => Markup::create('<fieldset><legend>' . $this->t('Number') . '</legend>'),
        '#type' => 'select',
        '#title' => $this->t('Number'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['number'] ?? '',
      ];
      $form['relatedItems'][$i]['number_type'] = [
        '#suffix' => Markup::create('</fieldset>'),
        '#type' => 'select',
        '#title' => $this->t('Number Type'),
        '#description' => $this->t('What kind of number is selected above, e.g. article or report number.'),
        '#options' => $number_type_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['number_type'] ?? '',
      ];
      $form['relatedItems'][$i]['first_page'] = [
        '#type' => 'select',
        '#title' => $this->t('First Page'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['first_page'] ?? '',
      ];
      $form['relatedItems'][$i]['last_page'] = [
        '#type' => 'select',
        '#title' => $this->t('Last Page'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['last_page'] ?? '',
      ];
      $form['relatedItems'][$i]['publisher'] = [
        '#type' => 'select',
        '#title' => $this->t('Publisher'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['publisher'] ?? '',
      ];
      $form['relatedItems'][$i]['edition'] = [
        '#type' => 'select',
        '#title' => $this->t('Edition'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['edition'] ?? '',
      ];
      $form['relatedItems'][$i]['contributors'] = [
        '#prefix' => Markup::create('<fieldset><legend>' . $this->t('Contributor (Fixed Type)') . '</legend>'),
        '#type' => 'select',
        '#title' => $this->t('Contributor(s)'),
        '#description' => $this->t('Field holding the related item\'s contributor(s). If the field has multiple values, each becomes a separate contributor.'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['contributors'] ?? '',
      ];
      $form['relatedItems'][$i]['contributor_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Contributor Type'),
        '#description' => $this->t('Contributor type applied to every value in the Contributor(s) field above.'),
        '#options' => $contributor_type_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['contributor_type'] ?? '',
      ];
      $form['relatedItems'][$i]['contributors_name_type'] = [
        '#suffix' => Markup::create('</fieldset>'),
        '#type' => 'select',
        '#title' => $this->t('Contributor Name Type'),
        '#description' => $this->t('Applied to every value in the Contributor(s) field above. Left unset, the nameType attribute is omitted (unknown).'),
        '#options' => $name_type_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['contributors_name_type'] ?? '',
      ];
      $form['relatedItems'][$i]['typed_contributors'] = [
        '#prefix' => Markup::create('<fieldset><legend>' . $this->t('Contributor (Typed Relation)') . '</legend>'),
        '#type' => 'select',
        '#title' => $this->t('Contributor(s)'),
        '#description' => $this->t('A typed relation field to a person taxonomy term, where the contributor type varies per value (mapped from the field\'s relation type to a DataCite contributorType; unrecognized types fall back to "Other"). If the term has a URL field called field_orcid, that value is automatically pulled as well.'),
        '#options' => $field_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['typed_contributors'] ?? '',
      ];
      $form['relatedItems'][$i]['typed_contributors_name_type'] = [
        '#suffix' => Markup::create('</fieldset>'),
        '#type' => 'select',
        '#title' => $this->t('Contributor Name Type'),
        '#description' => $this->t('Applied to every value in the Contributor(s) field above. Left unset, the nameType attribute is omitted (unknown).'),
        '#options' => $name_type_options,
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $saved_value['typed_contributors_name_type'] ?? '',
      ];
      if ($related_item_count > 1) {
        $form['relatedItems'][$i]['remove_related_item'] = [
          '#type' => 'button',
          '#value' => $this->t('Remove'),
          '#name' => 'remove_related_item_' . $i,
          '#ajax' => [
            'callback' => [$this, 'addRelatedItemCallback'],
            'wrapper' => 'related-items-wrapper',
            'event' => 'click',
          ],
          '#executes_submit_callback' => TRUE,
          '#submit' => [[$this, 'removeRelatedItemSubmit']],
          '#limit_validation_errors' => [['data', 'relatedItems']],
        ];
      }
    }

    $form['relatedItems']['add_related_item'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another related item'),
      '#submit' => [[$this, 'addRelatedItemSubmit']],
      '#ajax' => [
        'callback' => [$this, 'addRelatedItemCallback'],
        'wrapper' => 'related-items-wrapper',
      ],
      '#limit_validation_errors' => [['data', 'relatedItems']],
    ];

    return $form;
  }


  public function addIdentifierSubmit(array &$form, FormStateInterface $form_state): void {
    $existing = $form_state->getValue(['data', 'identifiers']) ?? [];
    $values = [];
    foreach ($existing as $key => $item) {
      if (is_int($key)) {
        $values[] = [
          'identifier_type' => $item['identifier_type'] ?? '',
          'identifier_value' => $item['identifier_value'] ?? '',
        ];
      }
    }
    $values[] = [];
    $form_state->set('identifier_values', $values);
    $form_state->set('identifier_count', count($values));
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for the "Add another identifier" button.
   */
  public function addIdentifierCallback(array &$form, FormStateInterface $form_state): array {
    return $form['entity_fieldset']['bundle_fieldset_container']['bundle_fieldset']['dataprofile_fieldset_container']['dataprofile_fieldset']['dataprofile_fields_fieldset_container']['fields_fieldset']['data']['identifiers'];
  }

  /**
   * Submit handler for the "Remove" identifier button.
   */
  public function removeIdentifierSubmit(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $index = (int) str_replace('remove_identifier_', '', $trigger['#name']);

    $existing = $form_state->getValue(['data', 'identifiers']) ?? [];
    $values = [];
    foreach ($existing as $key => $item) {
      if (is_int($key)) {
        $values[] = [
          'identifier_type' => $item['identifier_type'] ?? '',
          'identifier_value' => $item['identifier_value'] ?? '',
        ];
      }
    }

    unset($values[$index]);
    $values = array_values($values);

    $user_input = $form_state->getUserInput();
    unset($user_input['data']['identifiers']);
    $form_state->setUserInput($user_input);

    $form_state->set('identifier_values', $values);
    $form_state->set('identifier_count', count($values));
    $form_state->setRebuild();
  }

  /**
   * Extracts the geolocation sub-field values from a submitted form item.
   */
  private function extractGeoLocationValues(array $item): array {
    $values = [];
    foreach (self::GEOLOCATION_KEYS as $key) {
      $values[$key] = $item[$key] ?? '';
    }
    return $values;
  }

  public function addGeoLocationSubmit(array &$form, FormStateInterface $form_state): void {
    $existing = $form_state->getValue(['data', 'geoLocations']) ?? [];
    $values = [];
    foreach ($existing as $key => $item) {
      if (is_int($key)) {
        $values[] = $this->extractGeoLocationValues($item);
      }
    }
    $values[] = [];
    $form_state->set('geolocation_values', $values);
    $form_state->set('geolocation_count', count($values));
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for the "Add another geographic location" button.
   */
  public function addGeoLocationCallback(array &$form, FormStateInterface $form_state): array {
    return $form['entity_fieldset']['bundle_fieldset_container']['bundle_fieldset']['dataprofile_fieldset_container']['dataprofile_fieldset']['dataprofile_fields_fieldset_container']['fields_fieldset']['data']['geoLocations'];
  }

  /**
   * Submit handler for the "Remove" geolocation button.
   */
  public function removeGeoLocationSubmit(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $index = (int) str_replace('remove_geolocation_', '', $trigger['#name']);

    $existing = $form_state->getValue(['data', 'geoLocations']) ?? [];
    $values = [];
    foreach ($existing as $key => $item) {
      if (is_int($key)) {
        $values[] = $this->extractGeoLocationValues($item);
      }
    }

    unset($values[$index]);
    $values = array_values($values);

    $user_input = $form_state->getUserInput();
    unset($user_input['data']['geoLocations']);
    $form_state->setUserInput($user_input);

    $form_state->set('geolocation_values', $values);
    $form_state->set('geolocation_count', count($values));
    $form_state->setRebuild();
  }

  /**
   * Extracts the fixed-type contributor sub-field values from a submitted
   * form item.
   */
  private function extractContributorValues(array $item): array {
    $values = [];
    foreach (self::CONTRIBUTOR_KEYS as $key) {
      $values[$key] = $item[$key] ?? '';
    }
    return $values;
  }

  public function addContributorSubmit(array &$form, FormStateInterface $form_state): void {
    $existing = $form_state->getValue(['data', 'contributors']) ?? [];
    $values = [];
    foreach ($existing as $key => $item) {
      if (is_int($key)) {
        $values[] = $this->extractContributorValues($item);
      }
    }
    $values[] = [];
    $form_state->set('contributor_values', $values);
    $form_state->set('contributor_count', count($values));
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for the "Add another contributor" button.
   */
  public function addContributorCallback(array &$form, FormStateInterface $form_state): array {
    return $form['entity_fieldset']['bundle_fieldset_container']['bundle_fieldset']['dataprofile_fieldset_container']['dataprofile_fieldset']['dataprofile_fields_fieldset_container']['fields_fieldset']['data']['contributors'];
  }

  /**
   * Submit handler for the "Remove" contributor button.
   */
  public function removeContributorSubmit(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $index = (int) str_replace('remove_contributor_', '', $trigger['#name']);

    $existing = $form_state->getValue(['data', 'contributors']) ?? [];
    $values = [];
    foreach ($existing as $key => $item) {
      if (is_int($key)) {
        $values[] = $this->extractContributorValues($item);
      }
    }

    unset($values[$index]);
    $values = array_values($values);

    $user_input = $form_state->getUserInput();
    unset($user_input['data']['contributors']);
    $form_state->setUserInput($user_input);

    $form_state->set('contributor_values', $values);
    $form_state->set('contributor_count', count($values));
    $form_state->setRebuild();
  }

  /**
   * Extracts the title sub-field values from a submitted form item.
   */
  private function extractTitleValues(array $item): array {
    $values = [];
    foreach (self::TITLE_KEYS as $key) {
      $values[$key] = $item[$key] ?? '';
    }
    return $values;
  }

  public function addTitleSubmit(array &$form, FormStateInterface $form_state): void {
    $existing = $form_state->getValue(['data', 'titles']) ?? [];
    $values = [];
    foreach ($existing as $key => $item) {
      if (is_int($key)) {
        $values[] = $this->extractTitleValues($item);
      }
    }
    $values[] = [];
    $form_state->set('title_values', $values);
    $form_state->set('title_count', count($values));
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for the "Add another title" button.
   */
  public function addTitleCallback(array &$form, FormStateInterface $form_state): array {
    return $form['entity_fieldset']['bundle_fieldset_container']['bundle_fieldset']['dataprofile_fieldset_container']['dataprofile_fieldset']['dataprofile_fields_fieldset_container']['fields_fieldset']['data']['titles'];
  }

  /**
   * Submit handler for the "Remove" title button.
   */
  public function removeTitleSubmit(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $index = (int) str_replace('remove_title_', '', $trigger['#name']);

    $existing = $form_state->getValue(['data', 'titles']) ?? [];
    $values = [];
    foreach ($existing as $key => $item) {
      if (is_int($key)) {
        $values[] = $this->extractTitleValues($item);
      }
    }

    unset($values[$index]);
    $values = array_values($values);

    $user_input = $form_state->getUserInput();
    unset($user_input['data']['titles']);
    $form_state->setUserInput($user_input);

    $form_state->set('title_values', $values);
    $form_state->set('title_count', count($values));
    $form_state->setRebuild();
  }

  /**
   * Extracts the description sub-field values from a submitted form item.
   */
  private function extractDescriptionValues(array $item): array {
    $values = [];
    foreach (self::DESCRIPTION_KEYS as $key) {
      $values[$key] = $item[$key] ?? '';
    }
    return $values;
  }

  public function addDescriptionSubmit(array &$form, FormStateInterface $form_state): void {
    $existing = $form_state->getValue(['data', 'descriptions']) ?? [];
    $values = [];
    foreach ($existing as $key => $item) {
      if (is_int($key)) {
        $values[] = $this->extractDescriptionValues($item);
      }
    }
    $values[] = [];
    $form_state->set('description_values', $values);
    $form_state->set('description_count', count($values));
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for the "Add another description" button.
   */
  public function addDescriptionCallback(array &$form, FormStateInterface $form_state): array {
    return $form['entity_fieldset']['bundle_fieldset_container']['bundle_fieldset']['dataprofile_fieldset_container']['dataprofile_fieldset']['dataprofile_fields_fieldset_container']['fields_fieldset']['data']['descriptions'];
  }

  /**
   * Submit handler for the "Remove" description button.
   */
  public function removeDescriptionSubmit(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $index = (int) str_replace('remove_description_', '', $trigger['#name']);

    $existing = $form_state->getValue(['data', 'descriptions']) ?? [];
    $values = [];
    foreach ($existing as $key => $item) {
      if (is_int($key)) {
        $values[] = $this->extractDescriptionValues($item);
      }
    }

    unset($values[$index]);
    $values = array_values($values);

    $user_input = $form_state->getUserInput();
    unset($user_input['data']['descriptions']);
    $form_state->setUserInput($user_input);

    $form_state->set('description_values', $values);
    $form_state->set('description_count', count($values));
    $form_state->setRebuild();
  }

  /**
   * Extracts the date sub-field values from a submitted form item.
   */
  private function extractDateValues(array $item): array {
    $values = [];
    foreach (self::DATE_KEYS as $key) {
      $values[$key] = $item[$key] ?? '';
    }
    return $values;
  }

  public function addDateSubmit(array &$form, FormStateInterface $form_state): void {
    $existing = $form_state->getValue(['data', 'dates']) ?? [];
    $values = [];
    foreach ($existing as $key => $item) {
      if (is_int($key)) {
        $values[] = $this->extractDateValues($item);
      }
    }
    $values[] = [];
    $form_state->set('date_values', $values);
    $form_state->set('date_count', count($values));
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for the "Add another date" button.
   */
  public function addDateCallback(array &$form, FormStateInterface $form_state): array {
    return $form['entity_fieldset']['bundle_fieldset_container']['bundle_fieldset']['dataprofile_fieldset_container']['dataprofile_fieldset']['dataprofile_fields_fieldset_container']['fields_fieldset']['data']['dates'];
  }

  /**
   * Submit handler for the "Remove" date button.
   */
  public function removeDateSubmit(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $index = (int) str_replace('remove_date_', '', $trigger['#name']);

    $existing = $form_state->getValue(['data', 'dates']) ?? [];
    $values = [];
    foreach ($existing as $key => $item) {
      if (is_int($key)) {
        $values[] = $this->extractDateValues($item);
      }
    }

    unset($values[$index]);
    $values = array_values($values);

    $user_input = $form_state->getUserInput();
    unset($user_input['data']['dates']);
    $form_state->setUserInput($user_input);

    $form_state->set('date_values', $values);
    $form_state->set('date_count', count($values));
    $form_state->setRebuild();
  }

  /**
   * Extracts the related identifier sub-field values from a submitted form
   * item.
   */
  private function extractRelatedIdentifierValues(array $item): array {
    $values = [];
    foreach (self::RELATED_IDENTIFIER_KEYS as $key) {
      $values[$key] = $item[$key] ?? '';
    }
    return $values;
  }

  public function addRelatedIdentifierSubmit(array &$form, FormStateInterface $form_state): void {
    $existing = $form_state->getValue(['data', 'relatedIdentifiers']) ?? [];
    $values = [];
    foreach ($existing as $key => $item) {
      if (is_int($key)) {
        $values[] = $this->extractRelatedIdentifierValues($item);
      }
    }
    $values[] = [];
    $form_state->set('related_identifier_values', $values);
    $form_state->set('related_identifier_count', count($values));
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for the "Add another related identifier" button.
   */
  public function addRelatedIdentifierCallback(array &$form, FormStateInterface $form_state): array {
    return $form['entity_fieldset']['bundle_fieldset_container']['bundle_fieldset']['dataprofile_fieldset_container']['dataprofile_fieldset']['dataprofile_fields_fieldset_container']['fields_fieldset']['data']['relatedIdentifiers'];
  }

  /**
   * Submit handler for the "Remove" related identifier button.
   */
  public function removeRelatedIdentifierSubmit(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $index = (int) str_replace('remove_related_identifier_', '', $trigger['#name']);

    $existing = $form_state->getValue(['data', 'relatedIdentifiers']) ?? [];
    $values = [];
    foreach ($existing as $key => $item) {
      if (is_int($key)) {
        $values[] = $this->extractRelatedIdentifierValues($item);
      }
    }

    unset($values[$index]);
    $values = array_values($values);

    $user_input = $form_state->getUserInput();
    unset($user_input['data']['relatedIdentifiers']);
    $form_state->setUserInput($user_input);

    $form_state->set('related_identifier_values', $values);
    $form_state->set('related_identifier_count', count($values));
    $form_state->setRebuild();
  }

  /**
   * Extracts the related item sub-field values from a submitted form item.
   */
  private function extractRelatedItemValues(array $item): array {
    $values = [];
    foreach (self::RELATED_ITEM_KEYS as $key) {
      $values[$key] = $item[$key] ?? '';
    }
    return $values;
  }

  public function addRelatedItemSubmit(array &$form, FormStateInterface $form_state): void {
    $existing = $form_state->getValue(['data', 'relatedItems']) ?? [];
    $values = [];
    foreach ($existing as $key => $item) {
      if (is_int($key)) {
        $values[] = $this->extractRelatedItemValues($item);
      }
    }
    $values[] = [];
    $form_state->set('related_item_values', $values);
    $form_state->set('related_item_count', count($values));
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for the "Add another related item" button.
   */
  public function addRelatedItemCallback(array &$form, FormStateInterface $form_state): array {
    return $form['entity_fieldset']['bundle_fieldset_container']['bundle_fieldset']['dataprofile_fieldset_container']['dataprofile_fieldset']['dataprofile_fields_fieldset_container']['fields_fieldset']['data']['relatedItems'];
  }

  /**
   * Submit handler for the "Remove" related item button.
   */
  public function removeRelatedItemSubmit(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $index = (int) str_replace('remove_related_item_', '', $trigger['#name']);

    $existing = $form_state->getValue(['data', 'relatedItems']) ?? [];
    $values = [];
    foreach ($existing as $key => $item) {
      if (is_int($key)) {
        $values[] = $this->extractRelatedItemValues($item);
      }
    }

    unset($values[$index]);
    $values = array_values($values);

    $user_input = $form_state->getUserInput();
    unset($user_input['data']['relatedItems']);
    $form_state->setUserInput($user_input);

    $form_state->set('related_item_values', $values);
    $form_state->set('related_item_count', count($values));
    $form_state->setRebuild();
  }


  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['author'] = $form_state->getValue('author');
    $this->configuration['authorNameType'] = $form_state->getValue('authorNameType');
    $title_count = $form_state->get('title_count') ?? 1;
    $titles = [];
    for ($i = 0; $i < $title_count; $i++) {
      $item = $form_state->getValue(['titles', $i]) ?? [];
      $entry = $this->extractTitleValues($item);
      if (!empty($entry['title_value'])) {
        $titles[] = $entry;
      }
    }
    $this->configuration['titles'] = $titles;

    $this->configuration['publisher'] = $form_state->getValue('publisher');
    $this->configuration['year'] = $form_state->getValue('year');
    $this->configuration['rtypeGeneral'] = $form_state->getValue('rtypeGeneral');
    $this->configuration['rtype'] = $form_state->getValue('rtype');
    $this->configuration['subject'] = $form_state->getValue('subject');
    $contributor_count = $form_state->get('contributor_count') ?? 1;
    $contributors = [];
    for ($i = 0; $i < $contributor_count; $i++) {
      $item = $form_state->getValue(['contributors', $i]) ?? [];
      $entry = $this->extractContributorValues($item);
      if (!empty($entry['contributor_type']) && !empty($entry['field'])) {
        $contributors[] = $entry;
      }
    }
    $this->configuration['contributors'] = $contributors;

    $this->configuration['contributor'] = $form_state->getValue('contributor');
    $this->configuration['contributorNameType'] = $form_state->getValue('contributorNameType');

    $date_count = $form_state->get('date_count') ?? 1;
    $dates = [];
    for ($i = 0; $i < $date_count; $i++) {
      $item = $form_state->getValue(['dates', $i]) ?? [];
      $entry = $this->extractDateValues($item);
      if (!empty($entry['date_type']) && !empty($entry['date_value'])) {
        $dates[] = $entry;
      }
    }
    $this->configuration['dates'] = $dates;

    $this->configuration['language'] = $form_state->getValue('language');

    $identifier_count = $form_state->get('identifier_count') ?? 1;
    $identifiers = [];
    for ($i = 0; $i < $identifier_count; $i++) {
      $type = $form_state->getValue(['identifiers', $i, 'identifier_type']);
      $value = $form_state->getValue(['identifiers', $i, 'identifier_value']);
      if (!empty($value)) {
        $identifiers[] = [
          'identifier_type' => $type,
          'identifier_value' => $value,
        ];
      }
    }
    $this->configuration['identifiers'] = $identifiers;

    $related_identifier_count = $form_state->get('related_identifier_count') ?? 1;
    $relatedIdentifiers = [];
    for ($i = 0; $i < $related_identifier_count; $i++) {
      $item = $form_state->getValue(['relatedIdentifiers', $i]) ?? [];
      $entry = $this->extractRelatedIdentifierValues($item);
      if (!empty($entry['relation_type']) && !empty($entry['identifier_type']) && !empty($entry['identifier_value'])) {
        $relatedIdentifiers[] = $entry;
      }
    }
    $this->configuration['relatedIdentifiers'] = $relatedIdentifiers;

    $this->configuration['size'] = $form_state->getValue('size');
    $this->configuration['format'] = $form_state->getValue('format');
    $this->configuration['version'] = $form_state->getValue('version');
    $this->configuration['rights'] = $form_state->getValue('rights');

    $description_count = $form_state->get('description_count') ?? 1;
    $descriptions = [];
    for ($i = 0; $i < $description_count; $i++) {
      $item = $form_state->getValue(['descriptions', $i]) ?? [];
      $entry = $this->extractDescriptionValues($item);
      if (!empty($entry['description_type']) && !empty($entry['description_value'])) {
        $descriptions[] = $entry;
      }
    }
    $this->configuration['descriptions'] = $descriptions;

    $geolocation_count = $form_state->get('geolocation_count') ?? 1;
    $geoLocations = [];
    for ($i = 0; $i < $geolocation_count; $i++) {
      $item = $form_state->getValue(['geoLocations', $i]) ?? [];
      $entry = $this->extractGeoLocationValues($item);
      if (!empty($entry['place']) || !empty($entry['point'])) {
        $geoLocations[] = $entry;
      }
    }
    $this->configuration['geoLocations'] = $geoLocations;

    $this->configuration['funderName'] = $form_state->getValue('funderName');
    $this->configuration['funderIdentifier'] = $form_state->getValue('funderIdentifier');
    $this->configuration['funderIdentifierType'] = $form_state->getValue('funderIdentifierType');
    $this->configuration['funderAwardNumber'] = $form_state->getValue('funderAwardNumber');
    $this->configuration['funderAwardURI'] = $form_state->getValue('funderAwardURI');
    $this->configuration['funderAwardTitle'] = $form_state->getValue('funderAwardTitle');

    $related_item_count = $form_state->get('related_item_count') ?? 1;
    $relatedItems = [];
    for ($i = 0; $i < $related_item_count; $i++) {
      $item = $form_state->getValue(['relatedItems', $i]) ?? [];
      $entry = $this->extractRelatedItemValues($item);
      if (!empty($entry['relation_type']) && !empty($entry['related_item_type'])) {
        $relatedItems[] = $entry;
      }
    }
    $this->configuration['relatedItems'] = $relatedItems;
  }

}
