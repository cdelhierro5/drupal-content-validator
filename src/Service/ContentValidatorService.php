<?php

namespace Drupal\drupal_content_validator\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;

/**
 * Validates nodes against configurable business rules.
 *
 * Rules are defined per content type in the module settings form and stored
 * in Drupal's config system. Each rule is a callable check that returns
 * a ValidationResult.
 */
class ContentValidatorService {

  /**
   * Config object key.
   */
  const CONFIG_NAME = 'drupal_content_validator.settings';

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Runs all active rules for the given node's bundle.
   */
  public function validate(NodeInterface $node): ValidationResult {
    $result = new ValidationResult();
    $rules  = $this->getRulesForBundle($node->bundle());

    foreach ($rules as $rule_id => $rule) {
      if (empty($rule['enabled'])) {
        continue;
      }

      $method = 'validate' . str_replace('_', '', ucwords($rule_id, '_'));

      if (method_exists($this, $method)) {
        $this->$method($node, $result, $rule);
      }
    }

    $this->loggerFactory->get('content_validator')->info(
      'Validated node @nid (@bundle): @status',
      [
        '@nid'    => $node->id() ?? 'new',
        '@bundle' => $node->bundle(),
        '@status' => $result->isValid() ? 'PASSED' : 'FAILED',
      ]
    );

    return $result;
  }

  // ---------------------------------------------------------------------------
  // Built-in validation rules
  // ---------------------------------------------------------------------------

  /**
   * Rule: minimum body length.
   */
  protected function validateMinBodyLength(NodeInterface $node, ValidationResult $result, array $config): void {
    $min = (int) ($config['min_length'] ?? 100);

    if (!$node->hasField('body')) {
      return;
    }

    $length = mb_strlen(strip_tags($node->get('body')->value ?? ''));

    if ($length < $min) {
      $result->addError(sprintf(
        'Body text is too short (%d chars). Minimum required: %d.',
        $length,
        $min
      ));
    }
  }

  /**
   * Rule: title must not contain forbidden words.
   */
  protected function validateForbiddenWords(NodeInterface $node, ValidationResult $result, array $config): void {
    $words = array_filter(array_map('trim', explode(',', $config['words'] ?? '')));

    if (empty($words)) {
      return;
    }

    $title = mb_strtolower($node->label() ?? '');

    foreach ($words as $word) {
      if (str_contains($title, mb_strtolower($word))) {
        $result->addError(sprintf(
          'Title contains forbidden word: "%s".',
          $word
        ));
      }
    }
  }

  /**
   * Rule: required fields must not be empty.
   */
  protected function validateRequiredFields(NodeInterface $node, ValidationResult $result, array $config): void {
    $fields = array_filter(array_map('trim', explode(',', $config['fields'] ?? '')));

    foreach ($fields as $field_name) {
      if (!$node->hasField($field_name)) {
        continue;
      }

      if ($node->get($field_name)->isEmpty()) {
        $result->addError(sprintf(
          'Required field "%s" is empty.',
          $field_name
        ));
      }
    }
  }

  /**
   * Rule: node must have at least one image.
   */
  protected function validateHasImage(NodeInterface $node, ValidationResult $result, array $config): void {
    $field = $config['field'] ?? 'field_image';

    if (!$node->hasField($field)) {
      return;
    }

    if ($node->get($field)->isEmpty()) {
      $result->addError(sprintf(
        'Content must include at least one image in field "%s".',
        $field
      ));
    }
  }

  // ---------------------------------------------------------------------------
  // Config helpers
  // ---------------------------------------------------------------------------

  /**
   * Returns the rule config for a given bundle.
   *
   * @return array<string, array<string, mixed>>
   */
  public function getRulesForBundle(string $bundle): array {
    $config = $this->configFactory->get(self::CONFIG_NAME);
    return $config->get("bundles.$bundle.rules") ?? [];
  }

  /**
   * Whether validation is enabled for a content type.
   */
  public function isBundleEnabled(string $bundle): bool {
    $config = $this->configFactory->get(self::CONFIG_NAME);
    return (bool) $config->get("bundles.$bundle.enabled");
  }

  /**
   * Whether validation errors should block publishing.
   */
  public function isBlockingEnabled(string $bundle): bool {
    $config = $this->configFactory->get(self::CONFIG_NAME);
    return (bool) $config->get("bundles.$bundle.blocking");
  }

  /**
   * Returns all available content type bundles.
   *
   * @return string[]
   */
  public function getAvailableBundles(): array {
    $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    return array_keys($types);
  }

}
