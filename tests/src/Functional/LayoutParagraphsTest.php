<?php

namespace Drupal\Tests\layout_paragraphs\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;

/**
 * Tests adding a new layout section to layout paragraphs.
 *
 * @group layout_paragraphs
 */
class LayoutParagraphsTest extends BrowserTestBase {

  use ParagraphsTestBaseTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_paragraphs',
    'paragraphs',
    'node',
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

    $this->drupalGet('admin/structure/types/manage/page/node.page.field_content');
    $this->assertSession()->pageTextContains('Content settings for page.');

  }

}
