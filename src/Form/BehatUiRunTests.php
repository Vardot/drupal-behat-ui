<?php

/**
 * @file
 * Contains \Drupal\behat_ui\Form\BehatUiRunTests.
 */

namespace Drupal\behat_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\Process\Process;
use Behat\Testwork\ServiceContainer\Configuration\ConfigurationLoader;

class BehatUiRunTests extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'behat_ui_run_tests';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'behat_ui/style';
    $form['#attached']['library'][] = 'behat_ui/run-tests-scripts';
    
    $config = \Drupal::config('behat_ui.settings');
    $behat_ui_behat_bin_path = $config->get('behat_ui_behat_bin_path');
    $behat_ui_behat_config_path = $config->get('behat_ui_behat_config_path');
    
    $behat_ui_html_report_dir = $config->get('behat_ui_html_report_dir');
    $behat_ui_html_report_file = $config->get('behat_ui_html_report_file');
    
    $behat_ui_http_auth_headless_only = $config->get('behat_ui_http_auth_headless_only');

    $pid = $config->get('behat_ui_pidfile');
    $outfile = $config->get('behat_ui_outfile');
 
    $form['submit_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run behat tests'),
    ];

    $label = $this->t('Not running');
    $class = '';
    
    if ($pid && $this->processRunning($pid)) {
      $label = $this->t('Running <small><a href="#" id="behat-ui-kill">(kill)</a></small>');
      $class = 'running';
    }
    elseif ($pid && !$this->processRunning($pid)) {
      $tempstore->delete('behat_ui_pid');
    }
    $form['behat_ui_status'] = [
      '#type' => 'markup',
      '#markup' => '<p id="behat-ui-status" class="' . $class . '">' . $this->t('Status:') . ' <span>' . $label . '</span></p>',
    ];
    
    $output = '';

    if ($behat_ui_http_auth_headless_only && $behat_ui_html_report_dir) {
      $output = file_get_contents($behat_ui_html_report_dir . '/' . $behat_ui_html_report_file);
    }
    elseif ($outfile && file_exists($outfile)) {
      $output = nl2br(htmlentities(file_get_contents($outfile)));
    }
    $form['behat_ui_output'] = [
      '#title' => $this->t('Tests output'),
      '#type' => 'markup',
      '#markup' => '<div id="behat-ui-output">' . $output . '</div>',
    ];


    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    
    $config = \Drupal::config('behat_ui.settings');
    $behat_ui_behat_bin_path = $config->get('behat_ui_behat_bin_path');
    $behat_ui_behat_config_path = $config->get('behat_ui_behat_config_path');
    
    $behat_ui_html_report_dir = $config->get('behat_ui_html_report_dir');
    $behat_ui_html_report_file = $config->get('behat_ui_html_report_file');
    
    $behat_ui_log_report_dir = $config->get('behat_ui_log_report_dir');
    $behat_ui_log_report_file = $config->get('behat_ui_log_report_file');
    
    $behat_ui_http_auth_headless_only = $config->get('behat_ui_http_auth_headless_only');

    $pid = $config->get('behat_ui_pidfile');
    $outfile = $config->get('behat_ui_outfile');
    

    $message = \Drupal::messenger();

    if (!$pid) {
      $config = \Drupal::config('behat_ui.settings');

      $behat_config_path = "-c " . $config->get('behat_config_path');

      $filePath = $behat_ui_log_report_dir . '/' . $behat_ui_log_report_file;
      if (!\Drupal::service('file_system')->prepareDirectory($filePath, FileSystemInterface::CREATE_DIRECTORY)) {
        $message->addError(t('Output directory does not exists or is not writable.'));
      }

      $outfile = $behat_ui_log_report_dir . '/' . $behat_ui_log_report_file;
      $report_dir = $filePath;


      $command = "$behat_ui_behat_bin_path $behat_config_path -f pretty --out std > $outfile&";
      if ($behat_ui_http_auth_headless_only) {
        $command = "$behat_ui_behat_bin_path $behat_config_path --format pretty --out std --format html --out > $outfile &";
      }
      $process = new Process($command);
      $process->enableOutput();
      $process->start();
      $message->addMessage($process->getExitCodeText());
    }
    else {
      $message->addMessage($this->t('Tests are already running.'));
    }
  }

  /**
   * Helper function to check if a process with given PID is running or not.
   *
   * @param $pid
   *
   * @return bool
   */
  function processRunning($pid) {
    $isRunning = FALSE;
    if (posix_kill(intval($pid), 0)) {
      $isRunning = TRUE;
    }
    return $isRunning;
  }

  /**
   * Load Behat Config.
   *
   * Adding support for the Symfony yaml parser so everything can be setup
   * through Composer.
   *
   * @return array
   *   Behat config.
   */
  function loadBehatConfig() {

    $behat_config = array();

    $config = \Drupal::config('behat_ui.settings');

    $behat_ui_behat_config_path = $config->get('behat_ui_behat_config_path');
    $behat_ui_behat_bin_path = $config->get('behat_ui_behat_bin_path');
    $behat_ui_autoload_path = $config->get('behat_ui_autoload_path');
    $behat_ui_behat_config_file = $config->get('behat_ui_behat_config_file');


    try {
      if (is_file($autoload = $behat_ui_autoload_path)) {
        require $autoload;
      }
      else {

        $error_message = $this->t('You must set up the project dependencies, run the following commands:') . PHP_EOL .
            'curl -s http://getcomposer.org/installer | php' . PHP_EOL .
            'php composer.phar install' . PHP_EOL;

        \Drupal::messenger()->addError($error_message);
        \Drupal::logger('behat_ui')->notice($error_message, []);
      }

      $behat_config_factory = new ConfigurationLoader();
      $behat_config_factory->setConfigurationFilePath($behat_ui_behat_config_path . '/' . $behat_ui_behat_config_file);
      $behat_config = $behat_config_factory->loadConfiguration();

    }
    catch (ParseException $e) {
      \Drupal::messenger()->addError(t('Extension yaml is not loaded. Could not parse behat.yml file.'));
      $watchdog_message = $this->t('Could not parse Behat config file, check Composer libraries, file permissions and Behat config: Error = @error', array('@error' => $e));
      \Drupal::logger('behat_ui')->notice($watchdog_message, []);
    }
    return ($behat_config);
  }

}
