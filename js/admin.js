jQuery(document).ready(function($) {
    $('#test-credentials').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $result = $('#test-credentials-result');
        
        // Get current form values
        const username = $('#wpe_username').val();
        const password = $('#wpe_password').val();
        
        // Disable button and show loading state
        $button.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: wpeAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'test_wpe_credentials',
                nonce: wpeAdmin.nonce,
                username: username,
                password: password
            },
            success: function(response) {
                if (response.success) {
                    $result
                        .removeClass('error')
                        .addClass('success')
                        .html(response.data.message)
                        .slideDown();
                } else {
                    $result
                        .removeClass('success')
                        .addClass('error')
                        .html(response.data.message)
                        .slideDown();
                }
            },
            error: function() {
                $result
                    .removeClass('success')
                    .addClass('error')
                    .html('An error occurred while testing the credentials.')
                    .slideDown();
            },
            complete: function() {
                // Reset button state
                $button.prop('disabled', false).text('Test Credentials');
            }
        });
    });
});
