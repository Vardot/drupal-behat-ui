<?php

/**
 * @file
 * Contains \Drupal\behat_ui\Form\BehatUiNew.
 */

namespace Drupal\behat_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class BehatUiNew extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'behat_ui_new_form';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {

    $form['#attached']['library'][] = 'behat_ui/modal';
    $form['#attached']['library'][] = 'behat_ui/behat_ui';

    $form['behat_ui_steps'] = [
      '#title' => t('Available steps'),
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#prefix' => '<div id="behat-ui-steps">',
      '#suffix' => '</div>',
    ];

    $form['behat_ui_steps']['behat_ui_steps_content'] = [
      '#type' => 'markup',
      '#markup' => $this->behat_ui_steps(),
    ];

    $form['behat_ui_steps_link'] = [
        '#type' => 'markup',
        //'#markup' => '<p>' . l(t('Check available steps'), '#', ['attributes' => ['id' => 'behat-ui-steps-link']]) . '</p>',
        '#markup' => '<p>' . t('Check available steps') . '</p>',
      ];


    $form['behat_ui_new_scenario'] = [
      '#title' => t('New scenario'),
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#prefix' => '<div id="behat-ui-new-scenario">',
      '#suffix' => '</div>',
    ];

    $form['behat_ui_scenario_output'] = [
      '#title' => t('Scenario output'),
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#prefix' => '<div id="behat-ui-scenario-output">',
      '#suffix' => '</div>',
    ];

    $form['behat_ui_run'] = [
      '#type' => 'submit',
      '#value' => t('Run >>'),
      '#prefix' => '<div id="behat-ui-run">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => '_behat_ui_run_single_test',
        'wrapper' => 'behat-ui-output-inner',
      ],
    ];

    $form['behat_ui_new_scenario']['behat_ui_title'] = [
      '#type' => 'textfield',
      '#title' => 'Title of this scenario',
      '#required' => TRUE,
    ];

    $form['behat_ui_new_scenario']['behat_ui_steps'] = [
      '#type' => 'fieldset',
      '#title' => t('Steps'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#tree' => TRUE,
      '#prefix' => '<div id="behat-ui-new-steps">',
      '#suffix' => '</div>',
    ];

    if ($form->getStorage()) {
      for ($i = 0; $i < $form->getStorage(); $i++) {
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
            'And' => 'And',
            'Given' => 'Given',
            'When' => 'When',
            'Then' => 'Then',
          ],
          '#default_value' => '',
        ];

        $form['behat_ui_new_scenario']['behat_ui_steps'][$i]['step'] = [
          '#type' => 'textfield',
          '#autocomplete_path' => 'behat-ui/autocomplete',
        ];
      }
    }

    $form['behat_ui_new_scenario']['behat_ui_add_step'] = [
      '#type' => 'button',
      '#value' => t('Add'),
      '#href' => '',
      '#ajax' => [
        'callback' => 'behat_ui_ajax_add_step',
        'wrapper' => 'behat-ui-new-steps',
      ],
    ];

    $form['behat_ui_new_scenario']['behat_ui_javascript'] = [
      '#type' => 'checkbox',
      '#title' => t('Needs a real browser'),
      '#default_value' => 0,
      '#description' => t('Check this if this test needs a real browser, which supports JavaScript, in order to perform actions that happen without reloading the page.'),
    ];

    $form['behat_ui_new_scenario']['behat_ui_feature'] = [
      '#type' => 'radios',
      '#title' => t('Feature'),
      '#options' => $this->behat_ui_features(),
      '#required' => TRUE,
    ];

    $form['behat_ui_scenario_output']['behat_ui_output'] = [
      '#title' => t('Tests output'),
      '#type' => 'markup',
      '#markup' => '<div id="behat-ui-output"><div id="behat-ui-output-inner"></div></div>',
    ];

    $form['behat_ui_create'] = [
      '#type' => 'submit',
      '#value' => t('Download updated feature'),
      '#prefix' => '<div id="behat-ui-create">',
      '#suffix' => '</div>',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    global $user, $base_root;

    $behat_bin = _behat_ui_get_behat_bin_path();
    $behat_config = _behat_ui_get_behat_config_path();

    // Write to temporary file.
    $file_user_time = 'user-' . $user->uid . '-' . date('Y-m-d_h-m-s');
    $file = $behat_config . '/features/tmp/' . $file_user_time . '.feature';
    $feature = $form_state['values']['behat_ui_feature'];
    $scenario = "Feature: $feature\n  In order to test \"$feature\"\n\n";
    $scenario .= _behat_ui_generate_scenario($form_state);
    $handle = fopen($file, 'w+');
    fwrite($handle, $test);
    fclose($handle);

    $file_name = $file_user_time . '.feature';
    $file_size = filesize($file);
    // unlink($file);
    // Send file.
    $headers = array(
      'Content-Type' => 'text/x-behat',
      'Content-Disposition' => 'attachment; filename="' . "$feature.feature" . '"',
      'Content-Length' => $file_size,
    );
    file_transfer('temporary://' . $file, $headers);
  }

  /**
   * Get existing features.
   */
  function behat_ui_features() {
    $features_path = 'features';
    $config = \Drupal::config('behat_ui.settings');
    $behat_config_path = $config->get('behat_config_path');

    $features = array();
    if ($handle = opendir($behat_config_path . '/' . $features_path)) {
      while (FALSE !== ($file = readdir($handle))) {
        if (preg_match('/\.feature$/', $file)) {
          $feature = preg_replace('/\.feature$/', '', $file);
          $name = ucfirst(str_replace('_', ' ', $feature));
          $features[$feature] = $name;
        }
      }
    }
    return $features;
  }

  /**
   * Get available steps.
   */
  function behat_ui_steps() {
//    if ($cache = \Drupal::cache()->get('behat_ui_steps')) {
//      return $cache->data;
//    }

    $config = \Drupal::config('behat_ui.settings');

    $behat_bin = $config->get('behat_bin_path');
    $behat_config_path = $config->get('behat_config_path');

    global $base_root;
    $cmd = "cd $behat_config_path; $behat_bin -dl | sed 's/^\s*//g' | sort";
    $output = shell_exec($cmd);
    $output = nl2br(htmlentities($output));
//    \Drupal::cache('cache')->set('behat_ui_steps', $output);
    return $output;
  }

}
