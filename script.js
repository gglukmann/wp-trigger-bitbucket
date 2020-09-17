(function ($, window, document, undefined) {
  $(function () {
    $('.wp-trigger-bitbucket-deployments-button').on('click', function (e) {
      e.preventDefault();

      var target = e.target;
      target.disabled = true;

      $.ajax({
        type: 'POST',
        url: wpjd.ajaxurl,
        data: {
          action: 'wp_trigger_bitbucket_deployments_manual_trigger',
          security: wpjd.deployment_button_nonce,
        },
        dataType: 'json',
        success: function () {
          target.disabled = false;
        },
      });
    });
  });
})(jQuery, window, document);
