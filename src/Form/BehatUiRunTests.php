<?php

namespace Drupal\behat_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\Process\Process;
use Behat\Testwork\ServiceContainer\Configuration\ConfigurationLoader;

/**
 *
 */
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
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'behat_ui/style';
    $form['#attached']['library'][] = 'behat_ui/run-tests-scripts';

    $config = \Drupal::config('behat_ui.settings');
    $behat_ui_behat_bin_path = $config->get('behat_ui_behat_bin_path');
    $behat_ui_behat_config_path = $config->get('behat_ui_behat_config_path');

    $behat_ui_html_report_dir = $config->get('behat_ui_html_report_dir');
    $behat_ui_html_report_file = $config->get('behat_ui_html_report_file');
    
    $behat_ui_log_report_dir = $config->get('behat_ui_log_report_dir');
    $behat_ui_log_report_file = $config->get('behat_ui_log_report_file');

    $behat_ui_html_report = $config->get('behat_ui_html_report');

    $tempstore = \Drupal::service('tempstore.private')->get('behat_ui');
    $pid = $tempstore->get('behat_ui_pid');

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

    if ($behat_ui_html_report) {

      if (isset($behat_ui_html_report_dir) && $behat_ui_html_report_dir != ''
        && isset($behat_ui_html_report_file) && $behat_ui_html_report_file != '') {

        $html_report_output = $behat_ui_html_report_dir . '/' . $behat_ui_html_report_file;
        if ($html_report_output && file_exists($html_report_output)) {
          $form['behat_ui_output'] = [
            '#title' => $this->t('Tests output'),
            '#type' => 'markup',
            '#markup' => '<iframe id="behat-ui-output" src="file://' . $html_report_output .'"></iframe>',
          ];
        }
        else {
          $form['behat_ui_output'] = [
            '#title' => $this->t('Tests output'),
            '#type' => 'markup',
            '#markup' => '<div id="behat-ui-output">' . $this->t('No HTML report yet') . '</div>',
          ];
        }
      }
      else {
        $form['behat_ui_output'] = [
          '#title' => $this->t('Tests output'),
          '#type' => 'markup',
          '#markup' => '<div id="behat-ui-output">' . $this->t('HTML report directory and file are not configured') . '</div>',
        ];
      }
    }
    else {
      
      if (isset($behat_ui_log_report_dir) && $behat_ui_log_report_dir != ''
        && isset($behat_ui_log_report_file) && $behat_ui_log_report_file != '') {
              

        $log_report_output = $behat_ui_log_report_dir . '/' . $behat_ui_log_report_file;
        if ($log_report_output && file_exists($log_report_output)) {
          $log_report_output_content = nl2br(htmlentities(file_get_contents($log_report_output)));
          $form['behat_ui_output'] = [
            '#title' => $this->t('Tests output'),
            '#type' => 'markup',
            '#markup' => '<div id="behat-ui-output">' . $log_report_output_content . '</div>',
          ];
        }
        else {
          $form['behat_ui_output'] = [
            '#title' => $this->t('Tests output'),
            '#type' => 'markup',
            '#markup' => '<div id="behat-ui-output">' . $this->t('No Log report yet') . '</div>',
          ];
        }
      }
      else {
        $form['behat_ui_output'] = [
          '#title' => $this->t('Tests output'),
          '#type' => 'markup',
          '#markup' => '<div id="behat-ui-output">' . $this->t('The Log report directory and file is not configured') . '</div>',
        ];
      }
    }

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
    $behat_ui_behat_config_file = $config->get('behat_ui_behat_config_file');

    $behat_ui_behat_features_path = $config->get('behat_ui_behat_features_path');

    $behat_ui_html_report_dir = $config->get('behat_ui_html_report_dir');
    $behat_ui_html_report_file = $config->get('behat_ui_html_report_file');

    $behat_ui_log_report_dir = $config->get('behat_ui_log_report_dir');
    $behat_ui_log_report_file = $config->get('behat_ui_log_report_file');

    $behat_ui_html_report = $config->get('behat_ui_html_report');

    $tempstore = \Drupal::service('tempstore.private')->get('behat_ui');
    $pid = $tempstore->get('behat_ui_pid');

    $message = \Drupal::messenger();
    $command = '';

    if (!isset($pid)) {
      
      if ($behat_ui_html_report) {

      if (isset($behat_ui_html_report_dir) && $behat_ui_html_report_dir != ''
        && isset($behat_ui_html_report_file) && $behat_ui_html_report_file != '') {
          
          if (\Drupal::service('file_system')->prepareDirectory($behat_ui_html_report_dir, FileSystemInterface::CREATE_DIRECTORY)) {
            $html_report_output_file = $behat_ui_html_report_dir . '/' . $behat_ui_html_report_file;
            $command = "cd $behat_ui_behat_config_path;$behat_ui_behat_bin_path --config=$behat_ui_behat_config_file $behat_ui_behat_features_path --format pretty --format html --out $behat_ui_html_report_dir";
          }
          else {
            $message->addError($this->t('The HTML Output directory does not exists or is not writable.'));
          }
        }
        else {
          $message->addError($this->t('HTML report directory and file is not configured.'));
        }

      }
      else {

      if (isset($behat_ui_log_report_dir) && $behat_ui_log_report_dir != ''
        && isset($behat_ui_log_report_file) && $behat_ui_log_report_file != '') {
          if (\Drupal::service('file_system')->prepareDirectory($behat_ui_log_report_dir, FileSystemInterface::CREATE_DIRECTORY)) {
            $log_report_output_file = $behat_ui_log_report_dir . '/' . $behat_ui_log_report_file;
            $command = "cd $behat_ui_behat_config_path;$behat_ui_behat_bin_path --config=$behat_ui_behat_config_file $behat_ui_behat_features_path --format pretty --out std > $log_report_output_file&";
          }
          else {
            $message->addError($this->t('The Log Output directory does not exists or is not writable.'));
          }
        }
        else {
          $message->addError($this->t('The Log directory and file is not configured.')); 
        }
      }

      $process = new Process($command);
      $process->enableOutput();
      $process->start();
      $message->addMessage($process->getExitCodeText());
      $tempstore->set('behat_ui_pid', 'behat_ui_process_id_running');
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
  public function processRunning($pid) {
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
  public function loadBehatConfig() {

    $behat_config = [];

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
      $watchdog_message = $this->t('Could not parse Behat config file, check Composer libraries, file permissions and Behat config: Error = @error', ['@error' => $e]);
      \Drupal::logger('behat_ui')->notice($watchdog_message, []);
    }
    return ($behat_config);
  }

}
