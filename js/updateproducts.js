$(function() {

    var url = '?plugin=updateproducts&action=upload';
    $('.fileupload').fileupload({
        url: url,
        dataType: 'json',
        done: function(e, data) {

            if (data.result.status == 'ok') {
                $('#response').css('color', 'green');
                $('#response').text(data.result.data.message);
                $('#html').html(data.result.data.html);

            } else if (data.result.status == 'fail') {
                $('#response').css('color', 'red');
                $('#response').text(data.result.errors[0][0]);
            }
        },
        fail: function(e, data) {
            $('#response').css('color', 'red');
            $('#response').html(data.jqXHR.responseText);
        },
        progressall: function(e, data) {
            var progress = parseInt(data.loaded / data.total * 100, 10);
            $('#progress .progress-bar').css(
                    'width',
                    progress + '%'
                    );
        }
    }).prop('disabled', !$.support.fileInput)
            .parent().addClass($.support.fileInput ? undefined : 'disabled').click(function() {
        $('#progress .progress-bar').css('width', '0%');
        $('#response').html('');
        $('#html').html('');
    });

    $('.add-template').click(function() {
        $('.template-name').show();
        $(this).hide();
        $('.с-add-template').show();
        $('.save-template').show();
        return false;
    });
    $('.с-add-template').click(function() {
        $('.template-name').hide();
        $(this).hide();
        $('.add-template').show();
        $('.save-template').hide();
        return false;
    });

    $('.save-template').click(function() {
        if (!$('.template-name').val()) {
            alert('Введите название шаблона');
            return false;
        }
        var exist = false;
        $('select.templates-list option').each(function() {
            if ($('.template-name').val() == $(this).text())
            {
                alert('Такое название шаблона уже существует. Введите другое.');
                exist = true;
                return false;
            }
        });
        if (exist) {
            return false;
        }

        var $form = $(this).closest('form');
        $('.loading-template').show();
        $('.save-template').hide();
        $('.с-add-template').hide();
        $.ajax({
            type: 'POST',
            url: '?plugin=updateproducts&action=save',
            data: $form.serializeArray(),
            dataType: 'json',
            success: function(data, textStatus, jqXHR) {
                $('.loading-template').hide();
                $('.template-buttons').append('<span class="success-template"><i class="icon16 yes"></i>' + data.data.message + '</span>');
                var $option = $('<option>' + $('.template-name').val() + '</option>');
                $option.appendTo('.templates-list')
                $option.attr('selected', 'selected');
                templates[$('.template-name').val()] = data.data.template;
                setTimeout(function() {
                    $('.success-template').hide();
                    $('.с-add-template').click();
                }, 5000);

            },
            error: function(jqXHR, errorText) {
                $('.loading-template').hide();
                $('.template-buttons').append('<span class="error-template"><i class="icon16 no"></i>' + errorText + '<br>' + jqXHR.responseText + '</span>');
                setTimeout(function() {
                    $('.error-template').remove();
                    $('.с-add-template').click();
                }, 5000);
            }
        });
        return false;
    });
    $('.update-template').click(function() {
        $form = $('form#plugins-settings-form');
        $selected = $('.templates-list option:selected')
        template_name = $selected.text();
        $('.loading-template').show();
        $.ajax({
            type: 'POST',
            url: '?plugin=updateproducts&action=update',
            data: $form.serializeArray(),
            dataType: 'json',
            success: function(data, textStatus, jqXHR) {
                $('.loading-template').hide();
                templates[template_name] = data.data.template;
            },
            error: function(jqXHR, errorText) {

            }
        });
        return false;
    });
    
    $('.templates-list').change(function() {
        template_name = $(this).find('option:selected').text();
        template = templates[template_name];
        $('input[type="checkbox"]').attr('checked', false);
        for (var key in template) {
            var val = template[key];
            $('[name="shop_updateproducts[' + key + ']"]').val(val);
            $('[name="shop_updateproducts[' + key + ']"][type="checkbox"]').attr('checked', true);

        }
    });
    $('.delete-template').click(function() {
        $selected = $('.templates-list option:selected')
        template_name = $selected.text();
        $('.loading-template').show();
        $.ajax({
            type: 'POST',
            url: '?plugin=updateproducts&action=delete',
            data: {
                template_name: template_name
            },
            dataType: 'json',
            success: function(data, textStatus, jqXHR) {
                $('.loading-template').hide();
                $selected.remove();
                $('.templates-list').change();
            },
            error: function(jqXHR, errorText) {

            }
        });
        return false;
    });

    $('.templates-list').change();
});