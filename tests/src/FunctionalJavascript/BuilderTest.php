<?php

namespace Drupal\Tests\layout_paragraphs\FunctionalJavascript;

use Behat\Mink\Exception\ExpectationException;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;

/**
 * Tests adding a new layout section to layout paragraphs.
 *
 * @group layout_paragraphs
 */
class BuilderTest extends WebDriverTestBase {

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
    $this->addParagraphedContentType('page', 'field_content', 'layout_paragraphs');
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

    // Add "section" and "text" paragraph types to the "page" content type.
    $this->drupalGet('admin/structure/types/manage/page/fields/node.page.field_content');
    $this->submitForm([
      'settings[handler_settings][target_bundles_drag_drop][section][enabled]' => TRUE,
      'settings[handler_settings][target_bundles_drag_drop][text][enabled]' => TRUE,
    ], 'Save settings');

    // Use "Layout Paragraphs" formatter for the content field.
    $this->drupalGet('admin/structure/types/manage/page/display');
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('fields[field_content][type]', 'layout_paragraphs');
    $this->assertSession()->assertWaitOnAjaxRequest(1000, 'Unable to choose layout paragraphs field formatter.');
    $this->submitForm([], 'Save');

    $this->drupalLogout();
  }

  /**
   * Tests configuring a new layout paragraphs field.
   */
  public function testAddSection() {
    $this->loginWithPermissions([
      'create page content',
      'edit own page content',
    ]);

    $this->drupalGet('node/add/page');
    $page = $this->getSession()->getPage();

    // Click the Add Component button.
    $page->find('css', '.lpb-btn--add')->click();
    $this->assertSession()->assertWaitOnAjaxRequest(1000, 'Unable to click add a component.');

    // Add a section.
    $page->clickLink('section');
    $this->assertSession()->assertWaitOnAjaxRequest(1000, 'Unable to select section component.');

    // Choose a three-column layout.
    $elements = $page->findAll('css', 'label.option');
    $elements[2]->click();
    $this->assertSession()->assertWaitOnAjaxRequest(1000, 'Unable to select layout.');

    // Save the layout.
    $button = $page->find('css', 'button.lpb-btn--save');
    $button->click();
    $this->assertSession()->assertWaitOnAjaxRequest(1000, 'Could not save section.');

    // Assert that three columns now exist.
    $first_col = $page->find('css', '.layout__region--first');
    $this->assertNotEmpty($first_col);
    $second_col = $page->find('css', '.layout__region--second');
    $this->assertNotEmpty($second_col);
    $third_col = $page->find('css', '.layout__region--third');
    $this->assertNotEmpty($third_col);

  }

  /**
   * Tests adding a component into a section.
   */
  public function testAddComponent() {
    $this->testAddSection();
    $this->addTextComponent('Some arbitrary text', '.layout__region--first .lpb-btn--add');
    $this->submitForm([
      'title[0][value]' => 'Node title',
    ], 'Save');
    $this->assertSession()->pageTextContains('Node title');
    $this->assertSession()->pageTextContains('Some arbitrary text');
  }

  /**
   * Tests editing a paragraph.
   */
  public function testEditComponent() {
    $this->testAddComponent();
    $this->drupalGet('node/1/edit');

    $page = $this->getSession()->getPage();
    $button = $page->find('css', 'a.lpb-edit');
    $button->click();

    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Edit section');
  }

  /**
   * Tests reordering components.
   */
  public function testReorderComponents() {
    $this->testAddComponent();
    $this->drupalGet('node/1/edit');

    $page = $this->getSession()->getPage();
    $this->addTextComponent('Second text item.', '[data-id="2"] .lpb-btn--add.after');
    $this->assertOrderOfStrings(['Some arbitrary text', 'Second text item.'], 'Second item was not correctly added after the first.');

    // Click the new item's move up button.
    $button = $page->find('css', '.is_new .lpb-up');
    $button->click();

    $this->submitForm([
      'title[0][value]' => 'Node title',
    ], 'Save');
    $this->assertSession()->pageTextContains('Node title');
    $this->assertSession()->pageTextContains('Second text item.');

    // The second component should now appear first in the page source.
    $this->assertOrderOfStrings(['Second text item.', 'Some arbitrary text'], 'Components were not correctly reordered.');
  }

  /**
   * Tests keyboard navigation.
   */
  public function testKeyboardNavigation() {

    $this->testAddSection();
    $page = $this->getSession()->getPage();
    $this->submitForm([
      'title[0][value]' => 'Node title',
    ], 'Save');

    $this->drupalGet('node/1/edit');
    $this->addTextComponent('First item', '.layout__region--first .lpb-btn--add');
    $this->addTextComponent('Second item', '.layout__region--second .lpb-btn--add');
    $this->addTextComponent('Third item', '.layout__region--third .lpb-btn--add');

    // Click the new item's drag button.
    // This should create a <div> with the id 'lpb-navigatin-msg'.
    $button = $page->find('css', '.layout__region--third .lpb-drag');
    $button->click();
    $this->assertSession()->elementExists('css', '#lpb-navigating-msg');

    // Moves third item to bottom of second region.
    $this->keyPress('ArrowUp');
    $this->assertOrderOfStrings(['First item', 'Second item', 'Third item']);

    // Moves third item to top of second region.
    $this->keyPress('ArrowUp');
    $this->assertOrderOfStrings(['First item', 'Third item', 'Second item']);

    // Moves third item to bottom of first region.
    $this->keyPress('ArrowUp');
    $this->assertOrderOfStrings(['First item', 'Third item', 'Second item']);

    // Moves third item to top of first region.
    $this->keyPress('ArrowUp');
    $this->assertOrderOfStrings(['Third item', 'First item', 'Second item']);

    // Save the node.
    $this->submitForm([
      'title[0][value]' => 'Node title',
    ], 'Save');

    // Ensures reordering was correctly applied via Ajax.
    $this->assertOrderOfStrings(['Third item', 'First item', 'Second item']);
  }

  /**
   * Tests pressing the ESC key during keyboard navigation.
   */
  public function testKeyboardNavigationEsc() {

    $this->testAddSection();
    $page = $this->getSession()->getPage();
    $this->submitForm([
      'title[0][value]' => 'Node title',
    ], 'Save');

    $this->drupalGet('node/1/edit');
    $this->addTextComponent('First item', '.layout__region--first .lpb-btn--add');
    $this->addTextComponent('Second item', '.layout__region--second .lpb-btn--add');
    $this->addTextComponent('Third item', '.layout__region--third .lpb-btn--add');

    // Click the new item's drag button.
    // This should create a <div> with the id 'lpb-navigatin-msg'.
    $button = $page->find('css', '.layout__region--third .lpb-drag');
    $button->click();
    $this->assertSession()->elementExists('css', '#lpb-navigating-msg');

    // Moves third item to bottom of second region.
    $this->keyPress('ArrowUp');
    $this->assertOrderOfStrings(['First item', 'Second item', 'Third item']);

    // Moves third item to top of second region.
    $this->keyPress('ArrowUp');
    $this->assertOrderOfStrings(['First item', 'Third item', 'Second item']);

    // Moves third item to bottom of first region.
    $this->keyPress('ArrowUp');
    $this->assertOrderOfStrings(['First item', 'Third item', 'Second item']);

    // Moves third item to top of first region.
    $this->keyPress('ArrowUp');
    $this->assertOrderOfStrings(['Third item', 'First item', 'Second item']);

    // The Escape button should cancel reordering and return items
    // to their original order.
    $this->keyPress('Escape');
    $this->assertOrderOfStrings(['First item', 'Second item', 'Third item']);

    // Save the node.
    $this->submitForm([
      'title[0][value]' => 'Node title',
    ], 'Save');

    // Ensures canceling reordering was correctly applied via Ajax.
    $this->assertOrderOfStrings(['First item', 'Second item', 'Third item']);
  }

  /**
   * Uses Javascript to make a DOM element visible.
   *
   * @param string $selector
   *   A css selector.
   */
  protected function forceVisible($selector) {
    $this->getSession()->executeScript("jQuery('{$selector} .contextual .trigger').toggleClass('visually-hidden');");
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
