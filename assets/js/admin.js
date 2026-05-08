/*
 * CaptchaLa admin JS — wires the "Test connection" button to the
 * captchala_test_connection AJAX endpoint. Pure ES5 + jQuery to stay
 * compatible with old admin shells.
 */
(function ($) {
    'use strict';

    if (typeof window.CaptchalaAdmin !== 'object') { return; }

    var i18n = window.CaptchalaAdmin.i18n || {};
    var $btn = $('#captchala-test-connection');
    var $out = $('#captchala-test-result');

    if (!$btn.length) { return; }

    $btn.on('click', function () {
        var appKey    = $('#captchala_app_key').val();
        var appSecret = $('#captchala_app_secret').val();

        if (!appKey || !appSecret) {
            $out.removeClass('is-ok is-error').addClass('is-error').text(i18n.connFail || 'Failed.');
            return;
        }

        var originalText = $btn.text();
        $btn.prop('disabled', true).text(i18n.testing || 'Testing…');
        $out.removeClass('is-ok is-error').text('');

        $.post(window.CaptchalaAdmin.ajaxUrl, {
            action: window.CaptchalaAdmin.testAction,
            nonce:  window.CaptchalaAdmin.nonce,
            app_key:    appKey,
            app_secret: appSecret
        })
            .done(function (resp) {
                if (resp && resp.success) {
                    $out.removeClass('is-error').addClass('is-ok').text(
                        (resp.data && resp.data.message) || (i18n.connOk || 'OK.')
                    );
                } else {
                    var msg = (resp && resp.data && resp.data.message) || (i18n.connFail || 'Failed.');
                    $out.removeClass('is-ok').addClass('is-error').text(msg);
                }
            })
            .fail(function (xhr) {
                var msg = i18n.connFail || 'Failed.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    msg = xhr.responseJSON.data.message;
                }
                $out.removeClass('is-ok').addClass('is-error').text(msg);
            })
            .always(function () {
                $btn.prop('disabled', false).text(originalText || i18n.testButton || 'Test connection');
            });
    });
})(jQuery);
