<?php

namespace Drupal\Tests\field_defaults\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that defaults are set on fields.
 *
 * @group field_defaults
 */
abstract class FieldDefaultsTestBase extends BrowserTestBase {

  /**
   * The administrator account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $administratorAccount;

  /**
   * The field name.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['block', 'node', 'field_ui', 'field_defaults'];

  /**
   * {@inheritdoc}
   *
   * Once installed, a content type with the desired field is created.
   */
  protected function setUp(): void {
    // Install Drupal.
    parent::setUp();

    // Add the system menu blocks to appropriate regions.
    $this->setupMenus();

    // Create a Content type and some nodes.
    $this->drupalCreateContentType(['type' => 'page']);

    // Create and login a user that creates the content type.
    $permissions = [
      'administer nodes',
      'administer content types',
      'administer node fields',
      'edit any page content',
      'administer field defaults',
    ];
    $this->administratorAccount = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->administratorAccount);

    // Create some dummy content.
    for ($i = 0; $i < 20; $i++) {
      $this->drupalCreateNode();
    }
  }

  /**
   * Set up menus and tasks in their regions.
   *
   * Since menus and tasks are now blocks, we're required to explicitly set them
   * to regions.
   *
   * Note that subclasses must explicitly declare that the block module is a
   * dependency.
   */
  protected function setupMenus() {
    $this->drupalPlaceBlock('system_menu_block:tools', ['region' => 'primary_menu']);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'secondary_menu']);
    $this->drupalPlaceBlock('local_actions_block', ['region' => 'content']);
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content']);
  }

  /**
   * Creates a field on a content entity.
   */
  protected function createField($type = 'boolean', $cardinality = '1', $contentType = 'page') {
    $this->drupalGet('admin/structure/types/manage/' . $contentType . '/fields');

    // In Drupal 10.2 version changed the form to add a new field to a content
    // type and we have to take into it for tests for different core version.
    // We follow the approach using in the DeprecationHelper class, but in our
    // case changed UI not method or class. To correct pass test we'll search
    // buttons by different  label have to Drupal core version.
    // @see https://git.drupalcode.org/project/drupal/-/blob/11.x/core/lib/Drupal/Component/Utility/DeprecationHelper.php
    // Go to the 'Create a new field' page.
    // The label of Save button change depend on Drupal core version.
    $core_v = \Drupal::VERSION;

    // Clicks on Add field button.
    if (version_compare($core_v, '9.5', '<=')) {
      $this->clickLink('Add field');
    }
    else {
      $this->clickLink('Create a new field');
    }

    // Make a name for this field.
    $field_name = strtolower($this->randomMachineName(10));

    // Fill out the field form.
    $edit = [
      'new_storage_type' => $type,
      'field_name' => $field_name,
      'label' => $field_name,
    ];

    // Saves the first step Add a new Field form.
    if (version_compare($core_v, '10.2', '<=')) {
      $this->submitForm($edit, 'Save and continue');
    }
    if (version_compare($core_v, '10.2', '>=') && $type == 'boolean') {
      $this->submitForm($edit, 'Continue');
    }

    if (version_compare($core_v, '10.2', '>=') && $type == 'plain_text') {
      $this->submitForm($edit, 'Continue');
      $edit += [
        'group_field_options_wrapper' => 'string',
      ];
      $this->submitForm($edit, 'Continue');
    }

    // Fill out the $cardinality form as if we're not using an unlimited values.
    $edit = [
      'cardinality' => 'number',
      'cardinality_number' => (string) $cardinality,
    ];
    // -1 for $cardinality, we should change to 'Unlimited'.
    if (-1 == $cardinality) {
      $edit = [
        'cardinality' => '-1',
        'cardinality_number' => '1',
      ];
    }

    // And now we save the cardinality settings.
    // In D10.2 cardinality settings was combined with description and
    // saves as one step.
    if (version_compare($core_v, '10.2', '<=')) {
      $this->submitForm($edit, 'Save field settings');
      $this->assertSession()->pageTextContains("Updated field {$field_name} field settings.");
    }
    // Save.
    $this->submitForm([], 'Save settings');
    $this->assertSession()->pageTextContains("Saved {$field_name} configuration.");

    return $field_name;
  }

  /**
   * Sets a default value and runs the batch update.
   *
   * @todo Add support for cardinality.
   * @todo Add support for language.
   */
  protected function setDefaultValues($fieldName, $field_type = 'boolean', $values = [], $contentType = 'page') {
    $this->drupalGet('admin/structure/types/manage/' . $contentType . '/fields/node.' . $contentType . '.field_' . $fieldName);

    $field_setup = $this->setupFieldByType($field_type);

    // Fill out the field form.
    $edit = [
      'default_value_input[field_' . $fieldName . ']' . $field_setup['structure'] => $field_setup['value'],
      'default_value_input[field_defaults][update_defaults]' => TRUE,
    ];

    // Run batch.
    $this->submitForm($edit, 'Save settings');
    $this->assertSession()->responseNotContains('Initial progress message is not double escaped.');
    // Now also go to the next step.
    $this->maximumMetaRefreshCount = 1;
    $this->assertSession()->responseContains('Default values were updated for 20 entities.');
  }

  /**
   * Helper for field structure.
   *
   * @todo Add support for cardinality.
   */
  protected function setupFieldByType($type) {
    switch ($type) {
      case 'string':
        // Defaults for boolean per function def.
        $structure = '[0][value]';
        $value = 'field default';
        break;

      default:
        // Defaults for boolean per function def.
        $structure = '[value]';
        $value = TRUE;
    }
    return ['structure' => $structure, 'value' => $value];
  }

}
