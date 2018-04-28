<?php

/**
 * @file
 * Contains \Drupal\behat_ui\Form\BehatUiSettings.
 */

namespace Drupal\behat_ui\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class BehatUiSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'behat_ui_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['behat_ui.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $config = $this->config('behat_ui.settings');

    $form['behat_ui_behat_bin_path'] = [
      '#title' => t('Path to Behat binary'),
      '#description' => t('Absolute or relative to the path below.'),
      '#type' => 'textfield',
      '#default_value' => $config->get('behat_bin_path'),
      '#required' => TRUE,
    ];

    $form['behat_ui_behat_config_path'] = [
      '#title' => t('Directory path where Behat configuration file (behat.yml) is located'),
      '#description' => t('No need to include behat.yml on it, neither a trailing slash at the end. Relative paths are relative to Drupal root.'),
      '#type' => 'textfield',
      '#default_value' => $config->get('behat_config_path'),
      '#required' => TRUE,
    ];

    $form['behat_ui_http_user'] = [
      '#title' => t('HTTP Authentication User'),
      '#type' => 'textfield',
      '#default_value' => $config->get('http_user'),
    ];

    $form['behat_ui_http_password'] = [
      '#title' => t('HTTP Authentication Password'),
      '#type' => 'password',
      '#default_value' => $config->get('http_password'),
    ];

    $form['behat_ui_http_auth_headless_only'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable HTTP authentication only for headless testing'),
      '#default_value' => $config->get('http_auth_headless_only'),
      '#description' => t('Sometimes testing using Selenium (or other driver that allows JavaScript) does not handle HTTP authentication well, for example when you have some link with some JavaScript behavior attached. On these cases, you may enable this HTTP authentication only for headless testing and find another solution for drivers that allow JavaScript (for example, with Selenium + JavaScript you can use the extension Auto Auth and save the credentials on a Firefox profile).'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('behat_ui.settings');
    foreach ($form_state->getValues() as $key => $value) {
      if (strpos($key, 'behat_ui') !== FALSE) {
        $config->set(str_replace('behat_ui_', '', $key), $value);
      }
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
