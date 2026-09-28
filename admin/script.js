jQuery(function($){
    const config = window.wpcfbAdmin || { widths: [], i18n: {} };
    const t = config.i18n;

    // Script for Tabs
    $('.wpcfb-tab-link').on('click', function(){
        const tab = $(this).attr('data-tab');

        $('.wpcfb-tab-link').removeClass('active');
        $(this).addClass('active');

        $('.wpcfb-tab-panel').removeClass('active');
        $('#wpcfb-tab-' + tab).addClass('active');

        if ( 'preview' === tab ) {
            loadPreview();
        }
    });


    // ---Form Builder---
    const $canvas = $('#wpcfb-canvas');
    const $settings = $('#wpcfb-field-settings');
    if ( ! $canvas.length ) {
        return;
    }

    let fields = [];
    try{
        fields = JSON.parse($('#wpcfb-fields-json').val() || '[]');
    } catch(e){
        fields = [];
    }
    // Fields saved before widths existed are full width.
    fields.forEach(f => { f.width = f.width || '100'; });

    let selectedId = null;

    function syncJson(){
        $('#wpcfb-fields-json').val(JSON.stringify(fields));
    }

    function findField(id){
        return fields.find(f => f.id === id);
    }

    // What a field type offers in the builder (placeholder, options), as declared by its PHP registration.
    function supports(type, feature){
        const def = (config.types || {})[type];
        return !! def && (def.supports || []).includes(feature);
    }

    function typeLabel(type){
        const def = (config.types || {})[type];
        return def ? def.label : type;
    }

    function widthLabel(value){
        const match = config.widths.find(w => w.value === String(value));
        return match ? match.label : value;
    }

    // Field names are submission keys, so they must never collide.
    function uniqueName(type){
        const names = fields.map(f => f.name);
        let i = 1;
        while ( names.includes(type + '_' + i) ) {
            i++;
        }
        return type + '_' + i;
    }

    // Same rules as sanitize_key() on the server: lowercase letters, numbers, underscores.
    function normaliseName(value){
        return String(value).toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '');
    }

    // Build cards with jQuery setters (not template strings) so labels are never parsed as HTML.
    function renderCanvas(){
        $canvas.empty();

        if ( ! fields.length ) {
            $canvas.append($('<li class="wpcfb-canvas-empty"></li>').text(t.emptyCanvas));
        }

        fields.forEach(f => {
            const $card = $('<li class="wpcfb-field-card" tabindex="0" role="button"></li>')
                .addClass('wpcfb-w-' + f.width)
                .toggleClass('is-selected', f.id === selectedId)
                .attr('data-id', f.id)
                .attr('aria-pressed', f.id === selectedId ? 'true' : 'false');

            $card.append($('<span class="wpcfb-field-handle" aria-hidden="true">☰</span>').attr('title', t.drag));

            const $text = $('<span class="wpcfb-field-text"></span>');
            $text.append($('<span class="wpcfb-field-label"></span>').text(f.label || f.name));
            if ( f.required ) {
                $text.append($('<span class="wpcfb-field-required" aria-label="required">*</span>'));
            }
            let meta = typeLabel(f.type) + ' · ' + f.name;
            if ( supports(f.type, 'options') ) {
                const count = (f.options || []).length;
                meta += ' · ' + count + ' ' + (count === 1 ? 'option' : 'options');
                $card.toggleClass('has-warning', ! count);
            }
            $text.append($('<span class="wpcfb-field-meta"></span>').text(meta));
            $card.append($text);

            $card.append($('<span class="wpcfb-field-width"></span>').text(widthLabel(f.width)));
            $card.append(
                $('<button type="button" class="wpcfb-remove-field">&times;</button>')
                    .attr('aria-label', t.remove + ': ' + (f.label || f.name))
                    .attr('title', t.remove)
            );
            $canvas.append($card);
        });
        syncJson();
    }

    function renderSettings(){
        $settings.empty();
        const f = findField(selectedId);

        $settings.append($('<h3></h3>').text(t.fieldSettings));
        if ( ! f ) {
            $settings.append($('<p class="description"></p>').text(t.selectField));
            return;
        }

        const row = (labelText, $input, help) => {
            const id = 'wpcfb-fs-' + $input.attr('data-prop');
            const $row = $('<div class="wpcfb-fs-row"></div>');
            $row.append($('<label></label>').attr('for', id).text(labelText));
            $row.append($input.attr('id', id));
            if ( help ) {
                $row.append($('<p class="description"></p>').text(help));
            }
            return $row;
        };

        $settings.append(row(t.label, $('<input type="text" class="widefat" data-prop="label">').val(f.label)));

        const $name = $('<input type="text" class="widefat code" data-prop="name" spellcheck="false">').val(f.name);
        const $nameRow = row(t.name, $name, t.nameHelp);
        $nameRow.append('<p class="wpcfb-fs-error" role="alert"></p>');
        $settings.append($nameRow);

        if ( supports(f.type, 'placeholder') ) {
            // For a dropdown, the placeholder is the empty first option.
            const isSelect = supports(f.type, 'options');
            $settings.append(row(
                isSelect ? t.selectPrompt : t.placeholder,
                $('<input type="text" class="widefat" data-prop="placeholder">').val(f.placeholder || ''),
                isSelect ? t.selectPromptHelp : ''
            ));
        }

        if ( supports(f.type, 'options') ) {
            const $options = $('<textarea class="widefat" rows="5" data-prop="options"></textarea>').val((f.options || []).join('\n'));
            const $optionsRow = row(t.options, $options, t.optionsHelp);
            $optionsRow.append($('<p class="wpcfb-fs-error" role="alert"></p>').text((f.options || []).length ? '' : t.optionsEmpty));
            $settings.append($optionsRow);
        }

        const $width = $('<select class="widefat" data-prop="width"></select>');
        config.widths.forEach(w => $width.append($('<option></option>').val(w.value).text(w.label)));
        $width.val(f.width);
        $settings.append(row(t.width, $width, t.widthHelp));

        const $required = $('<label class="wpcfb-fs-check"></label>')
            .append($('<input type="checkbox" data-prop="required">').prop('checked', !! f.required))
            .append(document.createTextNode(' ' + t.required));
        const $requiredRow = $('<div class="wpcfb-fs-row"></div>').append($required);
        if ( 'checkbox' === f.type ) {
            $requiredRow.append($('<p class="description"></p>').text(t.checkboxRequiredHelp));
        }
        $settings.append($requiredRow);

        $settings.append($('<button type="button" class="button-link wpcfb-fs-remove"></button>').text(t.remove));
    }

    function select(id){
        selectedId = id;
        renderCanvas();
        renderSettings();
    }

    function removeField(id){
        fields = fields.filter(f => f.id !== id);
        if ( selectedId === id ) {
            selectedId = null;
        }
        renderCanvas();
        renderSettings();
    }

    $('.wpcfb-add-field').on('click', function(){
        const type = $(this).data('type');
        const field = {
            id: 'f' + Date.now(),
            type: type,
            label: (t.newField || 'New %s').replace('%s', typeLabel(type)),
            name: uniqueName(type),
            required: false,
            placeholder: '',
            width: '100'
        };
        if ( supports(type, 'options') ) {
            field.options = (t.defaultOptions || ['Option 1', 'Option 2']).slice();
        }
        fields.push(field);
        select(field.id);
        $settings.find('[data-prop="label"]').trigger('focus').trigger('select');
    });

    // Select a card by click or keyboard (Enter/Space), ignoring the remove button and drag handle.
    $canvas.on('click', '.wpcfb-field-card', function(e){
        if ( $(e.target).closest('.wpcfb-remove-field, .wpcfb-field-handle').length ) {
            return;
        }
        select($(this).attr('data-id'));
    });
    $canvas.on('keydown', '.wpcfb-field-card', function(e){
        if ( e.target === this && ( 'Enter' === e.key || ' ' === e.key ) ) {
            e.preventDefault();
            select($(this).attr('data-id'));
        }
    });

    $canvas.on('click', '.wpcfb-remove-field', function(){
        removeField($(this).closest('.wpcfb-field-card').attr('data-id'));
    });

    $settings.on('click', '.wpcfb-fs-remove', function(){
        removeField(selectedId);
    });

    // Label, placeholder: update as you type. The settings panel is not re-rendered, so focus stays put.
    $settings.on('input', '[data-prop="label"], [data-prop="placeholder"]', function(){
        const f = findField(selectedId);
        if ( ! f ) {
            return;
        }
        f[$(this).attr('data-prop')] = $(this).val();
        renderCanvas();
    });

    // Options: one per line, trimmed, blanks and duplicates dropped (the server applies the same rules).
    $settings.on('input', '[data-prop="options"]', function(){
        const f = findField(selectedId);
        if ( ! f ) {
            return;
        }
        f.options = $(this).val().split('\n').map(o => o.trim()).filter((o, i, all) => o && all.indexOf(o) === i);
        $(this).closest('.wpcfb-fs-row').find('.wpcfb-fs-error').text(f.options.length ? '' : t.optionsEmpty);
        renderCanvas();
    });

    $settings.on('change', '[data-prop="width"]', function(){
        const f = findField(selectedId);
        if ( f ) {
            f.width = $(this).val();
            renderCanvas();
        }
    });

    $settings.on('change', '[data-prop="required"]', function(){
        const f = findField(selectedId);
        if ( f ) {
            f.required = $(this).is(':checked');
            renderCanvas();
        }
    });

    // Name: validated on change (blur/Enter), since normalising while typing would move the cursor.
    $settings.on('change', '[data-prop="name"]', function(){
        const f = findField(selectedId);
        const $error = $settings.find('.wpcfb-fs-error').text('');
        if ( ! f ) {
            return;
        }

        const name = normaliseName($(this).val());
        if ( ! name ) {
            $error.text(t.nameEmpty);
            $(this).val(f.name);
            return;
        }
        if ( fields.some(o => o.id !== f.id && o.name === name) ) {
            $error.text(t.nameTaken);
            $(this).val(f.name);
            return;
        }

        f.name = name;
        $(this).val(name);
        renderCanvas();
    });

    // Enter in a settings input would submit the whole post form. Commit the value instead.
    $settings.on('keydown', 'input[type="text"]', function(e){
        if ( 'Enter' === e.key ) {
            e.preventDefault();
            $(this).trigger('change');
        }
    });

    $canvas.sortable({
        items: '.wpcfb-field-card',
        handle: '.wpcfb-field-handle',
        tolerance: 'pointer',
        placeholder: 'wpcfb-sort-placeholder',
        start: function(e, ui){
            // Size the drop placeholder like the dragged card so the row layout does not jump.
            const f = findField(ui.item.attr('data-id'));
            ui.placeholder.addClass('wpcfb-w-' + (f ? f.width : '100')).height(ui.item.outerHeight());
        },
        update: function(){
            const order = $canvas.find('.wpcfb-field-card').map(function(){ return $(this).attr('data-id'); }).get();
            fields = order.map(findField).filter(Boolean);
            renderCanvas();
        }
    });

    // ---Preview---
    function loadPreview(){
        const $preview = $('#wpcfb-preview').empty().append($('<p class="description"></p>').text(t.loading));

        $.post(config.ajax_url, {
            action: 'wpcfb_preview',
            nonce: config.preview_nonce,
            form_id: config.form_id,
            fields: JSON.stringify(fields),
            button_label: $('#wpcfb-button-label').val() || '',
            btn_color: $('#wpcfb-btn-color').val() || '',
            btn_text_color: $('#wpcfb-btn-text-color').val() || ''
        }).done(function(res){
            // Rendered server-side by the same escaped renderers as the live form.
            $preview.html(res && res.success ? res.data.html : '');
            // The preview sits inside the post edit form. Point its inputs at a form that does not exist,
            // so they are never submitted with the post (a field named "post_title" would overwrite it)
            // and an empty "required" preview field cannot block Update. Names stay, so radio groups still work.
            $preview.find('input, select, textarea').attr('form', 'wpcfb-preview-detached').removeAttr('required').attr('tabindex', '-1');
            if ( ! res || ! res.success ) {
                $preview.append($('<p class="description"></p>').text(t.previewError));
            }
        }).fail(function(){
            $preview.empty().append($('<p class="description"></p>').text(t.previewError));
        });
    }

    renderCanvas();
    renderSettings();
});
