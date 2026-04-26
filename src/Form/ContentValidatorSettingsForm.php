<?php

namespace Drupal\drupal_content_validator\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\drupal_content_validator\Service\ContentValidatorService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Administration form for Content Validator settings.
 *
 * Allows site admins to enable/disable validation per content type,
 * choose whether errors are blocking, and configure each rule's parameters.
 */
class ContentValidatorSettingsForm extends ConfigFormBase {

  public function __construct(
    protected ContentValidatorService $validator,
    ...$args
  ) {
    parent::__construct(...$args);
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('drupal_content_validator.validator'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [ContentValidatorService::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'content_validator_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config  = $this->config(ContentValidatorService::CONFIG_NAME);
    $bundles = $this->validator->getAvailableBundles();

    $form['#tree'] = TRUE;

    $form['intro'] = [
      '#markup' => '<p>' . $this->t(
        'Configure validation rules per content type. Blocking rules prevent publication when they fail.'
      ) . '</p>',
    ];

    foreach ($bundles as $bundle) {
      $bundle_config = $config->get("bundles.$bundle") ?? [];

      $form['bundles'][$bundle] = [
        '#type'        => 'details',
        '#title'       => $this->t('Content type: @bundle', ['@bundle' => $bundle]),
        '#open'        => !empty($bundle_config['enabled']),
      ];

      $form['bundles'][$bundle]['enabled'] = [
        '#type'          => 'checkbox',
        '#title'         => $this->t('Enable validation for <em>@bundle</em>', ['@bundle' => $bundle]),
        '#default_value' => $bundle_config['enabled'] ?? FALSE,
      ];

      $enabled_state = [
        'visible' => [":input[name='bundles[$bundle][enabled]']" => ['checked' => TRUE]],
      ];

      $form['bundles'][$bundle]['blocking'] = [
        '#type'          => 'checkbox',
        '#title'         => $this->t('Block publication on validation failure'),
        '#default_value' => $bundle_config['blocking'] ?? TRUE,
        '#states'        => $enabled_state,
      ];

      // ---- Rules ----

      $form['bundles'][$bundle]['rules'] = [
        '#type'   => 'fieldset',
        '#title'  => $this->t('Rules'),
        '#states' => $enabled_state,
      ];

      // Rule: minimum body length.
      $form['bundles'][$bundle]['rules']['min_body_length'] = [
        '#type'  => 'fieldset',
        '#title' => $this->t('Minimum body length'),
      ];
      $form['bundles'][$bundle]['rules']['min_body_length']['enabled'] = [
        '#type'          => 'checkbox',
        '#title'         => $this->t('Enable'),
        '#default_value' => $bundle_config['rules']['min_body_length']['enabled'] ?? FALSE,
      ];
      $form['bundles'][$bundle]['rules']['min_body_length']['min_length'] = [
        '#type'          => 'number',
        '#title'         => $this->t('Minimum characters'),
        '#default_value' => $bundle_config['rules']['min_body_length']['min_length'] ?? 100,
        '#min'           => 1,
      ];

      // Rule: forbidden words.
      $form['bundles'][$bundle]['rules']['forbidden_words'] = [
        '#type'  => 'fieldset',
        '#title' => $this->t('Forbidden words in title'),
      ];
      $form['bundles'][$bundle]['rules']['forbidden_words']['enabled'] = [
        '#type'          => 'checkbox',
        '#title'         => $this->t('Enable'),
        '#default_value' => $bundle_config['rules']['forbidden_words']['enabled'] ?? FALSE,
      ];
      $form['bundles'][$bundle]['rules']['forbidden_words']['words'] = [
        '#type'          => 'textfield',
        '#title'         => $this->t('Forbidden words (comma-separated)'),
        '#default_value' => $bundle_config['rules']['forbidden_words']['words'] ?? '',
        '#placeholder'   => 'spam, test, draft',
      ];

      // Rule: required fields.
      $form['bundles'][$bundle]['rules']['required_fields'] = [
        '#type'  => 'fieldset',
        '#title' => $this->t('Required fields'),
      ];
      $form['bundles'][$bundle]['rules']['required_fields']['enabled'] = [
        '#type'          => 'checkbox',
        '#title'         => $this->t('Enable'),
        '#default_value' => $bundle_config['rules']['required_fields']['enabled'] ?? FALSE,
      ];
      $form['bundles'][$bundle]['rules']['required_fields']['fields'] = [
        '#type'          => 'textfield',
        '#title'         => $this->t('Field machine names (comma-separated)'),
        '#default_value' => $bundle_config['rules']['required_fields']['fields'] ?? '',
        '#placeholder'   => 'field_summary, field_category',
      ];

      // Rule: has image.
      $form['bundles'][$bundle]['rules']['has_image'] = [
        '#type'  => 'fieldset',
        '#title' => $this->t('Must contain image'),
      ];
      $form['bundles'][$bundle]['rules']['has_image']['enabled'] = [
        '#type'          => 'checkbox',
        '#title'         => $this->t('Enable'),
        '#default_value' => $bundle_config['rules']['has_image']['enabled'] ?? FALSE,
      ];
      $form['bundles'][$bundle]['rules']['has_image']['field'] = [
        '#type'          => 'textfield',
        '#title'         => $this->t('Image field machine name'),
        '#default_value' => $bundle_config['rules']['has_image']['field'] ?? 'field_image',
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config(ContentValidatorService::CONFIG_NAME);

    foreach ($form_state->getValue('bundles') as $bundle => $values) {
      $config->set("bundles.$bundle", $values);
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
