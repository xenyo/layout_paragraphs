<?php

namespace Drupal\Tests\layout_paragraphs\FunctionalJavascript;

/**
 * Tests adding a new layout section to layout paragraphs.
 *
 * @group layout_paragraphs
 */
class BuilderTest extends BuilderTestBase {

  /**
   * Tests adding a section component to a new page.
   */
  public function testAddSection() {
    $this->loginWithPermissions([
      'create page content',
      'edit own page content',
    ]);

    $this->drupalGet('node/add/page');
    $page = $this->getSession()->getPage();

    $this->addSectionComponent(2, '.lpb-btn--add');

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
   * Tests editing a paragraph component.
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
   * Tests deleting a component.
   */
  public function testDeleteComponent() {
    $this->testAddComponent();
    $this->drupalGet('node/1/edit');
    $page = $this->getSession()->getPage();
    // Press delete on the component in the first region.
    $button = $page->find('css', '.layout__region--first a.lpb-delete');
    $button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Delete component');
    // Confirm delete.
    $button = $page->find('css', 'button.lpb-btn--confirm-delete');
    $button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Component should no longer be on page.
    $this->assertSession()->pageTextNotContains('Some arbitrary text');
    // Add a new component and press delete.
    $this->addTextComponent('New text component.', '.layout__region--first .lpb-btn--add');
    $button = $page->find('css', '.layout__region--first a.lpb-delete');
    $button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Cancel the operation.
    $button = $page->find('css', 'button.dialog-cancel');
    $button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('New text component.');
    $this->assertSession()->pageTextNotContains('Delete component');
  }

  /**
   * Tests reordering components with the "move up" button.
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

}
