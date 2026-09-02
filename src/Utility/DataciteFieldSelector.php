<?php

namespace Drupal\islandora_datacite_doi\Utility;

use Drupal\Core\Entity\EntityInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Lets the data profile form pick a field directly on the node, or a
 * field within a paragraph referenced by one of the node's fields.
 *
 * A selector is either a plain Drupal field machine name ("field_title"),
 * or "paragraph_field_name:sub_field_name" to reach into a paragraph
 * (e.g. "field_funder:field_funder_name").
 *
 * This assumes the entity being selected from is always a node, matching
 * every field this module works with; a field on some other entity type
 * simply won't be detected as a paragraph reference.
 */
final class DataciteFieldSelector {

  const DELIMITER = ':';

  /**
   * Whether a selector string points into a paragraph.
   */
  public static function isParagraphSelector(string $selector): bool {
    return strpos($selector, self::DELIMITER) !== FALSE;
  }

  /**
   * Splits a paragraph selector into [field_name, sub_field_name].
   */
  public static function splitSelector(string $selector): array {
    return explode(self::DELIMITER, $selector, 2);
  }

  /**
   * Builds a #options array from $available_fields, adding paragraph
   * sub-fields (grouped under an optgroup per paragraph field) for any
   * field that is an entity_reference_revisions field targeting
   * paragraphs.
   *
   * @param array $available_fields
   *   The flat field-name => field-name options passed in by dgi_actions'
   *   DataProfileForm via $form_state->getTemporaryValue('available_fields').
   *
   * @return array
   *   A #options-ready array: the original flat fields, plus one optgroup
   *   per paragraph-reference field listing "field_name:sub_field_name"
   *   options for that paragraph bundle's fields.
   */
  public static function buildFieldOptions(array $available_fields): array {
    $options = $available_fields;

    $field_manager = \Drupal::service('entity_field.manager');
    $storage_definitions = $field_manager->getFieldStorageDefinitions('node');
    $field_map = $field_manager->getFieldMap()['node'] ?? [];
    $field_config_storage = \Drupal::entityTypeManager()->getStorage('field_config');

    foreach (array_keys($available_fields) as $field_name) {
      if (!isset($storage_definitions[$field_name])) {
        continue;
      }
      $storage = $storage_definitions[$field_name];
      if ($storage->getType() !== 'entity_reference_revisions' || $storage->getSetting('target_type') !== 'paragraph') {
        continue;
      }

      // Union the allowed target bundles across every node bundle that
      // uses this field name, since the current bundle isn't available
      // to this plugin.
      $target_bundles = [];
      $bundles = array_keys($field_map[$field_name]['bundles'] ?? []);
      foreach ($bundles as $bundle) {
        $field_config = $field_config_storage->load("node.$bundle.$field_name");
        if (!$field_config) {
          continue;
        }
        $handler_settings = $field_config->getSetting('handler_settings') ?? [];
        $target_bundles += $handler_settings['target_bundles'] ?? [];
      }

      foreach (array_keys($target_bundles) as $paragraph_bundle) {
        $sub_field_names = array_keys($field_manager->getFieldDefinitions('paragraph', $paragraph_bundle));
        if (empty($sub_field_names)) {
          continue;
        }
        // A distinct group label avoids colliding with the flat
        // $field_name => $field_name entry already in $options.
        $group_label = "$field_name (paragraph)";
        $options[$group_label] = $options[$group_label] ?? [];
        foreach ($sub_field_names as $sub_field_name) {
          $options[$group_label][$field_name . self::DELIMITER . $sub_field_name] = $sub_field_name;
        }
      }
    }

    return $options;
  }

  /**
   * Resolves a field selector against an entity, mimicking the shape of
   * FieldItemListInterface::getValue() (an array of associative arrays).
   * Items referencing a taxonomy term additionally get 'value' set to the
   * term's label, and 'ror'/'orcid' set when the term has those fields.
   *
   * @return array
   *   The resolved field item values, or an empty array if the selector
   *   doesn't resolve to anything.
   */
  public static function resolveValues(EntityInterface $entity, string $selector): array {
    if (self::isParagraphSelector($selector)) {
      [$field_name, $sub_field_name] = self::splitSelector($selector);
      if (!$entity->hasField($field_name)) {
        return [];
      }
      $field = $entity->get($field_name);
      if ($field->isEmpty()) {
        return [];
      }
      $values = [];
      foreach ($field->referencedEntities() as $paragraph) {
        if (!$paragraph->hasField($sub_field_name) || $paragraph->get($sub_field_name)->isEmpty()) {
          continue;
        }
        $sub_field = $paragraph->get($sub_field_name);
        $allowed_values = self::getAllowedValues($sub_field);
        foreach ($sub_field->getValue() as $item) {
          $values[] = self::resolveItem($item, $allowed_values);
        }
      }
      return $values;
    }

    if (!$entity->hasField($selector)) {
      return [];
    }
    $field = $entity->get($selector);
    if ($field->isEmpty()) {
      return [];
    }
    $allowed_values = self::getAllowedValues($field);
    $values = [];
    foreach ($field->getValue() as $item) {
      $values[] = self::resolveItem($item, $allowed_values);
    }
    return $values;
  }

  /**
   * Resolves a field selector to its first value as a plain string.
   */
  public static function resolveString(EntityInterface $entity, string $selector): string {
    $values = self::resolveValues($entity, $selector);
    return $values[0]['value'] ?? '';
  }

  /**
   * Resolves a single field item: taxonomy term references get 'value' set
   * to the term's label (plus 'ror'/'orcid' when the term has those
   * fields), and List (text/integer/float) field values get 'value'
   * mapped from their stored key to its allowed-values label. Items that
   * are neither are returned unchanged.
   */
  private static function resolveItem(array $item, ?array $allowed_values): array {
    $item = self::resolveTermItem($item);
    if ($allowed_values !== NULL && isset($item['value']) && array_key_exists($item['value'], $allowed_values)) {
      $item['value'] = $allowed_values[$item['value']];
    }
    return $item;
  }

  /**
   * Returns a List field's key => label allowed values map, or NULL if the
   * field isn't a List (text/integer/float) field (or has no static
   * allowed values, e.g. one populated by an allowed_values_function).
   */
  private static function getAllowedValues($field): ?array {
    $storage_definition = $field->getFieldDefinition()->getFieldStorageDefinition();
    $allowed_values = $storage_definition->getSetting('allowed_values');
    return !empty($allowed_values) ? $allowed_values : NULL;
  }

  /**
   * Resolves a taxonomy term reference item to include the term's label
   * (as 'value'), and 'ror'/'orcid' when the term has those fields. Items
   * that aren't term references are returned unchanged.
   */
  private static function resolveTermItem(array $item): array {
    if (array_key_exists('target_id', $item)) {
      $term = Term::load($item['target_id']);
      if ($term) {
        $item['value'] = $term->label();
        if ($term->hasField('field_ror') && !$term->get('field_ror')->isEmpty()) {
          $item['ror'] = $term->get('field_ror')->uri;
        }
        if ($term->hasField('field_orcid') && !$term->get('field_orcid')->isEmpty()) {
          $item['orcid'] = $term->get('field_orcid')->uri;
        }
      }
    }
    return $item;
  }

  /**
   * Resolves multiple paragraph sub-field selectors that share the same
   * parent paragraph field, correlated per paragraph so e.g. a name and
   * an award number from the same paragraph stay paired even when one is
   * blank for that paragraph (unlike calling resolveString()/resolveValues()
   * separately per selector, which would resolve each into its own flat
   * list and lose that pairing).
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being read from.
   * @param array $selectors
   *   Associative array of result-key => paragraph selector (e.g.
   *   "field_funder:field_funder_name"). Selectors must all share the
   *   same parent paragraph field; one that doesn't is ignored, as is one
   *   that isn't a paragraph selector at all.
   *
   * @return array
   *   One associative array per paragraph that had at least one non-blank
   *   value, keyed the same as $selectors.
   */
  public static function resolveParagraphRows(EntityInterface $entity, array $selectors): array {
    $parent_field_name = NULL;
    $sub_fields = [];
    foreach ($selectors as $result_key => $selector) {
      if (empty($selector) || !self::isParagraphSelector($selector)) {
        continue;
      }
      [$field_name, $sub_field_name] = self::splitSelector($selector);
      $parent_field_name = $parent_field_name ?? $field_name;
      if ($field_name !== $parent_field_name) {
        // Selectors must share the same parent paragraph field to stay
        // correlated; ignore ones that don't.
        continue;
      }
      $sub_fields[$result_key] = $sub_field_name;
    }
    if (!$parent_field_name || !$entity->hasField($parent_field_name)) {
      return [];
    }
    $field = $entity->get($parent_field_name);
    if ($field->isEmpty()) {
      return [];
    }

    $rows = [];
    foreach ($field->referencedEntities() as $paragraph) {
      $row = [];
      foreach ($sub_fields as $result_key => $sub_field_name) {
        if (!$paragraph->hasField($sub_field_name) || $paragraph->get($sub_field_name)->isEmpty()) {
          continue;
        }
        $sub_field = $paragraph->get($sub_field_name);
        $item = self::resolveItem($sub_field->getValue()[0] ?? [], self::getAllowedValues($sub_field));
        if (!empty($item['value'])) {
          $row[$result_key] = $item['value'];
        }
      }
      if (!empty($row)) {
        $rows[] = $row;
      }
    }
    return $rows;
  }

}