<?php

namespace Drupal\Tests\layout_paragraphs\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\paragraphs\Functional\Classic\ParagraphsTestBase;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;

/**
 * Tests adding a new layout section to layout paragraphs.
 *
 * @group layout_paragraphs
 */
class LayoutParagraphsTest extends ParagraphsTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
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
    $this->addParagraphedContentType('page', 'content', 'layout_paragraphs');
  }

  /**
   * Tests configuring a new layout paragraphs field.
   */
  public function testLayoutParagraphsConfiguration() {

    $this->loginAsAdmin([
      'administer site configuration',
      'create article content',
      'create paragraphs content',
      'administer node display',
      'administer paragraph display',
      'edit any page content',
      'delete any page content',
      'access files overview',
    ]);
    $this->drupalGet('admin/structure/types/manage/page/node.page.field_content');
    $this->assertSession()->pageTextContains('Content settings for page.');

  }

}
