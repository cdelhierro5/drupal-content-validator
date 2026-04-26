<?php

namespace Drupal\drupal_content_validator\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\drupal_content_validator\Service\ContentValidatorService;
use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\core_event_dispatcher\Event\Entity\EntityPresaveEvent;
use Drupal\core_event_dispatcher\EntityHookEvents;

/**
 * Reacts to node presave events to run content validation.
 *
 * Using an event subscriber (instead of hook_node_presave alone) makes
 * the validation pipeline easier to extend and test.
 */
class NodeValidationSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected ContentValidatorService $validator,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Fallback: hook_node_presave in .module handles the actual blocking.
    // This subscriber is the extension point for additional async reactions.
    return [];
  }

  /**
   * Validates a node and returns the result.
   *
   * Called externally (e.g. from AJAX endpoints or tests).
   */
  public function validateNode(NodeInterface $node): array {
    $result = $this->validator->validate($node);

    return [
      'valid'    => $result->isValid(),
      'errors'   => $result->getErrors(),
      'warnings' => $result->getWarnings(),
    ];
  }

}
