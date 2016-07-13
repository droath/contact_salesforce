<?php

/**
 * @file
 * Contains \Drupal\contact_salesforce\SalesforceContactService.
 */

namespace Drupal\contact_salesforce;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\contact\Entity\ContactForm;
use Drupal\contact\MessageInterface;
use Drupal\field\Entity\FieldConfig;
use Psr\Log\LoggerInterface;

/**
 * Define contact salesforce class.
 */
class ContactSalesforceService {

  use StringTranslationTrait;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Logger object.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Contact salesforce service constructor.
   */
  public function __construct(ConfigFactoryInterface $config, LoggerInterface $logger) {
    $this->config = $config->get('contact_salesforce.config');
    $this->logger = $logger;
  }

  /**
   * Add additional form elements to contact form.
   *
   * @param array &$form
   *   An array of form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   A form state object.
   */
  public function addAdditionalFormElements(&$form, FormStateInterface $form_state) {
    $entity = $form_state->getFormObject()->getEntity();
    $config = $entity->getThirdPartySetting('contact_salesforce', 'configuration', []);

    $form['contact_salesforce'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Salesforce Contact'),
      '#tree' => TRUE,
      '#weight' => 99,
    ];
    $form['contact_salesforce']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send results to salesforce'),
      '#default_value' => isset($config['enable']) ? $config['enable'] : NULL,
    ];
    $form['contact_salesforce']['field_mappings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Field Mappings'),
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="contact_salesforce[enable]"]' => ['checked' => TRUE]
        ]
      ],
    ];

    foreach ($this->loadEntityFieldDefinitions($entity) as $field_name => $definition) {
      $form['contact_salesforce']['field_mappings'][$field_name] = [
        '#type' => 'select',
        '#title' => $this->t('@name', ['@name' => $definition->getLabel()]),
        '#options' => $this->getServiceFieldOptions(),
        '#empty_option' => $this->t('- None -'),
        '#default_value' => isset($config['field_mappings'][$field_name])
          ? $config['field_mappings'][$field_name]
          : NULL
      ];
    }

    $form['#entity_builders'][] = [$this, 'addAdditionalFormItems'];
  }

  /**
   * Add additional form items to entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param \Drupal\Core\Config\Entity\ThirdPartySettingsInterface $entity
   *   An entity object.
   * @param array &$form
   *   An array of form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   A form state object.
   */
  public function addAdditionalFormItems($entity_type, ThirdPartySettingsInterface $entity, &$form, FormStateInterface $form_state) {
    $settings = $form_state->getValue('contact_salesforce');

    if ($settings['enable']) {
      $settings['field_mappings'] = array_filter($settings['field_mappings']);
      $entity->setThirdPartySetting(
        'contact_salesforce', 'configuration', $settings
      );
    }
    else {
      $entity->unsetThirdPartySetting('contact_salesforce', 'configuration');
    }
  }

  /**
   * Send contact field results to salesforce.
   *
   * @param \Drupal\contact\MessageInterface $entity
   *   A contact entity object.
   */
  public function insertContactEntity(MessageInterface $entity) {
    $entity_type   = ContactForm::load($entity->bundle());
    $configuration = $entity_type
      ->getThirdPartySetting('contact_salesforce', 'configuration', []);

    // Continue if the contact type has been enabled salesforce.
    if (!isset($configuration['enable']) || !$configuration['enable']) {
      return;
    }
    $mappings = $configuration['field_mappings'];

    try {
      \Drupal::httpClient()
        ->request('POST', $this->config->get('endpoint'), [
          'form_params' => $this->getEntityFormParameters($entity, $mappings)
        ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Exception: ' . $e->getMessage());
    }
  }

  /**
   * Get salesforce service field options.
   *
   * @return array
   *   An array of salesforce field options.
   */
  protected function getServiceFieldOptions() {
    $options = &drupal_static(__FUNCTION__, []);

    if (empty($options)) {
      $string = $this->config->get('mapping_fields');

      foreach (explode("\r\n", $string) as $value) {
        list($key, $label) = array_map(
          '\Drupal\Component\Utility\Html::escape', explode('|', $value)
        );
        $options[$key] = $label;
      }
    }

    return $options;
  }

  /**
   * Get salesforce contact form parameters.
   *
   * @param MessageInterface $entity
   *   A contact entity.
   * @param array $mappings
   *   A array of field mappings, keyed by entity field name.
   *
   * @return array
   *   An array of contract form parameters.
   */
  public function getEntityFormParameters(MessageInterface $entity, array $mappings) {
    $parameters = [
      'oid' => $this->config->get('organization_id')
    ];

    foreach ($mappings as $field_name => $salesforce_field) {
      if (empty($salesforce_field) || isset($parameters[$salesforce_field])) {
        continue;
      }

      if (!$entity->hasField($field_name) || $entity->{$field_name}->isEmpty()) {
        continue;
      }

      $parameters[$salesforce_field] = $entity->{$field_name}->value;
    }

    return $parameters;
  }

  /**
   * Load entity field definitions.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity object.
   *
   * @return array
   *   An array of entity field definitions.
   */
  protected function loadEntityFieldDefinitions(EntityInterface $entity) {
    $definitions = [];
    $entity_type = $entity->getEntityType();

    if ($entity_type instanceof EntityTypeInterface) {
      $fields = $this->entityFieldDefinitions($entity_type->getBundleOf(), $entity->id());
      foreach ($fields as $field_name => $definition) {
        if ($definition->isReadOnly()) {
          continue;
        }

        $definitions[$field_name] = $definition;
      }
    }

    return $definitions;
  }

  /**
   * Get entity field manager.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   *   An entity field manager.
   */
  protected function entityFieldManager() {
    return \Drupal::service('entity_field.manager');
  }

  /**
   * Get entity field definitions.
   *
   * @param string $entity_type_id
   *   An entity type identifier.
   * @param string $bundle
   *   An entity bundle.
   *
   * @return array
   *   An array of fields.
   */
  protected function entityFieldDefinitions($entity_type_id, $bundle) {
    return $this->entityFieldManager()->getFieldDefinitions($entity_type_id, $bundle);
  }
}
