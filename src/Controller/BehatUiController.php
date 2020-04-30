<?php

namespace Drupal\behat_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Default Behat Ui controller for the Behat Ui module.
 */
class BehatUiController extends ControllerBase {

  /**
   * Get Behat test status.
   */
  public function getTestStatus() {
    $running = FALSE;
    $config = \Drupal::config('behat_ui.settings');
    $behat_ui_behat_bin_path = $config->get('behat_ui_behat_bin_path');
    $behat_ui_behat_config_path = $config->get('behat_ui_behat_config_path');

    $behat_ui_html_report_dir = $config->get('behat_ui_html_report_dir');
    $behat_ui_html_report_file = $config->get('behat_ui_html_report_file');

    $behat_ui_http_auth_headless_only = $config->get('behat_ui_http_auth_headless_only');

    $tempstore = \Drupal::service('user.private_tempstore')->get('behat_ui');
    $pid = $tempstore->get('behat_ui_pid');

    if (isset($pid) && $this->processRunning($pid)) {
      $running = TRUE;
    }
    if ($behat_ui_http_auth_headless_only && $behat_ui_html_report_dir) {
      $output = file_get_contents($behat_ui_html_report_dir . '/' . $behat_ui_html_report_file);
    }
    elseif ($outfile && file_exists($outfile)) {
      $output = nl2br(htmlentities(file_get_contents($outfile)));
    }
    return new JsonResponse(['running' => $running, 'output' => $output]);
  }

  /**
   * Auto complete.
   */
  public function autocomplete($string) {
    $matches = [];

    $steps = explode('<br />', $this->autocompleteDefinitionSteps());
    foreach ($steps as $step) {
      $title = preg_replace('/^\s*(Given|Then|When|And|But) \/\^/', '', $step);
      $title = preg_replace('/\$\/$/', '', $title);
      if (preg_match('/' . preg_quote($string) . '/', $title)) {
        $matches[$title] = $title;
      }
    }

    return new JsonResponse($matches);
  }

  /**
   * Kill running test.
   */
  public function kill() {
    $response = FALSE;
    $tempstore = \Drupal::service('user.private_tempstore')->get('behat_ui');
    $pid = $tempstore->get('behat_ui_pid');

    if ($pid) {
      try {
        $response = posix_kill($pid, SIGKILL);
      }
      catch (Exception $e) {
        $response = FALSE;
      }
    }
    return new JsonResponse(['response' => $response]);
  }

  /**
   * Download.
   */
  public function download($format) {

    $config = \Drupal::config('behat_ui.settings');

    if (($format === 'html' || $format === 'txt')) {

      $headers = [
        'Content-Type' => 'text/x-behat',
        'Content-Disposition' => 'attachment; filename="behat_ui_output.' . $format . '"',
        'Content-Length' => filesize($output),
      ];
      foreach ($headers as $key => $value) {
        drupal_add_http_header($key, $value);
      }
      if ($format === 'html') {

        $behat_ui_html_report_dir = $config->get('behat_ui_html_report_dir');
        $behat_ui_html_report_file = $config->get('behat_ui_html_report_file');
        $output = $behat_ui_html_report_dir . '/' . $behat_ui_html_report_file;
        readfile($output);
      }
      elseif ($format === 'txt') {
        drupal_add_http_header('Connection', 'close');

        $behat_ui_log_report_dir = $config->get('behat_ui_log_report_dir');
        $behat_ui_log_report_file = $config->get('behat_ui_log_report_file');

        $output = $behat_ui_log_report_dir . '/' . $behat_ui_log_report_file;
        $plain = file_get_contents($output);
        echo drupal_html_to_text($plain);
      }
    }
    else {
      \Drupal::messenger()->addError($this->t('Output file not found. Please run the tests again in order to generate it.'));
      drupal_goto('behat_ui.run_tests');
    }
  }

  /**
   * Auto complete behat definition steps.
   */
  public function getAutocompleteDefinitionSteps() {

    $config = \Drupal::config('behat_ui.settings');
    $behat_bin = $config->get('behat_ui_behat_bin_path');
    $behat_config_path = $config->get('behat_ui_behat_config_path');

    $cmd = "cd $behat_config_path; $behat_bin -dl | sed 's/^\s*//g'";
    $output = shell_exec($cmd);
    $output = nl2br(htmlentities($output));

    $build = [
      '#markup' => $this->formatBehatSteps($output, '', ''),
    ];
    return $build;
  }

  /**
   * Behat definition steps.
   */
  public function getDefinitionSteps() {

    $config = \Drupal::config('behat_ui.settings');
    $behat_bin = $config->get('behat_ui_behat_bin_path');
    $behat_config_path = $config->get('behat_ui_behat_config_path');

    $cmd = "cd $behat_config_path; $behat_bin -dl | sed 's/^\s*//g'";
    $output = shell_exec($cmd);
    $output = nl2br(htmlentities($output));

    $build = [
      '#markup' => $this->formatBehatSteps($output, '<p>', '</p><p class="messages messages--status color-success">'),
    ];
    return $build;
  }

  /**
   * Behat definitions steps with extended info.
   */
  public function getDefinitionStepsWithInfo() {

    $config = \Drupal::config('behat_ui.settings');
    $behat_bin = $config->get('behat_ui_behat_bin_path');
    $behat_config_path = $config->get('behat_ui_behat_config_path');

    $cmd = "cd $behat_config_path; $behat_bin -di";
    $output = shell_exec($cmd);
    $output = nl2br(htmlentities($output));

    $build = [
      '#markup' => $this->formatBehatSteps($output),
    ];
    return $build;
  }

  /**
   * Format Behat Steps.
   */
  public function formatBehatSteps($behatSteps, $formatCodeBeginValue = '<p><code>', $formatCodeEndBeginValue = '</code></p><p class="messages messages--status color-success"><code>') {

    $formatedBehatSteps = str_replace('Given ', '<b>Given</b> ', $behatSteps);
    $formatedBehatSteps = str_replace('When ', '<b>When</b> ', $formatedBehatSteps);
    $formatedBehatSteps = str_replace('Then ', '<b>Then</b> ', $formatedBehatSteps);
    $formatedBehatSteps = str_replace('And ', '<b>And</b> ', $formatedBehatSteps);
    $formatedBehatSteps = str_replace('But ', '<b>But</b> ', $formatedBehatSteps);

    $formatedBehatSteps = str_replace('Given|', '<b>Given</b>|', $behatSteps);
    $formatedBehatSteps = str_replace('When|', '<b>When</b>|', $formatedBehatSteps);
    $formatedBehatSteps = str_replace('Then|', '<b>Then</b>|', $formatedBehatSteps);
    $formatedBehatSteps = str_replace('And|', '<b>And</b>|', $formatedBehatSteps);
    $formatedBehatSteps = str_replace('But|', '<b>But</b>|', $formatedBehatSteps);

    $formatedBehatSteps = $formatCodeBeginValue . str_replace('default |', $formatCodeEndBeginValue, $formatedBehatSteps);

    return $formatedBehatSteps;
  }

  /**
   * Helper function to check if a process with given PID is running or not.
   *
   * @param $pid
   *
   * @return bool
   */
  public function processRunning($pid) {
    $isRunning = FALSE;
    if (posix_kill(intval($pid), 0)) {
      $isRunning = TRUE;
    }
    return $isRunning;
  }

}
