jQuery(function($){
    // Script for Tabs
    $('.wpcfb-tab-link').on('click', function(){
        const activeTab = '#wpcfb-tab-' + $(this).attr('data-tab');

        $('.wpcfb-tab-link').removeClass('active');
        $(this).addClass('active');

        $('.wpcfb-tab-panel').removeClass('active');
        $(activeTab).addClass('active');
    });


    // ---Form Builder---
    const $canvas = $('#wpcfb-canvas');
    if ( ! $canvas.length ) {
        return;
    }

    let fields = [];
    try{
        fields = JSON.parse($('#wpcfb-fields-json').val() || '[]');
    } catch(e){
        fields = [];
    }

    function syncJson(){
        $('#wpcfb-fields-json').val(JSON.stringify(fields));
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

    // Build cards with jQuery setters (not template strings) so labels are never parsed as HTML.
    function renderCanvas(){
        $canvas.empty();
        fields.forEach((f, i) => {
            const $card = $('<li class="wpcfb-field-card"></li>').attr('data-index', i);
            $card.append('<span class="wpcfb-field-handle">☰</span>');
            $card.append($('<input type="text" class="wpcfb-field-label">').val(f.label).attr('data-index', i));
            $card.append($('<span class="wpcfb-field-type"></span>').text('(' + f.type + ')'));
            $card.append(
                $('<label></label>')
                    .append($('<input type="checkbox" class="wpcfb-field-required">').attr('data-index', i).prop('checked', !! f.required))
                    .append(' Required')
            );
            $card.append($('<button type="button" class="wpcfb-remove-field">Remove</button>').attr('data-index', i));
            $canvas.append($card);
        });
        syncJson();
    }

    $('.wpcfb-add-field').on('click', function(){
        const type = $(this).data('type');
        fields.push({
            id: 'f' + Date.now(),
            type: type,
            label: 'New ' + type,
            name: uniqueName(type),
            required: false,
            placeholder: ''
        });
        renderCanvas();
    });

    $canvas.on('click', '.wpcfb-remove-field', function(){
        fields.splice($(this).data('index'), 1);
        renderCanvas();
    });

    $canvas.on('input change', '.wpcfb-field-label', function(){
        fields[$(this).data('index')].label = $(this).val();
        syncJson();
    });

    $canvas.on('change', '.wpcfb-field-required', function(){
        fields[$(this).data('index')].required = $(this).is(':checked');
        syncJson();
    });

    $canvas.sortable({
        handle: '.wpcfb-field-handle',
        update: function(){
            const newOrder = [];
            $canvas.find('.wpcfb-field-card').each(function(){
                newOrder.push(fields[$(this).data('index')]);
            });
            fields = newOrder;
            renderCanvas();
        }
    });
    renderCanvas();
});
