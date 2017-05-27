<?php /**
 * @file
 * Contains \Drupal\behat_ui\Controller\DefaultController.
 */

namespace Drupal\behat_ui\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the behat_ui module.
 */
class DefaultController extends ControllerBase {

  public function _behat_ui_status() {

    $behat_config_path = _behat_ui_get_behat_config_path();

    $pidfile = \Drupal::config('behat_ui.settings')->get('behat_ui_pidfile');
    $pid = empty($pidfile) ? 0 : intval(trim(file_get_contents($pidfile)));
    $outfile = \Drupal::config('behat_ui.settings')->get('behat_ui_outfile');
    $output = file_get_contents($behat_config_path . '/' . \Drupal::config('behat_ui.settings')->get('behat_ui_html_report_dir') . '/index.html');

    $running = FALSE;

    if ($pid) {
      try {
        $result = shell_exec(sprintf("ps %d", $pid));
        if (count(preg_split("/\n/", $result)) > 2) {
          $running = TRUE;
        }
      }
      

        catch (Exception $e) {
        // Do nothing.
      }

      if (!$running) {
        \Drupal::configFactory()->getEditable('behat_ui.settings')->set('behat_ui_pidfile', '')->save();
        \Drupal::configFactory()->getEditable('behat_ui.settings')->set('behat_ui_outfile', '')->save();
        \Drupal::configFactory()->getEditable('behat_ui.settings')->set('behat_ui_html_report_dir', '')->save();
      }
    }

    drupal_json_output(['running' => $running, 'output' => $output]);
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

    drupal_json_output($matches);
  }

  public function _behat_ui_kill() {
    $pidfile = \Drupal::config('behat_ui.settings')->get('behat_ui_pidfile');
    $pid = empty($pidfile) ? 0 : intval(trim(file_get_contents($pidfile)));
    $response = FALSE;

    if ($pid) {
      try {
        $response = posix_kill($pid, SIGKILL);
      }
      
        catch (Exception $e) {
        $response = FALSE;
      }
    }

    drupal_json_output(['response' => $response]);
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
