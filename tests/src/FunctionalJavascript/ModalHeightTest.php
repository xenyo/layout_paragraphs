<?php

namespace Drupal\Tests\layout_paragraphs\FunctionalJavascript;

/**
 * Tests that buttons remain reachable even with tall modal heights.
 *
 * @requires module layout_paragraphs_alter_controls_test
 *
 * @group layout_paragraphs
 */
class ModalHeightTest extends BuilderTestBase {

  /**
   * Tests modal height.
   */
  public function testModalHeight() {
    $this->loginWithPermissions([
      'create page content',
      'edit own page content',
    ]);
    $this->drupalGet('node/add/page');
    $page = $this->getSession()->getPage();

    // Click the Add Component button.
    $page->find('css', '.lpb-btn--add')->click();
    $this->assertSession()->assertWaitOnAjaxRequest(1000, 'Unable to click add a component.');

    // Add a text component.
    $page->clickLink('text');
    $this->assertSession()->assertWaitOnAjaxRequest(1000, 'Unable to select text component.');

    // Force the dialog height to expand beyond the viewport.
    $this->getSession()->executeScript('jQuery(\'.layout-paragraphs-component-form\').height(\'2000px\');');

    // Save button should be reachable.
    $this->assertSession()->waitForElementVisible('css', '.lpb-btn--save');
    $this->assertSession()->assertVisibleInViewport('css', '.lpb-btn--save');

  }

}
