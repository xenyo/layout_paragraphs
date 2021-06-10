<?php

namespace Drupal\layout_paragraphs\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\layout_paragraphs\Event\LayoutParagraphsUpdateLayoutEvent;

/**
 * Event subscriber.
 */
class LayoutParagraphsUpdateLayoutSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    return [
      LayoutParagraphsUpdateLayoutEvent::EVENT_NAME => 'compareLayouts',
    ];
  }

  /**
   * Restricts available types based on settings in layout.
   *
   * @param \Drupal\layout_paragraphs\Event\LayoutParagraphsUpdateLayoutEvent $event
   *   The allowed types event.
   */
  public function compareLayouts(LayoutParagraphsUpdateLayoutEvent $event) {

    $original = $event->getOriginalLayout()->getParagraphsReferenceField();
    $layout = $event->getUpdatedLayout()->getParagraphsReferenceField();

    $event->needsRefresh = ($original->isEmpty() != $layout->isEmpty());
  }

}
