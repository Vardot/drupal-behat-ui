<?php /**
 * @file
 * Contains \Drupal\behat_ui\Controller\DefaultController.
 */

namespace Drupal\behat_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Default controller for the behat_ui module.
 */
class DefaultController extends ControllerBase {

  public function behatUiStatus() {
    $running = FALSE;
    $tempstore = \Drupal::service('user.private_tempstore')->get('behat_ui');
    
    $config = \Drupal::config('behat_ui.settings');
    $behat_ui_behat_bin_path = $config->get('behat_ui_behat_bin_path');
    $behat_ui_behat_config_path = $config->get('behat_ui_behat_config_path');
    
    $behat_ui_html_report_dir = $config->get('behat_ui_html_report_dir');
    $behat_ui_html_report_file = $config->get('behat_ui_html_report_file');
    
    $behat_ui_http_auth_headless_only = $config->get('behat_ui_http_auth_headless_only');
    
    $pid = $tempstore->get('behat_ui_pid');
    $outfile = $tempstore->get('behat_ui_output_log');

    if ($pid && behat_ui_process_running($pid)) {
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

  public function behatUiAutocomplete($string) {
    $matches = [];

    $steps = explode('<br />', $this->behatUiAutocompleteDefinitionSteps());
    foreach ($steps as $step) {
      $title = preg_replace('/^\s*(Given|Then|When|And|But) \/\^/', '', $step);
      $title = preg_replace('/\$\/$/', '', $title);
      if (preg_match('/' . preg_quote($string) . '/', $title)) {
        $matches[$title] = $title;
      }
    }

    return new JsonResponse($matches);
  }

  public function behatUiKill() {
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

  public function behatUiDownload($format) {

    $behat_bin = _behat_ui_get_behat_bin_path();
    $behat_config_path = _behat_ui_get_behat_config_path();

    if (($format === 'html' || $format === 'txt') && file_exists($output)) {

      $output = \Drupal::config('behat_ui.settings')->get('behat_ui_html_report_dir');

      $headers = [
        'Content-Type' => 'text/x-behat',
        'Content-Disposition' => 'attachment; filename="behat_ui_output.' . $format . '"',
        'Content-Length' => filesize($output),
      ];
      foreach ($headers as $key => $value) {
        drupal_add_http_header($key, $value);
      }
      if ($format === 'html') {
        readfile($output);
      }
      elseif ($format === 'txt') {
        drupal_add_http_header('Connection', 'close');

        $output = \Drupal::config('behat_ui.settings')->get('behat_ui_outfile');
        $plain = file_get_contents($output);
        echo drupal_html_to_text($plain);
      }
    }
    else {
      \Drupal::messenger()->addError(t('Output file not found. Please run the tests again in order to generate it.'));
      drupal_goto('admin/config/development/behat_ui');
    }
  }
  
  /**
   * Behat definition steps.
   */
  public function behatUiAutocompleteDefinitionSteps() {

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
  public function behatUiDefinitionSteps() {

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
  public function behatUiDefinitionStepsWithInfo() {

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

    $formatedBehatSteps = $formatCodeBeginValue . str_replace('default |',  $formatCodeEndBeginValue, $formatedBehatSteps);

    return $formatedBehatSteps;
  }

}
