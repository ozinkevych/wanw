<?php

namespace Drupal\Tests\field_defaults\Functional;

/**
 * Tests that defaults are set on string fields.
 *
 * @group field_defaults
 */
class StringTest extends FieldDefaultsTestBase {

  /**
   * Test updating a string.
   */
  public function testFieldString() {
    // In D10.2 the text field changed the machine name
    // from 'string' to 'plain_text'.
    $core_v = \Drupal::VERSION;

    if (version_compare($core_v, '10.2', '<=')) {
      $type = 'string';
    }
    else {
      $type = 'plain_text';
    }

    $fieldName = $this->createField($type);
    $this->setDefaultValues($fieldName, 'string');

    // Ensure value is checked on any random node.
    $this->drupalGet('node/' . rand(1, 20) . '/edit');

    $field_setup = $this->setupFieldByType('string');
    $this->assertSession()
      ->fieldValueEquals('field_' . $fieldName . $field_setup['structure'], $field_setup['value']);
  }

}
