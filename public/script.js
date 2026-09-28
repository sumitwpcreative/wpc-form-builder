jQuery(function($){

    function showMessage($form, text, type){
        $form.find('.wpcfb-message')
            .removeClass('wpcfb-message--success wpcfb-message--error')
            .addClass('wpcfb-message--' + type)
            .text(text);
    }

    function showFieldErrors($form, errors){
        $form.find('.wpcfb-field-error').remove();
        $form.find('[aria-invalid]').removeAttr('aria-invalid');

        $.each(errors || {}, function(name, message){
            const $input = $form.find('[name="' + name + '"]');
            $input.attr('aria-invalid', 'true');
            $input.closest('.wpcfb-field').append($('<span class="wpcfb-field-error"></span>').text(message));
        });
    }

    $('.wpcfb-form').on('submit', function(e){
        e.preventDefault();

        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const formProps = Object.fromEntries(new FormData(this));

        if ( $button.prop('disabled') ) {
            return;
        }
        $button.prop('disabled', true);
        showFieldErrors($form, {});
        showMessage($form, '', 'success');

        // Fetch a fresh nonce first: the form HTML may come from a page cache.
        $.post(wpcfbData.ajax_url, { action: 'wpcfb_get_nonce' })
            .then(function(res){
                return $.post(wpcfbData.ajax_url, {
                    action : 'wpcfb_submit_form',
                    wpcfb_form_submit_nonce : res.data.nonce,
                    formData : formProps
                });
            })
            .done(function(res){
                showMessage($form, (res.data && res.data.message) || wpcfbData.messages.success, 'success');
                $form[0].reset();
            })
            .fail(function(xhr){
                const data = (xhr.responseJSON && xhr.responseJSON.data) || {};
                showFieldErrors($form, data.errors);
                showMessage($form, data.message || wpcfbData.messages.error, 'error');
            })
            .always(function(){
                $button.prop('disabled', false);
            });
    });
});
