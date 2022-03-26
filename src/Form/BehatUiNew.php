<?php

namespace Drupal\behat_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Behat Ui New Scenarios/Feature class.
 */
class BehatUiNew extends FormBase {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

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
   * Constructs a BehatUiNew object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory service.
   * @param \Symfony\Component\HttpFoundation\Request $current_request
   *   The current request.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(ConfigFactory $config_factory, Request $current_request, MessengerInterface $messenger, FileSystemInterface $file_system) {
    $this->configFactory = $config_factory;
    $this->currentRequest = $current_request;
    $this->messenger = $messenger;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('messenger'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'behat_ui_new_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'behat_ui/style';
    $form['#attached']['library'][] = 'behat_ui/new-test-scripts';

    $config = $this->configFactory->getEditable('behat_ui.settings');

    $behat_ui_editing_mode = $config->get('behat_ui_editing_mode');
    if ($behat_ui_editing_mode == 'guided_entry') {
      $form['behat_ui_new_scenario'] = [
        '#type' => 'markup',
        '#markup' => '<div class="layout-row clearfix">'
        . '  <div class="layout-column layout-column--half">'
        . '    <div id="behat-ui-new-scenario" class="panel">'
        . '      <h3 class="panel__title">' . $this->t('New scenario') . '</h3>'
        . '      <div class="panel__content">',
      ];

      $behat_ui_steps_link = new Url('behat_ui.behat_dl');
      $form['behat_ui_new_scenario']['behat_ui_steps_link'] = [
        '#type' => 'markup',
        '#markup' => '<a class="button use-ajax"
              data-dialog-options="{&quot;width&quot;:500}" 
              data-dialog-renderer="off_canvas" 
              data-dialog-type="dialog"
              href="' . $this->currentRequest->getSchemeAndHttpHost() . $behat_ui_steps_link->toString() . '" >' . $this->t('Check available steps') . '</a>',
      ];

      $behat_ui_steps_link_with_info = new Url('behat_ui.behat_di');
      $form['behat_ui_new_scenario']['behat_ui_steps_link_with_info'] = [
        '#type' => 'markup',
        '#markup' => '<a class="button use-ajax"
              data-dialog-options="{&quot;width&quot;:500}" 
              data-dialog-renderer="off_canvas" 
              data-dialog-type="dialog"
              href="' . $this->currentRequest->getSchemeAndHttpHost() . $behat_ui_steps_link_with_info->toString() . '" >' . $this->t('Full steps with info') . '</a>',
      ];

      $form['behat_ui_new_scenario']['behat_ui_title'] = [
        '#type' => 'textfield',
        '#maxlength' => 512,
        '#title' => $this->t('Title of this scenario'),
        '#required' => TRUE,
      ];

      $form['behat_ui_new_scenario']['behat_ui_steps'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Steps'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
        '#tree' => TRUE,
        '#prefix' => '<div id="behat-ui-new-steps">',
        '#suffix' => '</div>',
      ];
      $storage = $form_state->getValues();
      $stepCount = isset($storage['behat_ui_steps']) ? (count($storage['behat_ui_steps']) + 1) : 1;
      if (isset($storage)) {
        for ($i = 0; $i < $stepCount; $i++) {
          $form['behat_ui_new_scenario']['behat_ui_steps'][$i] = [
            '#type' => 'fieldset',
            '#collapsible' => FALSE,
            '#collapsed' => FALSE,
            '#tree' => TRUE,
          ];

          $form['behat_ui_new_scenario']['behat_ui_steps'][$i]['type'] = [
            '#type' => 'select',
            '#options' => [
              '' => '',
              'Given' => 'Given',
              'When' => 'When',
              'Then' => 'Then',
              'And' => 'And',
              'But' => 'But',
            ],
            '#default_value' => '',
          ];

          $form['behat_ui_new_scenario']['behat_ui_steps'][$i]['step'] = [
            '#type' => 'textfield',
            '#maxlength' => 512,
            '#autocomplete_route_name' => 'behat_ui.autocomplete',
          ];
        }
      }

      $form['behat_ui_new_scenario']['behat_ui_add_step'] = [
        '#type' => 'button',
        '#value' => $this->t('Add'),
        '#href' => '',
        '#ajax' => [
          'callback' => '::ajaxAddStep',
          'wrapper' => 'behat-ui-new-steps',
        ],
      ];

      $form['behat_ui_new_scenario']['behat_ui_javascript'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Needs a real browser'),
        '#default_value' => $config->get('behat_ui_needs_browser'),
        '#description' => $this->t('Check this if this test needs a real browser, which supports JavaScript, in order to perform actions that happen without reloading the page.'),
      ];
    }
    elseif ($behat_ui_editing_mode == 'free_text') {

      $form['behat_ui_new_feature'] = [
        '#type' => 'markup',
        '#markup' => '<div class="layout-row clearfix">'
        . '  <div class="layout-column layout-column--half">'
        . '    <div id="behat-ui-new-scenario" class="panel">'
        . '      <h3 class="panel__title">' . $this->t('New Feature') . '</h3>'
        . '      <div class="panel__content">',
      ];

      $behat_ui_steps_link = new Url('behat_ui.behat_dl');
      $form['behat_ui_new_feature']['behat_ui_steps_link'] = [
        '#type' => 'markup',
        '#markup' => '<a class="button use-ajax"
              data-dialog-options="{&quot;width&quot;:500}" 
              data-dialog-renderer="off_canvas" 
              data-dialog-type="dialog"
              href="' . $this->currentRequest->getSchemeAndHttpHost() . $behat_ui_steps_link->toString() . '" >' . $this->t('Check available steps') . '</a>',
      ];

      $behat_ui_steps_link_with_info = new Url('behat_ui.behat_di');
      $form['behat_ui_new_feature']['behat_ui_steps_link_with_info'] = [
        '#type' => 'markup',
        '#markup' => '<a class="button use-ajax"
              data-dialog-options="{&quot;width&quot;:500}" 
              data-dialog-renderer="off_canvas" 
              data-dialog-type="dialog"
              href="' . $this->currentRequest->getSchemeAndHttpHost() . $behat_ui_steps_link_with_info->toString() . '" >' . $this->t('Full steps with info') . '</a>',
      ];

      $form['behat_ui_new_feature']['free_text'] = [
        '#type' => 'textarea',
        '#rows' => 30,
        '#resizable' => TRUE,
        '#attributes' => [
          'class' => ['free-text-ace-editor'],
        ],
        '#default_value' => $this->getFeature(),
      ];
      $form['behat_ui_new_feature']['free_text_ace_editor'] = [
        '#type' => 'markup',
        '#markup' => '<div id="free_text_ace_editor">' . $this->getFeature() . '</div>',
      ];
      $form['#attached']['library'][] = 'behat_ui/ace-editor';
    }

    // List of features in the selected behat features folder.
    $features_options = $this->getExistingFeatures();
    $features_default_value = 'default';
    if (count($features_options) > 0) {
      if (!isset($features_options['default'])) {
        $features_default_value = array_key_first($features_default_value);
      }
    }
    $form['behat_ui_new_scenario']['behat_ui_feature'] = [
      '#type' => 'radios',
      '#title' => $this->t('Feature'),
      '#options' => $features_options,
      '#default_value' => $features_default_value,
      '#suffix' => '</div></div></div>',
    ];

    $form['behat_ui_scenario_output'] = [
      '#type' => 'markup',
      '#markup' => '<div class="layout-column layout-column--half">
            <div class="panel">
              <h3 class="panel__title">' . $this->t('Scenario output') . '</h3>
              <div id="behat-ui-scenario-output" class="panel__content">',
    ];

    $form['behat_ui_run'] = [
      '#type' => 'button',
      '#value' => $this->t('Run >>'),
      '#ajax' => [
        'callback' => '::runSingleTest',
        'event' => 'click',
        'wrapper' => 'behat-ui-output',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Running the testing feature...'),
        ],
      ],
    ];

    $form['behat_ui_create'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download updated feature'),
      '#attribute' => [
        'id' => 'behat-ui-create',
        'classes' => ['button'],
      ],
    ];

    $form['behat_ui_output'] = [
      '#title' => $this->t('Tests output'),
      '#type' => 'markup',
      '#markup' => '<div id="behat-ui-output"><div id="behat-ui-output-inner"></div></div></div>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggerdElement = $form_state->getTriggeringElement();
    $htmlIdofTriggeredElement = $triggerdElement['#id'];

    $config = $this->configFactory->getEditable('behat_ui.settings');

    $behat_ui_behat_config_path = $config->get('behat_ui_behat_config_path');
    $behat_ui_behat_features_path = $config->get('behat_ui_behat_features_path');
    $behat_ui_editing_mode = $config->get('behat_ui_editing_mode');

    if ($htmlIdofTriggeredElement == 'edit-behat-ui-create') {
      $formValues = $form_state->getValues();

      $file = $behat_ui_behat_config_path . '/' . $behat_ui_behat_features_path . '/' . $formValues['behat_ui_feature'] . '.feature';

      if ($behat_ui_editing_mode == 'guided_entry') {
        $feature = file_get_contents($file);
        $scenario = $this->generateScenario($formValues);
        $content = $feature . "\n" . $scenario;
      }
      elseif ($behat_ui_editing_mode == 'free_text') {
        $content = $formValues['free_text'];
      }

      $handle = fopen($file, 'w+');
      fwrite($handle, $content);
      fclose($handle);

      $file_name = $formValues['behat_ui_feature'] . '.feature';
      $file_size = filesize($file);
      $response = new Response();
      $response->headers->set('Content-Type', 'text/x-behat');
      $response->headers->set('Content-Disposition', 'attachment; filename="' . $file_name . '"');
      $response->headers->set('Pragma', 'no-cache');
      $response->headers->set('Content-Transfer-Encoding', 'binary');
      $response->headers->set('Content-Length', $file_size);
      $form_state->disableRedirect();
      readfile($file);
      return $response->send();

    }
  }

  /**
   * Get existing features.
   */
  public function getExistingFeatures() {

    $config = $this->configFactory->getEditable('behat_ui.settings');

    $behat_ui_behat_config_path = $config->get('behat_ui_behat_config_path');
    $behat_ui_behat_features_path = $config->get('behat_ui_behat_features_path');

    $features = [];

    $features_path = $behat_ui_behat_config_path . '/' . $behat_ui_behat_features_path;
    if ($this->fileSystem->prepareDirectory($features_path, FileSystemInterface::CREATE_DIRECTORY)) {
      if ($handle = opendir($behat_ui_behat_config_path . '/' . $behat_ui_behat_features_path)) {
        while (FALSE !== ($file = readdir($handle))) {
          if (preg_match('/\.feature$/', $file)) {
            $feature = preg_replace('/\.feature$/', '', $file);
            $name = $file;
            $features[$feature] = $name;
          }
        }
      }
    }
    else {
      $this->messenger->addError($this->t('The Features directory does not exists or is not writable.'));
    }

    if (count($features) < 1) {
      $features['default'] = 'default.feature';
    }

    return $features;
  }

  public function getFeature($feature_name = 'default.feature') {
    $config = $this->configFactory->getEditable('behat_ui.settings');

    $behat_ui_behat_config_path = $config->get('behat_ui_behat_config_path');
    $behat_ui_behat_features_path = $config->get('behat_ui_behat_features_path');

    $default_feature_path = $behat_ui_behat_config_path . '/' . $behat_ui_behat_features_path . '/' . $feature_name;

    if (file_exists($default_feature_path)) {
      return file_get_contents($default_feature_path);
    }
    else {
      return '
        Feature: Website requeirment: Website home page.
          As a visitor to the website 
          I want to navigate to the home page
          So that I will be able to see all homepage content

          @javascript @init @check
          Scenario: check the welcome message at the homepage
            Given I am an anonymous user
            When I go to the homepage
            Then I should see "No front page content has been created yet."
      ';
    }

  }

  /**
   * Run a single test.
   */
  public function runSingleTest(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('behat_ui.settings');
    $behat_ui_behat_bin_path = $config->get('behat_ui_behat_bin_path');
    $behat_ui_behat_config_path = $config->get('behat_ui_behat_config_path');
    $behat_ui_behat_config_file = $config->get('behat_ui_behat_config_file');
    $behat_ui_behat_features_path = $config->get('behat_ui_behat_features_path');

    $behat_ui_html_report = $config->get('behat_ui_html_report');
    $behat_ui_html_report_dir = $config->get('behat_ui_html_report_dir');
    $behat_ui_log_report_dir = $config->get('behat_ui_log_report_dir');
    $behat_ui_save_user_testing_features = $config->get('behat_ui_save_user_testing_features');
    $behat_ui_editing_mode = $config->get('behat_ui_editing_mode');

    $formValues = $form_state->getValues();
    // Write to temporary file.
    $file_user_time = 'user-' . date('Y-m-d_h-m-s');
    $file = $behat_ui_behat_config_path . '/' . $behat_ui_behat_features_path . '/' . $file_user_time . '.feature';

    if ($behat_ui_editing_mode == 'guided_entry') {
      $title = $formValues['behat_ui_title'];
      $test = "Feature: $title\n  In order to test \"$title\"\n\n";
      $test .= $this->generateScenario($formValues);
    }
    elseif ($behat_ui_editing_mode == 'free_text') {
      $test = $formValues['free_text'];
    }

    $handle = fopen($file, 'w+');
    fwrite($handle, $test);
    fclose($handle);

    // Run file.
    $test_file = $behat_ui_behat_features_path . '/' . $file_user_time . '.feature';
    $command = '';

    if ($behat_ui_html_report) {

      if (isset($behat_ui_html_report_dir) && $behat_ui_html_report_dir != '') {

        if ($this->fileSystem->prepareDirectory($behat_ui_html_report_dir, FileSystemInterface::CREATE_DIRECTORY)) {
          $command = "cd $behat_ui_behat_config_path;$behat_ui_behat_bin_path  --config=$behat_ui_behat_config_file $test_file --format pretty --out std --format html --out $behat_ui_html_report_dir";
        }
        else {
          $this->messenger->addError($this->t('The HTML Output directory does not exists or is not writable.'));
        }
      }
      else {
        $this->messenger->addError($this->t('HTML report directory and file is not configured.'));
      }

    }
    else {

      if (isset($behat_ui_log_report_dir) && $behat_ui_log_report_dir != '') {

        if ($this->fileSystem->prepareDirectory($behat_ui_log_report_dir, FileSystemInterface::CREATE_DIRECTORY)) {
          $log_report_output_file = $behat_ui_log_report_dir . '/bethat-ui-test.log';
          $command = "cd $behat_ui_behat_config_path;$behat_ui_behat_bin_path --config=$behat_ui_behat_config_file  $test_file --format pretty --out std > $log_report_output_file";
        }
        else {
          $this->messenger->addError($this->t('The Log Output directory does not exists or is not writable.'));
        }
      }
      else {
        $this->messenger->addError($this->t('The Log directory and file is not configured.'));
      }
    }

    $output = shell_exec($command);

    if (isset($output)) {
      $report_html_file_name_and_path = $behat_ui_html_report_dir . '/index.html';

      $report_html_handle = fopen($report_html_file_name_and_path, 'r');
      $report_html = fread($report_html_handle, filesize($report_html_file_name_and_path));
      if (isset($report_html)) {
        fclose($report_html_handle);

        if (!$behat_ui_save_user_testing_features) {
          unlink($file);
        }
      }

    }

    $report_url = new Url('behat_ui.report');

    $form['behat_ui_output'] = [
      '#title' => $this->t('Tests output'),
      '#type' => 'markup',
      '#markup' => Markup::create('<div id="behat-ui-output"><iframe id="behat-ui-output-iframe"  src="' . $this->currentRequest->getSchemeAndHttpHost() . $report_url->toString() . '" width="100%" height="100%"></iframe></div>'),
    ];

    return $form['behat_ui_output'];
  }

  /**
   * Given a form_state, return a Behat scenario.
   */
  public function generateScenario($formValues) {
    $scenario = "";
    if ($formValues['behat_ui_javascript']) {
      $scenario .= " @javascript";
    }
    $title = $formValues['behat_ui_title'];
    $scenario .= "\nScenario: $title\n";

    $steps_count = count($formValues['behat_ui_steps']);

    for ($i = 0; $i < $steps_count; $i++) {
      $type = $formValues['behat_ui_steps'][$i]['type'];
      $step = $formValues['behat_ui_steps'][$i]['step'];

      if (!empty($type) && !empty($step)) {
        $step = preg_replace('/\n\|/', "\n  |", preg_replace('/([:\|])\|/', "$1\n|", $step));
        $scenario .= "  $type $step\n";
      }
    }

    return $scenario;
  }

  /**
   * Behat Ui add step AJAX.
   */
  public function ajaxAddStep($form, $form_state) {
    return $form['behat_ui_new_scenario']['behat_ui_steps'];
  }

}
