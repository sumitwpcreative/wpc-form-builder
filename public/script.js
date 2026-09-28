jQuery(function($){

    const TOKEN_MAX_AGE_MS = 60 * 60 * 1000;

    function showMessage($form, text, type){
        $form.find('.wpcfb-message')
            .removeClass('wpcfb-message--success wpcfb-message--error')
            .addClass('wpcfb-message--' + type)
            .text(text);
    }

    // Object.fromEntries(FormData) keeps only the last value per name, so ticked checkboxes would be lost.
    // "name[]" inputs become an array under "name" (sent as formData[name][]), everything else a string.
    function collectFormData(form){
        const data = {};
        for ( const [key, value] of new FormData(form) ) {
            if ( key.slice(-2) === '[]' ) {
                const name = key.slice(0, -2);
                (data[name] = data[name] || []).push(value);
            } else {
                data[key] = value;
            }
        }
        return data;
    }

    function showFieldErrors($form, errors){
        $form.find('.wpcfb-field-error').remove();
        $form.find('[aria-invalid]').removeAttr('aria-invalid');

        $.each(errors || {}, function(name, message){
            // Checkbox groups are named "name[]"; radio groups share one name across inputs.
            const $input = $form.find('[name="' + name + '"], [name="' + name + '[]"]');
            $input.attr('aria-invalid', 'true');
            $input.closest('.wpcfb-field').append($('<span class="wpcfb-field-error"></span>').text(message));
        });
    }

    // Nonce + signed "form started" token, fetched when the visitor first engages with the form.
    // Fetched over AJAX because the page itself may be served from cache.
    function getToken($form){
        const cached = $form.data('wpcfbToken');
        if ( cached && Date.now() - cached.receivedAt < TOKEN_MAX_AGE_MS ) {
            return $.Deferred().resolve(cached).promise();
        }
        if ( $form.data('wpcfbTokenRequest') ) {
            return $form.data('wpcfbTokenRequest');
        }

        const request = $.post(wpcfbData.ajax_url, { action: 'wpcfb_get_nonce' }).then(function(res){
            const token = {
                nonce: res.data.nonce,
                started: res.data.started,
                minMs: res.data.min_ms || 0,
                receivedAt: Date.now()
            };
            $form.data('wpcfbToken', token).removeData('wpcfbTokenRequest');
            return token;
        }, function(xhr){
            $form.removeData('wpcfbTokenRequest');
            // Returning a plain value would resolve the chain (jQuery 3), so re-reject.
            return $.Deferred().reject(xhr).promise();
        });

        $form.data('wpcfbTokenRequest', request);
        return request;
    }

    // Wait out the minimum fill time (plus a small buffer for clock differences) so fast humans are not rejected.
    function waitForMinimum(token){
        const remaining = token.minMs + 250 - (Date.now() - token.receivedAt);
        const wait = $.Deferred();
        setTimeout(function(){ wait.resolve(token); }, Math.max(0, remaining));
        return wait.promise();
    }

    function resetTurnstile($form){
        const widget = $form.find('.cf-turnstile')[0];
        if ( widget && window.turnstile ) {
            window.turnstile.reset(widget);
        }
    }

    // Conversion tracking. Fires a DOM event (for custom scripts) and a dataLayer event (for GTM),
    // then redirects if the form has a thank-you page. The redirect waits for GTM (max 1s) so tags are not lost.
    function trackSubmission($form, data){
        const detail = { formId: data.form_id, formTitle: data.form_title };
        $form[0].dispatchEvent(new CustomEvent('wpcfb_submitted', { bubbles: true, detail: detail }));

        let redirected = false;
        const redirect = function(){
            if ( data.redirect && ! redirected ) {
                redirected = true;
                window.location.href = data.redirect;
            }
        };

        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'wpcfb_submitted',
            wpcfb_form_id: data.form_id,
            wpcfb_form_title: data.form_title,
            eventCallback: redirect,
            eventTimeout: 1000
        });

        // eventCallback only runs when GTM is loaded, so always keep a fallback.
        if ( data.redirect ) {
            setTimeout(redirect, 1000);
        }
    }

    $('.wpcfb-form').one('focusin pointerdown', function(){
        getToken($(this));
    });

    $('.wpcfb-form').on('submit', function(e){
        e.preventDefault();

        const $form = $(this);
        const $button = $form.find('button[type="submit"]');

        if ( $button.prop('disabled') ) {
            return;
        }
        $button.prop('disabled', true);
        showFieldErrors($form, {});
        showMessage($form, '', 'success');

        getToken($form)
            .then(waitForMinimum)
            .then(function(token){
                const formProps = collectFormData($form[0]);
                formProps.wpcfb_started = token.started;

                return $.post(wpcfbData.ajax_url, {
                    action : 'wpcfb_submit_form',
                    wpcfb_form_submit_nonce : token.nonce,
                    formData : formProps
                });
            })
            .done(function(res){
                const data = res.data || {};
                showMessage($form, data.message || wpcfbData.messages.success, 'success');
                $form[0].reset();
                trackSubmission($form, data);
            })
            .fail(function(xhr){
                const data = (xhr && xhr.responseJSON && xhr.responseJSON.data) || {};
                // A stale token needs replacing before the next attempt.
                if ( data.code && data.code.indexOf('timing_') === 0 ) {
                    $form.removeData('wpcfbToken');
                }
                showFieldErrors($form, data.errors);
                showMessage($form, data.message || wpcfbData.messages.error, 'error');
            })
            .always(function(){
                resetTurnstile($form);
                $button.prop('disabled', false);
            });
    });
});
