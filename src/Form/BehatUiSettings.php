<?php

namespace Drupal\behat_ui\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Behat UI Settings class.
 */
class BehatUiSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'behat_ui_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['behat_ui.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('behat_ui.settings');

    $form['behat_ui_behat_config_path'] = [
      '#title' => $this->t('Behat configuration directory path'),
      '#description' => $this->t('Directory path for Behat configuration. This is where the <em>behat.yml</em> file lives. Do not include the <em>behat.yml</em> file and no trailing slash at the end.'),
      '#type' => 'textfield',
      '#maxlength' => 512,
      '#default_value' => $config->get('behat_ui_behat_config_path'),
      '#prefix' => '<div class="layout-row clearfix">
          <div class="layout-column layout-column--half">
            <div class="panel">
              <h3 class="panel__title">' . $this->t('Behat General Settings') . '</h3>
              <div class="panel__content">',
    ];

    $form['behat_ui_behat_bin_path'] = [
      '#title' => $this->t('Behat executable command path'),
      '#description' => $this->t('An absolute path, or a relative path based on the Behat configuration directory path above.<br />
        <b>Sometimes you will need to include the php executable. For example:</b><br />
        <ul>
          <li>bin/behat</li>
          <li>php ./bin/behat</li>
          <li>../../../bin/behat</li>
          <li>../../../vendor/behat/behat</li>
          <li>/var/www/html/PROJECT_FOLDER/bin/behat</li>
        </ul>'),
      '#type' => 'textfield',
      '#maxlength' => 512,
      '#default_value' => $config->get('behat_ui_behat_bin_path'),
      '#required' => TRUE,
    ];

    $form['behat_ui_behat_config_file'] = [
      '#title' => $this->t('Behat configuration file name'),
      '#description' => $this->t('The Behat configuration file, in the Behat configuration directory path. Usually <em>behat.yml</em>.<br />
              <b>Examples:</b>
              <ul>
                <li>behat.yml</li>
                <li>behat.devshop.yml</li>
                <li>behat.varbase.yml</li>
                <li>behat.install.yml</li>
                <li>behat.tools.yml</li>
                <li>behat.my-custom-config.yml</li>
              </ul>'),
      '#type' => 'textfield',
      '#maxlength' => 512,
      '#default_value' => $config->get('behat_ui_behat_config_file'),
    ];

    $form['behat_ui_behat_features_path'] = [
      '#title' => $this->t('Behat features directory path'),
      '#description' => $this->t('The directory path that has the Gherkin script files with <em>.feature</em> extension. The path is relative to the Behat configuration directory path. Do not include trailing slash at the end.<br />
              <b>Examples:</b>
              <ul>
                <li>features</li>
                <li>tests/features</li>
                <li>tests/features/commerce</li>
              </ul>'),
      '#type' => 'textfield',
      '#maxlength' => 512,
      '#default_value' => $config->get('behat_ui_behat_features_path'),
    ];

    $form['behat_ui_autoload_path'] = [
      '#title' => $this->t('Autoload path'),
      '#description' => $this->t('The path for the autoload file, relative to the Behat configuration directory path.<br />
        <b>Examples:</b><br />
        <ul>
          <li>../../../vendor/autoload.php</li>
          <li>../../../web/autoload.php</li>
          <li>../../../docroot/autoload.php</li>
        </ul>'),
      '#type' => 'textfield',
      '#maxlength' => 512,
      '#default_value' => $config->get('behat_ui_autoload_path'),
      '#suffix' => '</div></div>',
    ];

    $form['behat_ui_html_report'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable HTML report format'),
      '#default_value' => $config->get('behat_ui_html_report'),
      '#description' => $this->t('Check to enable generating an HTML report for your test results.'),
      '#prefix' => '<div class="panel">
          <h3 class="panel__title">' . $this->t('HTML formatted Report') . '</h3>
          <div class="panel__content">',
    ];

    $form['behat_ui_html_report_dir'] = [
      '#title' => $this->t('HTML report directory'),
      '#description' => $this->t('The full absolute path for the tests/reports. No trailing slash at the end.'),
      '#type' => 'textfield',
      '#maxlength' => 512,
      '#default_value' => $config->get('behat_ui_html_report_dir'),
      '#suffix' => '</div></div>',
    ];

    $form['behat_ui_log_report_dir'] = [
      '#title' => $this->t('Console log report directory'),
      '#description' => $this->t('The full absolute path for the tests/logs. No trailing slash at the end'),
      '#type' => 'textfield',
      '#maxlength' => 512,
      '#default_value' => $config->get('behat_ui_log_report_dir'),
      '#prefix' => '<div class="panel">
          <h3 class="panel__title">' . $this->t('Console Log formatted Report') . '</h3>
          <div class="panel__content">',
      '#suffix' => '</div></div></div>',
    ];

    $editing_mode_default_value = $config->get('behat_ui_editing_mode');
    if (empty($editing_mode_default_value)) {
      $editing_mode_default_value = 'guided_entry';
    }

    $editing_mode_options = [
      'guided_entry' => $this->t('Guided entry'),
      'free_text' => $this->t('Free text'),
    ];

    $form['behat_ui_editing_mode'] = [
      '#type' => 'radios',
      '#options' => $editing_mode_options,
      '#default_value' => $editing_mode_default_value,
      '#prefix' => '<div class="layout-column layout-column--half">
          <div class="panel">
            <h3 class="panel__title">' . $this->t('Editing Mode') . '</h3>
            <div class="panel__content">',
      '#suffix' => '</div></div>',
    ];

    $form['behat_ui_http_user'] = [
      '#title' => $this->t('HTTP authentication user'),
      '#description' => $this->t('Username for the basic authentication for the targeted site.'),
      '#type' => 'textfield',
      '#maxlength' => 512,
      '#default_value' => $config->get('behat_ui_http_user'),
      '#prefix' => '<div class="panel">
            <h3 class="panel__title">' . $this->t('HTTP Authentication') . '</h3>
            <div class="panel__content">',
    ];

    $form['behat_ui_http_password'] = [
      '#title' => $this->t('HTTP authentication password'),
      '#description' => $this->t('Password for the basic authentication for the targeted site.'),
      '#type' => 'password',
      '#default_value' => $config->get('behat_ui_http_password'),
      '#suffix' => '</div></div>',
    ];

    $form['behat_ui_http_auth_headless_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable HTTP authentication only for headless testing.'),
      '#default_value' => $config->get('behat_ui_http_auth_headless_only'),
      '#description' => $this->t('Sometimes testing using Selenium (or other driver that allows JavaScript) does not handle HTTP authentication well, for example when you have some link with some JavaScript behavior attached. On these cases, you may enable this HTTP authentication only for headless testing and find another solution for drivers that allow JavaScript (for example, with Selenium + JavaScript you can use the extension Auto Auth and save the credentials on a Firefox profile).'),
    ];

    $form['behat_ui_needs_browser'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Needs a real browser'),
      '#default_value' => $config->get('behat_ui_needs_browser'),
      '#description' => $this->t('Check this if this test needs a real browser driver using Selenium - which supports JavaScript - in order to perform actions that happen without reloading the page.'),
    ];

    $form['behat_ui_save_user_testing_features'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Save user testing features'),
      '#default_value' => $config->get('behat_ui_save_user_testing_features'),
      '#description' => $this->t('Check if you want to save user testing features in the Behat features path.'),
    ];

    $form['behat_ui_behat_tags'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of available Behat tags to pass to the run tests to limit scenarios.'),
      '#default_value' => $config->get('behat_ui_behat_tags'),
      '#cols' => 60,
      '#rows' => 10,
      '#description' => $this->t('Scenarios are tagged with the Behat tags to limit the selection of scnarios based on needed test or what change in the tested site.<br />
       <b>For Example:</b><br />
        <br /> <b>Actions:</b>
        <ul>
          <li>[ javascript|Selenium + JavaScript ] <b>@javascript</b> = Run scenarios with Selenium + JavaScript needed in the page.</li>
          <li>[ api|Drupal API ] <b>@api</b> = Run scenarios with Drupal API when we only have file access to the site.</li>
        </ul>
        <br /> <b>Environment:</b>
        <ul>
          <li>[ local|Local ] <b>@local</b> = Recommanded to run scenarios only in Local development workstations.</li>
          <li>[ development|Development ] <b>@development</b> = Recommanded to run scenarios only in Development servers.</li>
          <li>[ staging|Staging and testing ] <b>@staging</b> = Recommanded to run scenarios only in Staging and testing servers.</li>
          <li>[ production|Production ] <b>@production</b> = Recommanded to run scenarios only in Production live servers.</li>
        </ul>
        <br /> <b>Other:</b> you may have your behat tags and flags for your custom usage.
        <ul>
          <li>[ frontend|Front-End ] <b>@frontend</b> = Front-End scenarios.</li>
          <li>[ backend|Back-End ] <b>@backend</b> = Back-End scenarios.</li>
          <li>[ admin|Administration ] <b>@admin</b> = Testing scenarios for the administration only.</li>
          <li>[ init|Initialization ] <b>@init</b> = Initialization scenarios before tests.</li>
          <li>[ cleanup|Cleanup ] <b>@cleanup</b> = Cleanup scenarios after tests.</li>
          <li>[ tools|Tools ] <b>@tools</b> = tools scenarios.</li>
        </ul>
       '),
      '#prefix' => '<div class="panel">
          <h3 class="panel__title">' . $this->t('Behat Tags') . '</h3>
          <div class="panel__content">',
      '#suffix' => '</div></div></div></div>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('behat_ui.settings');
    foreach ($form_state->getValues() as $key => $value) {
      if (strpos($key, 'behat_ui') !== FALSE) {
        $config->set($key, $value);
      }
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Validate Form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate Behat UI - behat tags values.
    $behat_ui_behat_tags_values = self::optionsExtractAllowedListTextValues($form_state->getValue('behat_ui_behat_tags'));
    if (!is_array($behat_ui_behat_tags_values)) {
      $form_state->setErrorByName('behat_ui_behat_tags', $this->t('Allowed values list: invalid input.'));
    }
    else {
      // Check that keys are valid for the field type.
      foreach ($behat_ui_behat_tags_values as $key => $value) {
        if (mb_strlen($key) > 255) {
          $form_state->setErrorByName('behat_ui_behat_tags', $this->t('Allowed values list: each key must be a string at most 255 characters long.'));
          break;
        }
      }
    }
  }

  /**
   * Parses a string of 'allowed values' into an array.
   *
   * @param string $string
   *   The list of allowed values in string format described in
   *   optionsExtractAllowedValues().
   *
   * @return arraynull
   *   The array of extracted key/value pairs, or NULL if the string is invalid.
   *
   * @see optionsExtractAllowedListTextValues()
   */
  public function optionsExtractAllowedListTextValues($string) {
    $values = [];

    $list = explode("\n", $string);
    $list = array_map('trim', $list);
    $list = array_filter($list, 'strlen');

    foreach ($list as $text) {
      $value = $key = FALSE;

      // Check for an explicit key.
      $matches = [];
      if (preg_match('/(.*)\|(.*)/', $text, $matches)) {
        // Trim key and value to avoid unwanted spaces issues.
        $key = trim($matches[1]);
        $value = trim($matches[2]);
        $values[$key] = $value;
      }
      else {
        return NULL;
      }
    }

    return $values;
  }

}
