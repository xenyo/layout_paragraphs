<?php

namespace Drupal\Tests\layout_paragraphs\FunctionalJavascript;

use Behat\Mink\Exception\ExpectationException;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;

/**
 * Base class for Layout Paragraphs Builder tests.
 *
 * @group layout_paragraphs
 */
abstract class BuilderTestBase extends WebDriverTestBase {

  use ParagraphsTestBaseTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_paragraphs',
    'paragraphs',
    'node',
    'field',
    'field_ui',
    'block',
    'paragraphs_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->addParagraphsType('section');
    $this->addParagraphsType('text');
    $this->addFieldtoParagraphType('text', 'field_text', 'text');

    $this->loginWithPermissions([
      'administer site configuration',
      'administer node fields',
      'administer node display',
      'administer paragraphs types',
    ]);

    // Enable Layout Paragraphs behavior for section paragraph type.
    $this->drupalGet('admin/structure/paragraphs_type/section');
    $this->submitForm([
      'behavior_plugins[layout_paragraphs][enabled]' => TRUE,
      'behavior_plugins[layout_paragraphs][settings][available_layouts][]' => [
        'layout_onecol',
        'layout_twocol',
        'layout_threecol_25_50_25',
        'layout_threecol_33_34_33',
      ],
    ], 'Save');
    $this->assertSession()->pageTextContains('Saved the section Paragraphs type.');
    $this->addLayoutParagraphedContentType('page', 'field_content');
    $this->drupalLogout();
  }

  /**
   * Adds a content type with a layout paragraphs field.
   *
   * @param string $type_name
   *   The name of the content type.
   * @param string $paragraph_field
   *   The name of the paragraphs reference field.
   */
  protected function addLayoutParagraphedContentType($type_name, $paragraph_field) {
    $this->addParagraphedContentType($type_name, $paragraph_field, 'layout_paragraphs');
    // Add "section" and "text" paragraph types to the "page" content type.
    $this->drupalGet('admin/structure/types/manage/page/fields/node.' . $type_name . '.' . $paragraph_field);
    // Enables all paragraph types.
    $this->submitForm([
      'settings[handler_settings][negate]' => '1',
    ], 'Save settings');
    // Use "Layout Paragraphs" formatter for the content field.
    $this->drupalGet('admin/structure/types/manage/' . $type_name . '/display');
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('fields[' . $paragraph_field . '][type]', 'layout_paragraphs');
    $this->assertSession()->assertWaitOnAjaxRequest(1000, 'Unable to choose layout paragraphs field formatter.');
    $this->submitForm([], 'Save');
  }

  /**
   * Uses Javascript to make a DOM element visible.
   *
   * @param string $selector
   *   A css selector.
   */
  protected function forceVisible($selector) {
    $this->getSession()->executeScript("jQuery('{$selector}').css({display:'inline-block', opacity:1, visibility: 'visible'});");
  }

  /**
   * Inserts a text component by clicking the "+" button.
   *
   * @param string $text
   *   The text for the component's field_text value.
   * @param string $css_selector
   *   A css selector targeting the "+" button.
   */
  protected function addTextComponent($text, $css_selector) {
    $page = $this->getSession()->getPage();
    // Add a text item to first column.
    // Because there are only two component types and sections cannot
    // be nested, this will load the text component form directly.
    $button = $page->find('css', $css_selector);
    $button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $title = $page->find('css', '.ui-dialog-title');
    if ($title->getText() == 'Choose a component') {
      $page->clickLink('text');
      $this->assertSession()->assertWaitOnAjaxRequest();
    }

    $dialog = $page->find('css', '.lpb-dialog');
    $style = $dialog->getAttribute('style');
    if (strpos($style, 'width: 90%;') === FALSE || strpos($style, 'height: auto;') === FALSE) {
      throw new ExpectationException('Incorrect dialog width or height settings', $this->getSession()->getDriver());
    }

    $this->assertSession()->pageTextContains('field_text');

    $page->fillField('field_text[0][value]', $text);
    // Force show the hidden submit button so we can click it.
    $this->getSession()->executeScript("jQuery('.lpb-btn--save').attr('style', '');");
    $button = $this->assertSession()->waitForElementVisible('css', ".lpb-btn--save");
    $button->press();

    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains($text);
  }

  /**
   * Inserts a text component by clicking the "+" button and choosing "section".
   *
   * @param int $columns_choice
   *   Which column option to choose.
   * @param string $css_selector
   *   A css selector targeting the "+" button.
   */
  protected function addSectionComponent(int $columns_choice, $css_selector) {

    $page = $this->getSession()->getPage();
    // Click the Add Component button.
    $page->find('css', $css_selector)->click();
    $this->assertSession()->assertWaitOnAjaxRequest(1000, 'Unable to click add a component.');

    // Add a section.
    $page->clickLink('section');
    $this->assertSession()->assertWaitOnAjaxRequest(1000, 'Unable to select section component.');

    // Choose a three-column layout.
    $elements = $page->findAll('css', 'label.option');
    $elements[$columns_choice]->click();
    $this->assertSession()->assertWaitOnAjaxRequest(1000, 'Unable to select layout.');

    // Save the layout.
    $button = $page->find('css', 'button.lpb-btn--save');
    $button->click();
    $this->assertSession()->assertWaitOnAjaxRequest(1000, 'Could not save section.');

  }

  /**
   * Creates a new user with provided permissions and logs them in.
   *
   * @param array $permissions
   *   An array of permissions.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The user.
   */
  protected function loginWithPermissions(array $permissions) {
    $user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($user);
    return $user;
  }

  /**
   * {@inheritDoc}
   *
   * Added method with fixed return comment for IDE type hinting.
   *
   * @return \Drupal\FunctionalJavascriptTests\JSWebAssert
   *   A new JS web assert object.
   */
  public function assertSession($name = '') {
    $js_web_assert = parent::assertSession($name);
    return $js_web_assert;
  }

  /**
   * Asserts that provided strings appear on page in same order as in array.
   *
   * @param array $strings
   *   A list of strings in the order they are expected to appear.
   * @param string $assert_message
   *   Message if assertion fails.
   */
  protected function assertOrderOfStrings(array $strings, $assert_message = 'Strings are not in correct order.') {
    $page = $this->getSession()->getPage();
    $page_text = $page->getHtml();
    $highmark = -1;
    foreach ($strings as $string) {
      $this->assertSession()->pageTextContains($string);
      $pos = strpos($page_text, $string);
      if ($pos <= $highmark) {
        throw new ExpectationException($assert_message, $this->getSession()->getDriver());
      }
      $highmark = $pos;
    }
  }

  /**
   * Simulates pressing a key with javascript.
   *
   * @param string $key_code
   *   The string key code (i.e. ArrowUp, Enter).
   */
  protected function keyPress($key_code) {
    $script = 'var e = new KeyboardEvent("keydown", {bubbles : true, cancelable : true, code: "' . $key_code . '"});
    document.body.dispatchEvent(e);';
    $this->getSession()->executeScript($script);
  }

}
