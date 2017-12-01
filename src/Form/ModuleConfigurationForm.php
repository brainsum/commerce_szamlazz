<?php

namespace Drupal\commerce_szamlazz\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that configures forms module settings.
 */
class ModuleConfigurationForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'szamlazz_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_szamlazz.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    // Config get for default values;.
    $config = $this->config('commerce_szamlazz.settings');

    $form['szamlazz_user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Szamlazz api user name'),
      '#description' => $this->t('User name to be used when posting to commerce_szamlazz.hu'),
      '#default_value' => $config->get('szamlazz_user'),
    ];
    $form['szamlazz_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Szamlazz api user password.'),
      '#description' => $this->t('Password for commerce_szamlazz api user.'),
      '#default_value' => $config->get('szamlazz_password'),
    ];
    $form['szamlazz_download'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Download invoice after generation.'),
      '#default_value' => $config->get('szamlazz_download'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::configFactory()->getEditable('commerce_szamlazz.settings')
      // Set the submitted configuration setting.
      ->set('szamlazz_user', $form_state->getValue('szamlazz_user'))
      ->set('szamlazz_password', $form_state->getValue('szamlazz_password'))
      ->set('szamlazz_download', $form_state->getValue('szamlazz_download'))
      ->save();
  }

}
