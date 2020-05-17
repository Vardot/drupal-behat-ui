<?php

namespace Drupal\behat_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Url;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\Process\Process;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Behat UI Run Tests class.
 */
class BehatUiRunTests extends FormBase {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The tempstore object.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStore;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * Constructs a BehatUiNew object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Symfony\Component\HttpFoundation\Request $current_request
   *   The current request.
   */
  public function __construct(ConfigFactory $config_factory, MessengerInterface $messenger, FileSystemInterface $file_system, PrivateTempStoreFactory $temp_store_factory, Request $current_request) {
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
    $this->fileSystem = $file_system;
    $this->tempStore = $temp_store_factory;
    $this->currentRequest = $current_request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('messenger'),
      $container->get('file_system'),
      $container->get('tempstore.private'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

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

    $config = $this->configFactory->getEditable('behat_ui.settings');

    $behat_ui_html_report = $config->get('behat_ui_html_report');
    $behat_ui_html_report_dir = $config->get('behat_ui_html_report_dir');
    $behat_ui_log_report_dir = $config->get('behat_ui_log_report_dir');

    $beaht_ui_tempstore_collection = $this->tempStore->get('behat_ui');
    $pid = $beaht_ui_tempstore_collection->get('behat_ui_pid');

    $label = $this->t('Not running');
    $class = '';

    if ($pid && posix_kill(intval($pid), 0)) {
      $label = $this->t("Process:") . $pid . ' ' . $this->t('Running <small><a href="#" id="behat-ui-kill">(kill)</a></small>');
      $class = 'running';
    }

    $form['submit_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run behat tests'),
    ];

    $form['behat_ui_status'] = [
      '#type' => 'markup',
      '#markup' => '<p id="behat-ui-status" class="' . $class . '">' . $this->t('Status:') . ' <span>' . $label . '</span></p>',
    ];

    if ($behat_ui_html_report) {

      if (isset($behat_ui_html_report_dir) && $behat_ui_html_report_dir != '') {

        $html_report_output = $behat_ui_html_report_dir . '/index.html';
        if ($html_report_output && file_exists($html_report_output)) {

          $report_url = new Url('behat_ui.report');
          $form['behat_ui_output'] = [
            '#title' => $this->t('Tests output'),
            '#type' => 'markup',
            '#markup' => '<div id="behat-ui-output"><iframe id="behat-ui-output-iframe" src="' . $this->currentRequest->getSchemeAndHttpHost() . $report_url->toString() . '" width="100%" height="100%"></iframe></div>',
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
        $this->messenger->addError($this->t('The HTML report directory is not configured.'));
      }
    }
    else {

      if (isset($behat_ui_log_report_dir) && $behat_ui_log_report_dir != '') {

        $log_report_output = $behat_ui_log_report_dir . '/bethat-ui-test.log';
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
        $this->messenger->addError($this->t('The Console Log report directory is not configured.'));
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Validate Form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $config = $this->configFactory->getEditable('behat_ui.settings');
    $behat_ui_behat_bin_path = $config->get('behat_ui_behat_bin_path');
    $behat_ui_behat_config_path = $config->get('behat_ui_behat_config_path');
    $behat_ui_behat_config_file = $config->get('behat_ui_behat_config_file');

    $behat_ui_behat_features_path = $config->get('behat_ui_behat_features_path');

    $behat_ui_html_report = $config->get('behat_ui_html_report');
    $behat_ui_html_report_dir = $config->get('behat_ui_html_report_dir');
    $behat_ui_log_report_dir = $config->get('behat_ui_log_report_dir');

    $beaht_ui_tempstore_collection = $this->tempStore->get('behat_ui');
    $pid = $beaht_ui_tempstore_collection->get('behat_ui_pid');

    $command = '';

    if ($pid && posix_kill(intval($pid), 0)) {
      $form_state->setErrorByName('submit_button', $this->t('Tests are already running!'));
    }
    else {

      $command = '';
      if ($behat_ui_html_report) {

        if (isset($behat_ui_html_report_dir) && $behat_ui_html_report_dir != '') {
          if ($this->fileSystem->prepareDirectory($behat_ui_html_report_dir, FileSystemInterface::CREATE_DIRECTORY)) {
            $command = "cd $behat_ui_behat_config_path;$behat_ui_behat_bin_path --config=$behat_ui_behat_config_file $behat_ui_behat_features_path --format pretty --out std --format html --out $behat_ui_html_report_dir";
          }
          else {
            $form_state->setErrorByName('submit_button', $this->t('The HTML Output directory does not exists or is not writable.'));
          }
        }
        else {
          $form_state->setErrorByName('submit_button', $this->t('HTML report directory and file is not configured.'));
        }
      }
      else {

        if (isset($behat_ui_log_report_dir) && $behat_ui_log_report_dir != '') {

          if ($this->fileSystem->prepareDirectory($behat_ui_log_report_dir, FileSystemInterface::CREATE_DIRECTORY)) {
            $log_report_output_file = $behat_ui_log_report_dir . "/bethat-ui-test.log";
            $command = "cd $behat_ui_behat_config_path;$behat_ui_behat_bin_path --config=$behat_ui_behat_config_file $behat_ui_behat_features_path --format pretty --out std > $log_report_output_file&";
          }
          else {
            $form_state->setErrorByName('submit_button', $this->t('The Log Output directory does not exists or is not writable.'));
          }
        }
        else {
          $form_state->setErrorByName('submit_button', $this->t('The Log directory and file is not configured.'));
        }
      }

      $process = new Process($command);
      $process->enableOutput();

      try {
        $process->start();
        $new_pid = $process->getPid() + 1;
        $this->messenger->addMessage($this->t("Started running tests using prcess ID: @pid", ["@pid" => $new_pid]));
        $beaht_ui_tempstore_collection->set('behat_ui_pid', $new_pid);

        if (!$process->isSuccessful()) {
          $this->messenger->addMessage($process->getErrorOutput());
        }
      }
      catch (ProcessFailedException $exception) {
        $form_state->setErrorByName('submit_button', $exception->getMessage());
      }
    }
  }

}
