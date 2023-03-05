<?php

namespace Drupal\islandora_datacite_doi\Plugin\ServiceDataType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\dgi_actions\Plugin\ServiceDataTypeBase;
/**
 * Mints a DOI from Datacite.
 *
 * @ServiceDataType(
 *   id = "datacite_doi",
 *   label = @Translation("Datacite DOIe"),
 *   description = @Translation("Service information for Datacite DOIs.")
 * )
 */
class DataciteDOI extends ServiceDataTypeBase {


  /**
   * Datacite DOI service data plugin constructor.
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
  public function defaultConfiguration(): array {
    return [
      'use_https' => TRUE,
      'host_doi' => NULL,
      'host_mds' => NULL,
      'username' => NULL,
      'password' => NULL,
      'prefix' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['host_doi'] = [
      '#type' => 'url',
      '#title' => $this->t('Datacite DOI Service Host'),
      '#description' => $this->t('Host address for the Datacite DOI service endpoint.'),
      '#default_value' => $this->configuration['host_doi'],
      '#required' => TRUE,
    ];
    $form['host_mds'] = [
      '#type' => 'url',
      '#title' => $this->t('Datacite Metadata Service Host'),
      '#description' => $this->t('Host address for the Datacite Metadata service endpoint.'),
      '#default_value' => $this->configuration['host_mds'],
      '#required' => TRUE,
    ];
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#description' => $this->t('Username of the Datacite administrator.'),
      '#default_value' => $this->configuration['username'],
      '#required' => TRUE,
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#description' => $this->t('Password of the Datacite administrator.'),
      '#default_value' => $this->configuration['password'],
      '#required' => is_null($this->configuration['password']),
      '#placeholder' => $this->configuration['password'] ? '********' : '',
    ];
    $form['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix'),
      '#description' => $this->t('DOI prefix for this site. All generated DOIs will start with this'),
      '#default_value' => $this->configuration['prefix'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getStateKeys(): array {
    return [
      'username',
      'password',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['host_doi'] = $form_state->getValue('host_doi');
    $this->configuration['host_mds'] = $form_state->getValue('host_mds');
    $this->configuration['prefix'] = $form_state->getValue('prefix');
    $this->configuration['username'] = $form_state->getValue('username');
    $this->configuration['password'] = !empty($form_state->getValue('password')) ? $form_state->getValue('password') : $this->configuration['password'];
    // Handle the scenario where the user did not modify the password as this
    // gets stored on the entity.
    $form_state->setValue('password', $this->configuration['password']);
    $this->configuration['use_https'] = $form_state->getValue('use_https');
  }

}
