<?php

namespace Drupal\Tests\inline_entity_form\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Base Class for Inline Entity Form Tests.
 */
abstract class InlineEntityFormTestBase extends WebDriverTestBase {

  /**
   * User with permissions to create content.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * Field config storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorage
   */
  protected $fieldStorageConfigStorage;

  /**
   * Field config storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $fieldConfigStorage;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->fieldStorageConfigStorage = $this->container->get('entity_type.manager')->getStorage('field_storage_config');
    $this->fieldConfigStorage = $this->container->get('entity_type.manager')->getStorage('field_config');
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareSettings() {
    $drupal_version = (float) substr(\Drupal::VERSION, 0, 3);
    if ($drupal_version < 8.8) {
      // Fix entity_reference_autocomplete match_limit schema errors.
      $this->strictConfigSchema = FALSE;
    }
    parent::prepareSettings();
  }

  /**
   * Gets IEF button name.
   *
   * @param string $xpath
   *   Xpath of the button.
   *
   * @return string
   *   The name of the button.
   */
  protected function getButtonName($xpath) {
    $retval = '';
    /** @var \SimpleXMLElement[] $elements */
    if ($elements = $this->xpath($xpath)) {
      foreach ($elements[0]->attributes() as $name => $value) {
        if ($name == 'name') {
          $retval = $value;
          break;
        }
      }
    }
    return $retval;
  }

  /**
   * Passes if no node is found for the title.
   *
   * @param string $title
   *   Node title to check.
   * @param string $message
   *   Message to display.
   */
  protected function assertNoNodeByTitle($title, $message = '') {
    if (!$message) {
      $message = "No node with title: $title";
    }
    $node = $this->getNodeByTitle($title, TRUE);

    $this->assertEmpty($node, $message);
  }

  /**
   * Passes if a node is found for the title.
   *
   * @param string $title
   *   Node title to check.
   * @param string $content_type
   *   The content type to check.
   * @param string $message
   *   Message to display.
   */
  protected function assertNodeByTitle($title, $content_type = NULL, $message = '') {
    if (!$message) {
      $message = "Node with title found: $title";
    }
    $node = $this->getNodeByTitle($title, TRUE);
    if ($this->assertNotEmpty($node, $message)) {
      if ($content_type) {
        $this->assertEqual($node->bundle(), $content_type, "Node is correct content type: $content_type");
      }
    }
  }

  /**
   * Ensures that an entity with a specific label exists.
   *
   * @param string $label
   *   The label of the entity.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   (optional) The bundle this entity should have.
   */
  protected function assertEntityByLabel($label, $entity_type_id = 'node', $bundle = NULL) {
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_type = $entity_type_manager->getDefinition($entity_type_id);
    $label_key = $entity_type->getKey('label');
    $bundle_key = $entity_type->getKey('bundle');

    $query = $entity_type_manager->getStorage($entity_type_id)->getQuery();
    $query->condition($label_key, $label);

    if ($bundle && $bundle_key) {
      $query->condition($bundle_key, $bundle);
    }

    $result = $query->execute();
    $this->assertNotEmpty($result);
  }

  /**
   * Checks for check correct fields on form displays.
   *
   * This checks based on exported config in the
   * inline_entity_form_test module.
   *
   * @param string $form_display
   *   The form display to check.
   * @param string $prefix
   *   The config prefix.
   */
  protected function checkFormDisplayFields($form_display, $prefix) {
    $assert_session = $this->assertSession();
    $form_display_fields = [
      'node.ief_test_custom.default' => [
        'expected' => [
          '[title][0][value]',
          '[uid][0][target_id]',
          '[created][0][value][date]',
          '[created][0][value][time]',
          '[promote][value]',
          '[sticky][value]',
          '[positive_int][0][value]',
        ],
        'unexpected' => [],
      ],
      'node.ief_test_custom.inline' => [
        'expected' => [
          '[title][0][value]',
          '[positive_int][0][value]',
        ],
        'unexpected' => [
          '[uid][0][target_id]',
          '[created][0][value][date]',
          '[created][0][value][time]',
          '[promote][value]',
          '[sticky][value]',
        ],
      ],
    ];

    if (empty($form_display_fields[$form_display])) {
      throw new \Exception('Form display not found: ' . $form_display);
    }

    $fields = $form_display_fields[$form_display];
    foreach ($fields['expected'] as $expected_field) {
      $assert_session->fieldExists($prefix . $expected_field);
    }
    foreach ($fields['unexpected'] as $unexpected_field) {
      $assert_session->fieldNotExists($prefix . $unexpected_field, NULL);
    }
  }

  /**
   * Wait for an IEF table row to appear.
   *
   * @param string $title
   *   The title of the row for which to wait.
   */
  protected function waitForRowByTitle($title) {
    $this->assertNotEmpty($this->assertSession()->waitForElement('xpath', '//td[@class="inline-entity-form-node-label" and text()="' . $title . '"]'));
  }

  /**
   * Wait for an IEF table row to appear.
   *
   * @param string $title
   *   The title of the row for which to wait.
   */
  protected function waitForRowRemovedByTitle($title) {
    $this->assertNotEmpty($this->waitForElementRemoved('xpath', '//td[@class="inline-entity-form-node-label" and text()="' . $title . '"]'));
  }

  /**
   * Asserts that an IEF table row appears.
   *
   * @param string $title
   *   The title of the row for which to wait.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The <td> element containing the label for the IEF row.
   */
  protected function assertRowByTitle($title) {
    $this->assertNotEmpty($element = $this->assertSession()->elementExists('xpath', '//td[@class="inline-entity-form-node-label" and text()="' . $title . '"]'));
    return $element;
  }

  /**
   * Asserts that an IEF table row appears.
   *
   * @param string $title
   *   The title of the row for which to wait.
   */
  protected function assertNoRowByTitle($title) {
    $this->assertSession()->elementNotExists('xpath', '//td[@class="inline-entity-form-node-label" and text()="' . $title . '"]');
  }

  /**
   * Looks for the specified selector and returns TRUE when it is unavailable.
   *
   * @todo Remove when tests are running on Drupal 8.8. or greater. Then
   * we can use $assert_session->waitForElementRemoved(). This is will be when
   * Drupal 8.7 reaches EOL (which is when 8.9 is released in June 2020).
   *
   * @param string $selector
   *   The selector engine name. See ElementInterface::findAll() for the
   *   supported selectors.
   * @param string|array $locator
   *   The selector locator.
   * @param int $timeout
   *   (Optional) Timeout in milliseconds, defaults to 10000.
   *
   * @return bool
   *   TRUE if not found, FALSE if found.
   *
   * @see Drupal\FunctionalJavascriptTests\JSWebAssert::waitForElementRemoved
   */
  public function waitForElementRemoved($selector, $locator, $timeout = 10000) {
    $page = $this->getSession()->getPage();

    $result = $page->waitFor($timeout / 1000, function () use ($page, $selector, $locator) {
      return !$page->find($selector, $locator);
    });

    return $result;
  }

}
