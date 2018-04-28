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

  public function _behat_ui_status() {
    $running = FALSE;
    $tempstore = \Drupal::service('user.private_tempstore')->get('behat_ui');
    $pid = $tempstore->get('behat_ui_pid');
    $outfile = $tempstore->get('behat_ui_output_log');
    $reportdir = $tempstore->get('behat_ui_report_dir');
    $enableHtml = $tempstore->get('behat_ui_enable_html');

    if ($pid && behat_ui_process_running($pid)) {
      $running = TRUE;
    }
    if ($enableHtml && $reportdir) {
      $output = file_get_contents($reportdir . '/index.html');
    }
    elseif ($outfile && file_exists($outfile)) {
      $output = nl2br(htmlentities(file_get_contents($outfile)));
    }
    return new JsonResponse(['running' => $running, 'output' => $output]);
  }

  public function _behat_ui_autocomplete($string) {
    $matches = [];

    $steps = explode('<br />', _behat_ui_steps());
    foreach ($steps as $step) {
      $title = preg_replace('/^\s*(Given|Then|When) \/\^/', '', $step);
      $title = preg_replace('/\$\/$/', '', $title);
      if (preg_match('/' . preg_quote($string) . '/', $title)) {
        $matches[$title] = $title;
      }
    }

    return new JsonResponse($matches);
  }

  public function _behat_ui_kill() {
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

  public function _behat_ui_download($format) {

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
      drupal_set_message(t('Output file not found. Please run the tests again in order to generate it.'), 'error');
      drupal_goto('admin/config/development/behat_ui');
    }
  }

}
