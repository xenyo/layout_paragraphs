<?php

namespace Drupal\Tests\layout_paragraphs\FunctionalJavascript;

use Drupal\editor\Entity\Editor;
use Drupal\field\Entity\FieldConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Entity\Entity\EntityFormDisplay;

/**
 * Tests that buttons remain reachable even with tall modal heights.
 *
 * @requires module layout_paragraphs_alter_controls_test
 *
 * @group layout_paragraphs
 */
class ModalHeightTest extends BuilderTestBase {

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
    'ckeditor',
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'seven';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {

    parent::setUp();
    $this->addParagraphsType('rich_text');

    // Create a text format and associate CKEditor.
    $this->filterFormat = FilterFormat::create([
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
      'weight' => 0,
    ]);
    $this->filterFormat->save();

    Editor::create([
      'format' => 'filtered_html',
      'editor' => 'ckeditor',
    ])->save();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_rich_text',
      'type' => 'text_long',
      'entity_type' => 'paragraph',
    ]);
    $field_storage->save();

    // Create a body field instance for the 'page' node type.
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'rich_text',
      'label' => 'Body',
      'settings' => ['display_summary' => TRUE],
      'required' => TRUE,
    ])->save();

    // Assign widget settings for the 'default' form mode.
    EntityFormDisplay::create([
      'targetEntityType' => 'paragraph',
      'bundle' => 'rich_text',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_rich_text', ['type' => 'text_textarea'])
      ->save();
  }

  /**
   * Tests modal height.
   */
  public function testModalHeight() {
    $this->loginWithPermissions([
      'create page content',
      'edit own page content',
      'use text format filtered_html',
    ]);

    $this->drupalGet('node/add/page');
    $page = $this->getSession()->getPage();

    $button = $page->find('css', '.lpb-btn--add');
    $button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $button = $page->find('css', '.type-rich_text');
    $button->click();
    $this->assertJsCondition('typeof CKEDITOR !== "undefined";');
    $this->assertJsCondition('Object.keys(CKEDITOR.instances).length > 0;');
    $this->htmlOutput();
    // Causes the dialog height to grow beyond the viewport.
    $text = "-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />-- rich text line --<p />";
    $this->getSession()->executeScript(<<<JS
jQuery('.layout-paragraphs-component-form').height('1000px');
JS);
    $button = $page->find('css', '.ui-dialog-buttonpane .lpb-btn--save');
    $button->click();

    $this->assertSession()->assertWaitOnAjaxRequest();
    //$this->assertSession()->pageTextContains('-- rich text line --');
    $this->htmlOutput();

    // $this->submitForm([
    //   'title[0][value]' => 'Node title',
    // ], 'Save');
    //$this->assertSession()->pageTextContains('-- rich text line --');

  }

}
