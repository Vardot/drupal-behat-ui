<?php

/**
 * @file
 * Behat ui drush commands.
 */

/**
 * Implements hook_drush_command().
 */
function behat_ui_drush_command() {
  $items = [];

  // The 'behat-ui' command.
  $items['behat-ui'] = [
    'description' => "Runs your Behat testing suite.",
    'arguments' => [],
    'options' => [],
    'examples' => [
      'drush bui' => 'Runs the testing suite.',
    ],
    'aliases' => ['bui'],
    // No bootstrap at all.
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  ];

  return $items;
}

/**
 * Implements hook_drush_help().
 */
function behat_ui_drush_help($section) {
  switch ($section) {
    case 'drush:behat-ui':
      return dt("This command will run the Behat testing suite.");
  }
}

/**
 * Drush command callback.
 */
function drush_behat_ui() {
  global $base_root;

  $config = \Drupal::config('behat_ui.settings');

  $behat_ui_behat_config_path = $config->get('behat_ui_behat_config_path');
  $behat_ui_behat_features_path = $config->get('behat_ui_behat_features_path');

  $behat_ui_log_report_dir = $config->get('behat_ui_log_report_dir');

  $behat_ui_http_user = $config->get('behat_ui_http_user');
  $behat_ui_http_password = $config->get('behat_ui_http_password');
  $behat_ui_http_auth_headless_only = $config->get('behat_ui_http_auth_headless_only');

  $url = $base_root;
  if (isset($behat_ui_http_user)
    && !empty($behat_ui_http_user)
    && isset($behat_ui_http_password)
    && !empty($behat_ui_http_password)
    && isset($behat_ui_http_auth_headless_only)
    && $behat_ui_http_auth_headless_only == TRUE) {

    $basic_auth = "$behat_ui_http_user:$behat_ui_http_password@";
    $url = preg_replace('/^(https?:\/\/)/', $basic_auth, $url);
  }

  $behat_ui_behat_bin_path = $config->get('behat_ui_behat_bin_path');
  $behat_ui_behat_config_file = $config->get('behat_ui_behat_config_path');

  $command = '';
  if (isset($behat_ui_log_report_dir) && $behat_ui_log_report_dir != '') {

    if (\Drupal::service('file_system')->prepareDirectory($behat_ui_log_report_dir, FileSystemInterface::CREATE_DIRECTORY)) {
      $log_report_output_file = $behat_ui_log_report_dir . '/bethat-ui-test.log';
      $command = "cd $behat_ui_behat_config_path;$behat_ui_behat_bin_path --config=$behat_ui_behat_config_file $behat_ui_behat_features_path --format pretty --out std > $log_report_output_file";
    }
    else {
      drush_print(t('The Log Output directory does not exists or is not writable.'));
    }
  }
  else {
    drush_print(t('The Log directory and file is not configured.'));
  }

  $output = shell_exec($command);
  drush_print($output);
  drush_print(file_get_contents($log_report_output_file));
}
