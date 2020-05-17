/**
 * @file
 * Behaviors Behat UI run tests scripts.
 */

(function ($, _, Drupal, drupalSettings) {
  "use strict";
  
  Drupal.behaviors.BehatUiRunTests = {
    attach: function (context, settings) {
      

      var killProcess = function () {
        $('#behat-ui-kill', context).click(function() {
          $.ajax({
            url: drupalSettings.path.baseUrl + 'behat-ui/kill?' + parseInt(Math.random() * 1000000000, 10),
            dataType: 'json',
            success: function (data) {
              if (data.response) {
                console.log(Drupal.t('Process killed'));
                checkStatus();
              }
              else {
                console.log(Drupal.t('Could not kill process'));
              }
            },
            error: function (xhr, textStatus, error) {
              console.log(Drupal.t('An error happened on trying to kill the process.'));
            }
          });
          return false;
        });
      };

      var checkStatus = function () {
        var behat_ui_status = $('#behat-ui-status', context);
        var behat_ui_output = $('#behat-ui-output', context);

        $.ajax({
          url: drupalSettings.path.baseUrl + 'behat-ui/status?' + parseInt(Math.random() * 1000000000, 10),
          dataType: 'json',
          success: function (data) {

            behat_ui_status.removeClass('running');

            if (data.running) {
              behat_ui_status.addClass('running');
              behat_ui_status.find('span').html(Drupal.t('Process:') + data.pid + ' ' + Drupal.t('Running <small><a href="#" id="behat-ui-kill">(kill)</a></small>'));
              killProcess();
              setTimeout(checkStatus, 10000);
            }
            else {
              behat_ui_status.find('span').html(Drupal.t('Not running'));
            }
            
            behat_ui_output.html(data.output);

          },
          error: function (xhr, textStatus, error) {
            console.log(Drupal.t('An error happened on checking tests status.'));
            setTimeout(checkStatus, 10000);
          }
        });
      };

      checkStatus();
      killProcess();
    }
  };
})(window.jQuery, window._, window.Drupal, window.drupalSettings);
