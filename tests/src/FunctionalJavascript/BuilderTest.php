<?php

namespace Drupal\Tests\layout_paragraphs\FunctionalJavascript;

use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

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

    // Add a text item to first column.
    // Because there are only two component types and sections cannot
    // be nested, this will load the text component form directly.
    $button = $page->find('css', '.layout__region--first .lpb-btn--add');
    $button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('field_text');

    $page->fillField('field_text[0][value]', 'Some arbitrary text');
    // Force show the hidden submit button so we can click it.
    $this->getSession()->executeScript("jQuery('.lpb-btn--save').attr('style', '');");
    $button = $this->assertSession()->waitForElementVisible('css', ".lpb-btn--save");
    $button->press();

    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Some arbitrary text');

    $this->submitForm([
      'title[0][value]' => 'Node title',
    ], 'Save');
    $this->assertSession()->pageTextContains('Node title');
    $this->assertSession()->pageTextContains('Some arbitrary text');

  }

  protected function forceVisible($selector) {
    $this->getSession()->executeScript("jQuery('{$selector} .contextual .trigger').toggleClass('visually-hidden');");
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

}
