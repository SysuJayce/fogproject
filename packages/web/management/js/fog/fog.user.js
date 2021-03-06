$(function() {
    checkboxToggleSearchListPages();
    form = $('.username-input').parents('form');
    validator = form.validate({
        rules: {
            name: {
                required: {
                    depends: function() {
                        $(this).val($.trim($(this).val()));
                        return true;
                    }
                },
                minlength: 1,
                maxlength: 255
            },
            password: {
                required: {
                    depends: function() {
                        $(this).val($.trim($(this).val()));
                        return true;
                    }
                },
                minlength: 4
            },
            password_confirm: {
                minlength: 4,
                equalTo: '#password'
            }
        },
        messages: {
            password_confirm: {
                minlength: 'Password must be at least 4 characters long',
                equalTo: 'Passwords do not match',
            }
        }
    });
    $('.username-input').rules('add', {regex: /^[a-zA-Z0-9_-]{3,40}$/});
    $('.username-input,.password-input1,.password-input2').on('keyup change blur',function() {
        return validator.element(this);
    });
});
