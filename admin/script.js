$ = jQuery;

$(document).ready(function(){
    // Script for Tabs
    $('.wpcfb-tab-link').click(function(){
        $data_tab = $(this).attr('data-tab');
        $active_tab = `#wpcfb-tab-${$data_tab}`;

        $('.wpcfb-tab-link').removeClass('active');
        $(this).addClass('active');

        $('.wpcfb-tab-panel').removeClass('active');
        $($active_tab).addClass('active');
    });


    // ---Form Builder---
    let fields = [];
    try{
        fields = JSON.parse($('#wpcfb-fields-json').val() || '[]');
    } catch(e){
        fields = [];
    }

    function syncJson(){
        $('#wpcfb-fields-json').val(JSON.stringify(fields));
    }

    function renderCanvas(){
        const $canvas = $('#wpcfb-canvas').empty();
        fields.forEach((f, i) => {
            $canvas.append(
                `<li class="wpcfb-field-card" data-index="${i}">
                    <span class="wpcfb-field-handle">☰</span>
                    <input type="text" class="wpcfb-field-label" value="${f.label}" data-index="${i}">
                    <span class="wpcfb-field-type">(${f.type})</span>
                    <label><input type="checkbox" class="wpcfb-field-required" data-index="${i}" ${f.required ? 'checked' : ''}> Required</label>
                    <button type="button" class="wpcfb-remove-field" data-index="${i}">Remove</button>
                </li>`
            );
        });
        syncJson();
    }

    $('.wpcfb-add-field').on('click', function(){
        const type = $(this).data('type');
        fields.push({
            id: 'f' + Date.now(),
            type: type,
            label: 'New ' + type,
            name: type + '_' + fields.length,
            required: false,
            placeholder: ''
        });
        renderCanvas();
    });

    $('#wpcfb-canvas').on('click', '.wpcfb-remove-field', function(){
        fields.splice($(this).data('index'), 1);
        renderCanvas();
    });

    $('#wpcfb-canvas').on('change', '.wpcfb-field-required', function(){
        fields[$(this).data('index')].required = $(this).is(':checked');
        syncJson();
    });

    $('#wpcfb-canvas').sortable({
        handle: '.wpcfb-field-handle',
        update: function(){
            const newOrder = [];
            $('#wpcfb-canvas .wpcfb-field-card').each(function(){
                newOrder.push(fields[$(this).data('index')]);
            });
            fields = newOrder;
            renderCanvas();
        }
    });
    renderCanvas();
});



