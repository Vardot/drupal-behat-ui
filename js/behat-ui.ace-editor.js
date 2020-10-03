/**
 * @file
 * Behat UI Ace Editor.
 */

(function ($, Drupal) {
  'use strict';
  Drupal.behaviors.behatUiAceEditor = {
    attach: function () {
      if (typeof ace == 'undefined' || typeof ace.edit != 'function') {
        return;
      }

      var editor = ace.edit('free_text_ace_editor');
      editor.getSession().setMode('ace/mode/gherkin');
      editor.getSession().setTabSize(2);

      editor.setOption("autoScrollEditorIntoView", true);
      editor.setOption("mergeUndoDeltas", "always");
      editor.setOption("enableBasicAutocompletion", true);

      editor.getSession().on('change', function () {
        $('.free-text-ace-editor').val(editor.getSession().getValue());
      });

      editor.getSession().setValue($('.free-text-ace-editor').val());

      // When the form fails to validate because the text area is required,
      // shift the focus to the editor.
      $('.free-text-ace-editor').on('focus', function () {
        editor.getSession().textInput.focus()
      })
    }
  };
})(jQuery, Drupal);
