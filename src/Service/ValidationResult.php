<?php

namespace Drupal\drupal_content_validator\Service;

/**
 * Value object representing the result of a content validation run.
 */
class ValidationResult {

  /**
   * @var string[]
   */
  private array $errors = [];

  /**
   * @var string[]
   */
  private array $warnings = [];

  /**
   * Adds a blocking validation error.
   */
  public function addError(string $message): void {
    $this->errors[] = $message;
  }

  /**
   * Adds a non-blocking warning.
   */
  public function addWarning(string $message): void {
    $this->warnings[] = $message;
  }

  /**
   * Whether all rules passed (no errors).
   */
  public function isValid(): bool {
    return empty($this->errors);
  }

  /**
   * @return string[]
   */
  public function getErrors(): array {
    return $this->errors;
  }

  /**
   * @return string[]
   */
  public function getWarnings(): array {
    return $this->warnings;
  }

  /**
   * Returns a flat summary for logging.
   */
  public function getSummary(): string {
    if ($this->isValid()) {
      return 'All validation rules passed.';
    }

    return 'Errors: ' . implode(' | ', $this->errors);
  }

}
