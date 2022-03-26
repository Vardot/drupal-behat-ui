<?php

namespace Drupal\behat_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default Behat Ui controller for the Behat Ui module.
 */
class BehatUiController extends ControllerBase {

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
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The tempstore object.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStore;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a BehatUIController object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Symfony\Component\HttpFoundation\Request $current_request
   *   The current request.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(ConfigFactory $config_factory, MessengerInterface $messenger, Request $current_request, PrivateTempStoreFactory $temp_store_factory, RendererInterface $renderer) {
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
    $this->currentRequest = $current_request;
    $this->tempStore = $temp_store_factory;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('messenger'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('tempstore.private'),
      $container->get('renderer')
    );
  }

  /**
   * Get Behat test status report.
   */
  public function getTestStatusReport() {
    $config = $this->configFactory->getEditable('behat_ui.settings');

    $behat_ui_html_report = $config->get('behat_ui_html_report');
    $behat_ui_html_report_dir = $config->get('behat_ui_html_report_dir');
    $behat_ui_log_report_dir = $config->get('behat_ui_log_report_dir');

    $output = '';
    if ($behat_ui_html_report) {
      if (isset($behat_ui_html_report_dir) && $behat_ui_html_report_dir != '') {

        $html_report = $behat_ui_html_report_dir . '/index.html';

        if ($html_report && file_exists($html_report)) {
          $output = file_get_contents($html_report);
        }
        else {
          $output = $this->t('No HTML test report yet!');
        }
      }

    }
    else {

      if (isset($behat_ui_log_report_dir) && $behat_ui_log_report_dir != '') {

        $log_report = $behat_ui_log_report_dir . '/bethat-ui-test.log';

        if ($log_report && file_exists($log_report)) {
          $output = nl2br(htmlentities(file_get_contents($log_report)));
        }
        else {
          $output = $this->t('No Console log test report yet!');
        }
      }
    }

    $build = [
      '#theme' => 'behat_ui_report',
      '#output' => $output,
      '#name' => "Behat UI report",
      '#cache' => ['max-age' => 0],
    ];

    $build_output = $this->renderer->renderRoot($build);
    $response = new Response();
    $response->setContent($build_output);
    return $response;

  }

  /**
   * Get Behat test status.
   */
  public function getTestStatus() {

    $report_url = new Url('behat_ui.report');
    $output = '<iframe id="behat-ui-output-iframe" src="' . $this->currentRequest->getSchemeAndHttpHost() . $report_url->toString() . '" width="100%" height="100%"></iframe>';

    $beaht_ui_tempstore_collection = $this->tempStore->get('behat_ui');
    $pid = $beaht_ui_tempstore_collection->get('behat_ui_pid');

    if ($pid && posix_kill(intval($pid), 0)) {

      return new JsonResponse([
        'running' => TRUE,
        'pid' => $pid,
        'output' => $output,
      ]);
    }
    else {

      return new JsonResponse([
        'running' => FALSE,
        'pid' => '',
        'output' => $output,
      ]);
    }

  }

  /**
   * Auto complete Step.
   */
  public function autocompleteStep(Request $request) {
    $matches = [];

    $input = $request->query->get('q');

    if (!$input) {
      return new JsonResponse($matches);
    }

    $input = Xss::filter($input);

    $steps = explode('<br />', $this->getAutocompleteDefinitionSteps());
    foreach ($steps as $step) {
      $title = preg_replace('/^\s*(Given|Then|When|And|But) \/\^/', '', $step);
      $title = preg_replace('/\$\/$/', '', $title);
      if (preg_match('/' . preg_quote($input) . '/', $title)) {
        $matches[] = ['value' => $title, 'label' => $title];
      }
    }

    return new JsonResponse($matches);
  }

  /**
   * Kill running test.
   */
  public function kill() {
    $response = FALSE;
    $beaht_ui_tempstore_collection = $this->tempStore->get('behat_ui');
    $pid = $beaht_ui_tempstore_collection->get('behat_ui_pid');

    if ($pid && posix_kill(intval($pid), 0)) {
      try {
        $response = posix_kill($pid, SIGKILL);
        $beaht_ui_tempstore_collection->delete('behat_ui_pid');
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

    $config = $this->configFactory->getEditable('behat_ui.settings');

    if (($format === 'html' || $format === 'txt')) {

      $headers = [
        'Content-Type' => 'text/x-behat',
        'Content-Disposition' => 'attachment; filename="behat_ui_output.' . $format . '"',
      ];

      foreach ($headers as $key => $value) {
        drupal_add_http_header($key, $value);
      }

      if ($format === 'html') {
        $behat_ui_html_report_dir = $config->get('behat_ui_html_report_dir');
        $output = $behat_ui_html_report_dir . '/index.html';
        readfile($output);
      }
      elseif ($format === 'txt') {
        drupal_add_http_header('Connection', 'close');
        $behat_ui_log_report_dir = $config->get('behat_ui_log_report_dir');
        $output = $behat_ui_log_report_dir . '/bethat-ui-test.log';
        $plain = file_get_contents($output);
        echo drupal_html_to_text($plain);
      }
    }
    else {
      $this->messenger->addError($this->t('Output file not found. Please run the tests again in order to generate it.'));
      drupal_goto('behat_ui.run_tests');
    }
  }

  /**
   * Auto complete behat definition steps.
   */
  public function getAutocompleteDefinitionSteps() {

    $config = $this->configFactory->getEditable('behat_ui.settings');
    $behat_bin = $config->get('behat_ui_behat_bin_path');
    $behat_config_path = $config->get('behat_ui_behat_config_path');

    $command = "cd $behat_config_path; $behat_bin -dl | sed 's/^\s*//g'";
    $output = shell_exec($command);
    $output = nl2br(htmlentities($output));

    $output = str_replace('default |', '', $output);
    $output = str_replace('Given', '', $output);
    $output = str_replace('When', '', $output);
    $output = str_replace('Then', '', $output);
    $output = str_replace('And', '', $output);
    $output = str_replace('But', '', $output);
    $output = str_replace('/^', '', $output);

    return $output;
  }

  /**
   * Behat definition steps.
   */
  public function getDefinitionSteps() {

    $config = $this->configFactory->getEditable('behat_ui.settings');
    $behat_bin = $config->get('behat_ui_behat_bin_path');
    $behat_config_path = $config->get('behat_ui_behat_config_path');

    $cmd = "cd $behat_config_path; $behat_bin -dl | sed 's/^\s*//g'";
    $output = shell_exec($cmd);
    $output = nl2br(htmlentities($output));

    $build = [
      '#markup' => $this->formatBehatSteps($output, '<code>', '</code><br /><hr /><br /><code>'),
    ];
    return $build;
  }

  /**
   * Behat definitions steps with extended info.
   */
  public function getDefinitionStepsWithInfo() {

    $config = $this->configFactory->getEditable('behat_ui.settings');
    $behat_bin = $config->get('behat_ui_behat_bin_path');
    $behat_config_path = $config->get('behat_ui_behat_config_path');

    $command = "cd $behat_config_path; $behat_bin -di";
    $output = shell_exec($command);
    $output = nl2br(htmlentities($output));

    $build = [
      '#markup' => $this->formatBehatSteps($output),
    ];
    return $build;
  }

  /**
   * Format Behat Steps.
   */
  public function formatBehatSteps($behatSteps, $formatCodeBeginValue = '<code>', $formatCodeEndBeginValue = '</code><br /><hr /><code>') {

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

  public function getDefinitionStepsJson() {

    $config = $this->configFactory->getEditable('behat_ui.settings');
    $behat_bin = $config->get('behat_ui_behat_bin_path');
    $behat_config_path = $config->get('behat_ui_behat_config_path');

    $cmd = "cd $behat_config_path; $behat_bin -dl | sed 's/^\s*//g'";
    $output = shell_exec($cmd);

    $output = str_replace("default |", "", $output);
    $output = str_replace("/^", "", $output);
    $output = str_replace("$/", "", $output);

    $output = str_replace('Given|', 'Given', $output);
    $output = str_replace('When|', 'When', $output);
    $output = str_replace('Then|', 'Then', $output);
    $output = str_replace('And|', 'And', $output);
    $output = str_replace('But|', 'But', $output);

    $output = str_replace('Given', 'BEHAT_UI_DELIMITERGiven', $output);
    $output = str_replace('When', 'BEHAT_UI_DELIMITERWhen', $output);
    $output = str_replace('Then', 'BEHAT_UI_DELIMITERThen', $output);
    $output = str_replace('And', 'BEHAT_UI_DELIMITERAnd', $output);
    $output = str_replace('But', 'BEHAT_UI_DELIMITERBut', $output);

    $output = str_replace('Given', '', $output);
    $output = str_replace('When', '', $output);
    $output = str_replace('Then', '', $output);
    $output = str_replace('And', '', $output);
    $output = str_replace('But', '', $output);

    $behatList = [];

    $behatList += explode("BEHAT_UI_DELIMITER", $output);
    sort($behatList);

    return new JsonResponse($behatList);
  }

}
