<?php

namespace Drupal\drupal_content_validator\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\drupal_content_validator\Service\ContentValidatorService;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a configurable validation status block.
 *
 * Displays the current validation status of a node — useful in editorial
 * dashboards or content review workflows.
 *
 * @Block(
 *   id = "content_validator_status",
 *   admin_label = @Translation("Content Validation Status"),
 *   category = @Translation("Content Validator"),
 * )
 */
class ContentValidatorBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected ContentValidatorService $validator,
    protected RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('drupal_content_validator.validator'),
      $container->get('current_route_match'),
    );
  }

  // ---------------------------------------------------------------------------
  // Block configuration form
  // ---------------------------------------------------------------------------

  /**
   * {@inheritdoc}
   */
  public function blockForm(array $form, FormStateInterface $form_state): array {
    $config = $this->getConfiguration();

    $form['show_warnings'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Show warnings (non-blocking)'),
      '#default_value' => $config['show_warnings'] ?? TRUE,
    ];

    $form['show_when_valid'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Show block when content passes all rules'),
      '#default_value' => $config['show_when_valid'] ?? TRUE,
    ];

    $form['collapsed_on_valid'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Collapse block automatically when valid'),
      '#default_value' => $config['collapsed_on_valid'] ?? FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit(array $form, FormStateInterface $form_state): void {
    $this->setConfigurationValue('show_warnings', $form_state->getValue('show_warnings'));
    $this->setConfigurationValue('show_when_valid', $form_state->getValue('show_when_valid'));
    $this->setConfigurationValue('collapsed_on_valid', $form_state->getValue('collapsed_on_valid'));
  }

  // ---------------------------------------------------------------------------
  // Block rendering
  // ---------------------------------------------------------------------------

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $node = $this->routeMatch->getParameter('node');

    // Show nothing if not on a node page.
    if (!$node instanceof NodeInterface) {
      return [];
    }

    // Show nothing if validation is not enabled for this bundle.
    if (!$this->validator->isBundleEnabled($node->bundle())) {
      return [];
    }

    $config = $this->getConfiguration();
    $result = $this->validator->validate($node);

    // Optionally hide when valid.
    if ($result->isValid() && empty($config['show_when_valid'])) {
      return [];
    }

    return [
      '#theme'           => 'content_validator_block',
      '#is_valid'        => $result->isValid(),
      '#errors'          => $result->getErrors(),
      '#warnings'        => $config['show_warnings'] ? $result->getWarnings() : [],
      '#collapsed'       => $result->isValid() && !empty($config['collapsed_on_valid']),
      '#node_label'      => $node->label(),
      '#attached'        => ['library' => ['drupal_content_validator/validator']],
      '#cache'           => [
        'tags'    => $node->getCacheTags(),
        'context' => ['url.path'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return 0; // Always fresh — validation depends on node state.
  }

}
