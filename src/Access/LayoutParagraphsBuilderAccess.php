<?php

namespace Drupal\layout_paragraphs\Access;

use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;

/**
 * Class definition for LayoutParagraphsBuilderAccess.
 *
 * Checks access to layout paragraphs builder instance.
 */
class LayoutParagraphsBuilderAccess implements AccessInterface {

  /**
   * {@inheritDoc}
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check access for.
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The layout paragraphs layout object.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(AccountInterface $account, LayoutParagraphsLayout $layout_paragraphs_layout) {
    $entity = $layout_paragraphs_layout->getEntity();
    if ($entity->isNew()) {
      return $entity->access('create', $account, TRUE);
    }
    else {
      return $entity->access('update', $account, TRUE);
    }
  }

}
