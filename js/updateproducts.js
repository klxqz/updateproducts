$.fn.serializeObject = function()
{
    var o = {};
    var a = this.serializeArray();
    $.each(a, function() {
        if (o[this.name] !== undefined) {
            if (!o[this.name].push) {
                o[this.name] = [o[this.name]];
            }
            o[this.name].push(this.value || '');
        } else {
            o[this.name] = this.value || '';
        }
    });
    return o;
};
(function($) {
    "use strict";
    $.updateproducts = {
        options: {},
        init: function(options) {
            var that = this;
            that.options = options;
            this.initFileUpload();
            this.initMain();
            this.initEvents();
        },
        initEvents: function() {
            this.addTemplate();
            this.сAddTemplate();
            this.saveTemplate();
            this.updateTemplate();
            this.deleteTemplate();
            this.templatesListChange();
            $('#show-column-feature').click(function() {
                $('.column-feature').show();
                $('#show-column-feature-row').hide();
                $('#hide-column-feature-row').show();
                return false;
            });
            $('#hide-column-feature').click(function() {
                $('.column-feature').hide();
                $('#hide-column-feature-row').hide();
                $('#show-column-feature-row').show();
                return false;
            });

        },
        initMain: function() {
            $('#plugins-settings-form').submit(function() {
                var form = $(this);
                var url = form.attr('action');
                var processId;

                $('#s-regenerate-progressbar').show();
                form.find('.progressbar .progressbar-inner').css('width', '0%');
                form.find('.progressbar-description').text('0.000%');
                form.find('.progressbar').show();
                $("#s-regenerate-report").hide();

                var cleanup = function() {
                    $.post(url, {processId: processId, cleanup: 1}, function(r) {
                        $('#s-regenerate-progressbar').hide();
                        $("#s-regenerate-report").show();
                        if (r.report) {
                            $("#s-regenerate-report").html(r.report);
                            $("#s-regenerate-report").find('.close').click(function() {
                            });
                        }
                    }, 'json');
                };

                var step = function(delay) {
                    delay = delay || 2000;
                    var timer_id = setTimeout(function() {
                        $.post(url, $.extend({processId: processId}, form.serializeObject()),
                                function(r) {
                                    if (!r) {
                                        step(3000);
                                    } else if (r && r.ready) {
                                        form.find('.progressbar .progressbar-inner').css({
                                            width: '100%'
                                        });
                                        form.find('.progressbar-description').text('100%');
                                        cleanup();
                                    } else if (r && r.error) {
                                        form.find('.errormsg').text(r.error);
                                    } else {
                                        if (r && r.progress) {
                                            var progress = parseFloat(r.progress.replace(/,/, '.'));
                                            form.find('.progressbar .progressbar-inner').animate({
                                                'width': progress + '%'
                                            });
                                            form.find('.progressbar-description').text(r.progress + ' - ' + r.step);
                                        }
                                        if (r && r.warning) {
                                            form.find('.progressbar-description').append('<i class="icon16 exclamation"></i><p>' + r.warning + '</p>');
                                        }
                                        step();
                                    }
                                },
                                'json').error(function() {
                            step(3000);
                        });
                    }, delay);
                };

                $.post(url, form.serializeArray(),
                        function(r) {
                            if (r && r.processId) {
                                processId = r.processId;
                                step(1000);   // invoke Runner
                                step();         // invoke Messenger
                            } else if (r && r.error) {
                                form.find('.errormsg').text(r.error);
                            } else {
                                form.find('.errormsg').text('Server error');
                            }
                        },
                        'json').error(function() {
                    form.find('errormsg').text('Server error');
                });

                return false;
            });

            $('.upload_type').change(function() {
                $('.upload-field').hide();
                if ($(this).val() == 'url') {
                    $('#upload-url').show();
                } else {
                    $('#upload-file').show();
                }
            });
            $('.upload_type:first').click();
        },
        initFileUpload: function() {
            var self = this;
            var form = $(this).closest('#plugins-settings-form');
            $('.fileupload').fileupload({
                url: '?plugin=updateproducts&action=uploadFile',
                dataType: 'json',
                done: function(e, data) {
                    console.log(data);
                    $('.loading').remove();
                    if (data.result.status == 'ok') {
                        $('#response-upload-file').html(data.result.data.html);
                    } else if (data.result.status == 'fail') {
                        self.showErrors('#response-upload-file', data.result.errors);
                    }
                },
                fail: function(e, data) {
                    $('.loading').remove();
                    self.showErrors('#response-upload-file', data.textStatus);
                },
                start: function(e, data) {
                    $(this).parent().append('<span class="loading"><i class="icon16 loading"></i><span id="progress"></span></span>');
                },
                progressall: function(e, data) {
                    var progress = parseInt(data.loaded / data.total * 100, 10);
                    $('#progress').html(progress + '%');
                }
            });
        },
        addTemplate: function() {
            $('.add-template').click(function() {
                $('.template-name').show();
                $(this).hide();
                $('.с-add-template').show();
                $('.save-template').show();
                return false;
            });
        },
        сAddTemplate: function() {
            $('.с-add-template').click(function() {
                $('.template-name').hide();
                $(this).hide();
                $('.add-template').show();
                $('.save-template').hide();
                return false;
            });
        },
        saveTemplate: function() {
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

                var form = $(this).closest('form');
                $('.loading-template').show();
                $('.save-template').hide();
                $('.с-add-template').hide();
                $.ajax({
                    type: 'POST',
                    url: '?plugin=updateproducts&action=save',
                    data: form.serializeArray(),
                    dataType: 'json',
                    success: function(data, textStatus, jqXHR) {
                        $('.loading-template').hide();
                        $('.template-buttons').append('<span class="success-template"><i class="icon16 yes"></i>' + data.data.message + '</span>');
                        var option = $('<option>' + $('.template-name').val() + '</option>');
                        option.appendTo('.templates-list')
                        option.attr('selected', 'selected');
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
        },
        updateTemplate: function() {
            $('.update-template').click(function() {
                var form = $('form#plugins-settings-form');
                var selected = $('.templates-list option:selected');
                var template_name = selected.text();
                $('.loading-template').show();
                $.ajax({
                    type: 'POST',
                    url: '?plugin=updateproducts&action=update',
                    data: form.serializeArray(),
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
        },
        deleteTemplate: function() {
            $('.delete-template').click(function() {
                var selected = $('.templates-list option:selected');
                var template_name = selected.text();
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
                        selected.remove();
                        $('.templates-list').change();
                    },
                    error: function(jqXHR, errorText) {

                    }
                });
                return false;
            });
        },
        templatesListChange: function() {
            $('.templates-list').change(function() {
                var template_name = $(this).find('option:selected').text();
                var template = templates[template_name];
                $('input[type="checkbox"]').attr('checked', false);
                for (var key in template) {
                    var val = template[key];
                    $('[name="shop_updateproducts[' + key + ']"]').val(val);
                    $('[name="shop_updateproducts[' + key + ']"][type="checkbox"]').attr('checked', true);
                }
            });
            $('.templates-list').change();
        },
        showErrors: function(container, errors) {
            if (Array.isArray(errors)) {
                $(container).html('');
                for (var i in errors) {
                    $(container).append('<div class="error">' + errors[i] + '</div>');
                }
            } else if (typeof errors == 'string') {
                $(container).html('<div class="error">' + errors + '</div>');
            }
        }
    };

})(jQuery);