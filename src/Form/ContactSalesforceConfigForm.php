<?php

/**
 * @file
 * Contains \Drupal\contact_salesforce\Form\SalesforceContactSettings.
 */

namespace Drupal\contact_salesforce\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Define the contact salesforce configuration form.
 */
class ContactSalesforceConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'contact_salesforce_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'contact_salesforce.config'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('contact_salesforce.config');

    $form['organization_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization ID'),
      '#description' => $this->t('Input the salesforce organization identifier.'),
      '#default_value' => $config->get('organization_id'),
      '#required' => TRUE,
      '#size' => 25,
    ];
    $form['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endpoint'),
      '#description' => $this->t('Input the salesforce service endpoint.'),
      '#default_value' => $config->get('endpoint'),
      '#required' => TRUE,
    ];
    $form['mapping_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Mapping Fields'),
      '#description' => $this->t('Input the allowed salesforce mapping fields.
        <br/><strong>Note:</strong> Enter one value per line, in the format
        key|label.'),
      '#row' => 5,
      '#required' => TRUE,
      '#default_value' => $config->get('mapping_fields'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('contact_salesforce.config');
    $config->setData($form_state->cleanValues()->getValues())->save();
  }

}
