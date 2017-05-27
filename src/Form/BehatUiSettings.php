<?php

/**
 * @file
 * Contains \Drupal\behat_ui\Form\BehatUiSettings.
 */

namespace Drupal\behat_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class BehatUiSettings extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'behat_ui_settings';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {

    $pidfile = \Drupal::config('behat_ui.settings')->get('behat_ui_pidfile');
    $pid = empty($pidfile) ? '' : file_get_contents($pidfile);
    $outfile = \Drupal::config('behat_ui.settings')->get('behat_ui_outfile');
    $output = empty($outfile) ? '' : file_get_contents($outfile);

    $form['#attached']['library'][] = 'behat_ui/behat_ui';

    $form['behat_ui_behat_bin_path'] = [
      '#title' => t('Path to Behat binary'),
      '#description' => t('Absolute or relative to the path below.'),
      '#type' => 'textfield',
      '#default_value' => \Drupal::config('behat_ui.settings')->get('behat_ui_behat_bin_path'),
      '#required' => TRUE,
    ];

    $form['behat_ui_behat_config_path'] = [
      '#title' => t('Directory path where Behat configuration file (behat.yml) is located'),
      '#description' => t('No need to include behat.yml on it, neither a trailing slash at the end. Relative paths are relative to Drupal root.'),
      '#type' => 'textfield',
      '#default_value' => \Drupal::config('behat_ui.settings')->get('behat_ui_behat_config_path'),
      '#required' => TRUE,
    ];

    $form['behat_ui_http_user'] = [
      '#title' => t('HTTP Authentication User'),
      '#type' => 'textfield',
      '#default_value' => \Drupal::config('behat_ui.settings')->get('behat_ui_http_user'),
    ];

    $form['behat_ui_http_password'] = [
      '#title' => t('HTTP Authentication Password'),
      '#type' => 'password',
      '#default_value' => \Drupal::config('behat_ui.settings')->get('behat_ui_http_password'),
    ];

    $form['behat_ui_http_auth_headless_only'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable HTTP authentication only for headless testing'),
      '#default_value' => \Drupal::config('behat_ui.settings')->get('behat_ui_http_auth_headless_only'),
      '#description' => t('Sometimes testing using Selenium (or other driver that allows JavaScript) does not handle HTTP authentication well, for example when you have some link with some JavaScript behavior attached. On these cases, you may enable this HTTP authentication only for headless testing and find another solution for drivers that allow JavaScript (for example, with Selenium + JavaScript you can use the extension Auto Auth and save the credentials on a Firefox profile).'),
    ];

    $label = t('Running <small><a href="#" id="behat-ui-kill">(kill)</a></small>');
    $class = 'running';
    if (!$pid) {
      $label = t('Not running');
      $class = '';
    }
    $form['behat_ui_status'] = [
      '#type' => 'markup',
      '#markup' => '<p id="behat-ui-status" class="' . $class . '">' . t('Status:') . ' <span>' . $label . '</span></p>',
    ];

    $form['behat_ui_output'] = [
      '#title' => t('Tests output'),
      '#type' => 'markup',
      '#markup' => '<div id="behat-ui-output">' . $output . '</div>',
    ];

    $form['#attached']['library'][] = 'behat_ui/behat_ui_new';
    $form['#attached']['library'][] = 'behat_ui/modal';

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Run tests'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    global $base_root, $user;

    // Paths.
    \Drupal::configFactory()->getEditable('behat_ui.settings')->set('behat_ui_behat_bin_path', $form_state['values']['behat_ui_behat_bin_path'])->save();
    \Drupal::configFactory()->getEditable('behat_ui.settings')->set('behat_ui_behat_config_path', $form_state['values']['behat_ui_behat_config_path'])->save();

    $behat_bin = _behat_ui_get_behat_bin_path();
    $behat_config_path = _behat_ui_get_behat_config_path();

    // HTTP authentication.
    \Drupal::configFactory()->getEditable('behat_ui.settings')->set('behat_ui_http_user', $form_state['values']['behat_ui_http_user'])->save();
    if (!empty($form_state['values']['behat_ui_http_password'])) {
      \Drupal::configFactory()->getEditable('behat_ui.settings')->set('behat_ui_http_password', $form_state['values']['behat_ui_http_password'])->save();
    }
    \Drupal::configFactory()->getEditable('behat_ui.settings')->set('behat_ui_http_auth_headless_only', $form_state['values']['behat_ui_http_auth_headless_only'])->save();
    $username = \Drupal::config('behat_ui.settings')->get('behat_ui_http_user');
    $password = \Drupal::config('behat_ui.settings')->get('behat_ui_http_password');

    $url = $base_root;
    if (!empty($username) && !empty($password) && !\Drupal::config('behat_ui.settings')->get('behat_ui_http_auth_headless_only')) {
      $url = preg_replace('/^(https?:\/\/)/', "$1$username:$password@", $url);
      $url = preg_replace('/([^\/])$/', "$1/", $url);
    }

    // Run tests.
    $pidfile = \Drupal::config('behat_ui.settings')->get('behat_ui_pidfile');
    $pid = empty($pidfile) ? 0 : intval(trim(file_get_contents($pidfile)));
    $outfile = \Drupal::config('behat_ui.settings')->get('behat_ui_outfile');

    if (!$pid && empty($file)) {

      $file_user_time = 'user-' . $user->uid . '-' . date('Y-m-d_h-m-s');

      $outfile = $behat_config_path . '/logs/behat-ui-' . $file_user_time . '.log';
      $pidfile = $behat_config_path . '/pids/behat-ui-' . $file_user_time . '.pid';
      $report_dir = 'reports/report-' . $file_user_time;

      exec("cd $behat_config_path; $behat_bin --format pretty --out std --format html --out $report_dir > $outfile & echo $! > $pidfile");

      \Drupal::configFactory()->getEditable('behat_ui.settings')->set('behat_ui_pidfile', $pidfile)->save();
      \Drupal::configFactory()->getEditable('behat_ui.settings')->set('behat_ui_outfile', $outfile)->save();
      \Drupal::configFactory()->getEditable('behat_ui.settings')->set('behat_ui_html_report_dir', $report_dir)->save();
    }
    else {
      drupal_set_message(t('Tests are already running.'));
    }
  }
}
