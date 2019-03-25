<?php

/**
 * @file
 * Contains \Drupal\islandora_datacite_doi\Form\IslandoraDataciteDoiAdminForm.
 */

namespace Drupal\islandora_datacite_doi\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class IslandoraDataciteDoiAdminForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'islandora_datacite_doi_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('islandora_datacite_doi.settings');

    foreach (Element::children($form) as $variable) {
      $config->set($variable, $form_state->getValue($form[$variable]['#parents']));
    }
    $config->save();

    if (method_exists($this, '_submitForm')) {
      $this->_submitForm($form, $form_state);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['islandora_datacite_doi.settings'];
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    //form_load_include($form_state, 'inc', 'islandora_pydio_bridge', 'includes/admin.form');

    $form = [];

    $form['islandora_datacite_doi_prefix'] = [
      '#type' => 'textfield',
      '#title' => t('The prefix provided by Datacite for your DOIs'),
      '#default_value' => variable_get('islandora_datacite_doi_prefix', 'doi_prefix'),
      '#description' => t('The prefix provided by Datacite for your DOIs'),
      '#required' => TRUE,
    ];
    $form['islandora_datacite_doi_site'] = [
      '#type' => 'textfield',
      '#title' => t('The site or application name'),
      '#default_value' => variable_get('islandora_datacite_doi_site', 'site_or_application'),
      '#description' => t('A local site or application name to use in the creation of the DOI.  ' . 'DOIs will be minted as such "prefix/site/pid" e.g. "10.11571/upei-roblib-data/pydio:25"'),
      '#required' => TRUE,
    ];

    $form['islandora_datacite_doi_username'] = [
      '#type' => 'textfield',
      '#title' => t('Your Datacite username'),
      '#default_value' => variable_get('islandora_datacite_doi_username', 'datacite_username'),
      '#description' => t('The pydio server url and path'),
      '#required' => TRUE,
    ];

    $form['islandora_datacite_doi_password'] = [
      '#type' => 'textfield',
      '#title' => t('Datacite Password'),
      '#default_value' => variable_get('islandora_datacite_doi_password', 'datacite password'),
      '#description' => t('The password to use for Datacite'),
      '#required' => TRUE,
    ];

    $form['islandora_pydio_datacite_saxon_path'] = [
      '#type' => 'textfield',
      '#title' => t('The path to the saxon.jar file'),
      '#default_value' => variable_get('islandora_pydio_datacite_saxon_path', '/opt/saxon/saxon9he.jar'),
      '#description' => t('The path to saxon for xslt 2.0 tranforms.  Used to transform DDIto Datacite xml.'),
      '#required' => TRUE,
    ];

    $form['islandora_datacite_doi_use_ssl'] = [
      '#type' => 'checkbox',
      '#title' => t('Should Datacite use https for links back to your site'),
      '#default_value' => variable_get('islandora_datacite_doi_use_ssl', 'FALSE'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

}
?>
