<?php

/**
 * @file
 * Form handling for the administration of Islandora Pydio Bridge.
 */

/**
 * Defines the Islandora Datacite DOI admin form.
 *
 * @param array $form
 *   An array representing a Drupal form.
 * @param array $form_state
 *   The Drupal form state.
 *
 * @return array
 *   
 */
function islandora_datacite_doi_admin_form($form, &$form_state) {
  //form_load_include($form_state, 'inc', 'islandora_pydio_bridge', 'includes/admin.form');

  $form = array();

  $form['islandora_datacite_doi_prefix'] = array(
    '#type' => 'textfield',
    '#title' => t('The prefix provided by Datacite for your DOIs'),
    '#default_value' => variable_get('islandora_datacite_doi_prefix', 'doi_prefix'),
    '#description' => t('The prefix provided by Datacite for your DOIs'),
    '#required' => TRUE,
  );
  $form['islandora_datacite_doi_site'] = array(
    '#type' => 'textfield',
    '#title' => t('The site or application name'),
    '#default_value' => variable_get('islandora_datacite_doi_site', 'site_or_application'),
    '#description' => t('A local site or application name to use in the creation of the DOI.  '
        . 'DOIs will be minted as such "prefix/site/pid" e.g. "10.11571/upei-roblib-data/pydio:25"'),
    '#required' => TRUE,
  );

  $form['islandora_datacite_doi_username'] = array(
    '#type' => 'textfield',
    '#title' => t('Your Datacite username'),
    '#default_value' => variable_get('islandora_datacite_doi_username', 'datacite_username'),
    '#description' => t('The pydio server url and path'),
    '#required' => TRUE,
  );

  $form['islandora_datacite_doi_password'] = array(
    '#type' => 'textfield',
    '#title' => t('Datacite Password'),
    '#default_value' => variable_get('islandora_datacite_doi_password', 'datacite password'),
    '#description' => t('The password to use for Datacite'),
    '#required' => TRUE,
  );

  $form['islandora_pydio_datacite_saxon_path'] = array(
    '#type' => 'textfield',
    '#title' => t('The path to the saxon.jar file'),
    '#default_value' => variable_get('islandora_pydio_datacite_saxon_path', '/opt/saxon/saxon9he.jar'),
    '#description' => t('The path to saxon for xslt 2.0 tranforms.  Used to transform DDIto Datacite xml.'),
    '#required' => TRUE,
  );
  
  $form['islandora_datacite_doi_use_ssl'] = array(
    '#type' => 'checkbox',
    '#title' => t('Should Datacite use https for links back to your site'),
    '#default_value' => variable_get('islandora_datacite_doi_use_ssl', 'FALSE'),
    '#required' => TRUE,
  );

  return system_settings_form($form);
}
