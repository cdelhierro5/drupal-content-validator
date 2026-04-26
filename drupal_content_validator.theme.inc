<?php

/**
 * @file
 * Theme hooks for drupal_content_validator.
 */

/**
 * Implements hook_theme().
 */
function drupal_content_validator_theme(): array {
  return [
    'content_validator_block' => [
      'variables' => [
        'is_valid'   => TRUE,
        'errors'     => [],
        'warnings'   => [],
        'collapsed'  => FALSE,
        'node_label' => '',
      ],
    ],
  ];
}
