jQuery(document).ready(function(){
    function ia_change() {
        if ( jQuery('#woocommerce_payu_payu_ia_enabled').is(':checked') ) {
            jQuery('#woocommerce_payu_ia_title').closest('tr').show();
            jQuery('#woocommerce_payu_ia_description').closest('tr').show();
            jQuery('#woocommerce_payu_ia_title').prop('required',true);
        }
        else {
            jQuery('#woocommerce_payu_ia_title').closest('tr').hide();
            jQuery('#woocommerce_payu_ia_description').closest('tr').hide();
            jQuery('#woocommerce_payu_ia_title').prop('required',false);
        }
    }

    function subscriptions_change() {
        if ( jQuery('#woocommerce_payu_payu_subscriptions_enabled').is(':checked') ) {
            jQuery('#woocommerce_payu_subscriptions_title').closest('tr').show();
            jQuery('#woocommerce_payu_subscriptions_description').closest('tr').show();
            jQuery('#woocommerce_payu_subscriptions_title').prop('required',true);
        }
        else {
            jQuery('#woocommerce_payu_subscriptions_title').closest('tr').hide();
            jQuery('#woocommerce_payu_subscriptions_description').closest('tr').hide();
            jQuery('#woocommerce_payu_subscriptions_title').prop('required',false);
        }
    }

    function api_version_change() {
        if ( jQuery('#woocommerce_payu_api_version').val() == 'rest_api' ) {
            jQuery('#woocommerce_payu_returns_title').hide();
            jQuery('#woocommerce_payu_returns_title').next().hide();
            jQuery('#woocommerce_payu_key_1').closest('tr').hide();
            jQuery('#woocommerce_payu_pos_auth_key').closest('tr').hide();
            jQuery('#woocommerce_payu_check_sig').closest('tr').hide();
            jQuery('#woocommerce_payu_testmode').closest('tr').hide();

            jQuery('#woocommerce_payu_client_id').closest('tr').show();
            jQuery('#woocommerce_payu_client_secret').closest('tr').show();

            jQuery('#woocommerce_payu_sandbox').closest('tr').show();
            jQuery('#woocommerce_payu_sandbox_pos_id').closest('tr').show();
            jQuery('#woocommerce_payu_sandbox_key_2').closest('tr').show();
            jQuery('#woocommerce_payu_sandbox_client_id').closest('tr').show();
            jQuery('#woocommerce_payu_sandbox_client_secret').closest('tr').show();
            jQuery('#woocommerce_payu_return_error').closest('table').hide();
            sandbox_change();
        }
        else {
            jQuery('#woocommerce_payu_returns_title').show();
            jQuery('#woocommerce_payu_returns_title').next().show();

            jQuery('#woocommerce_payu_pos_id').closest('tr').show();
            jQuery('#woocommerce_payu_key_2').closest('tr').show();
            jQuery('#woocommerce_payu_key_1').closest('tr').show();
            jQuery('#woocommerce_payu_pos_auth_key').closest('tr').show();
            jQuery('#woocommerce_payu_check_sig').closest('tr').show();
            jQuery('#woocommerce_payu_testmode').closest('tr').show();

            jQuery('#woocommerce_payu_client_id').closest('tr').hide();
            jQuery('#woocommerce_payu_client_secret').closest('tr').hide();

            jQuery('#woocommerce_payu_sandbox').closest('tr').show();
            jQuery('#woocommerce_payu_sandbox_pos_id').closest('tr').hide();
            jQuery('#woocommerce_payu_sandbox_key_2').closest('tr').hide();
            jQuery('#woocommerce_payu_sandbox_client_id').closest('tr').hide();
            jQuery('#woocommerce_payu_sandbox_client_secret').closest('tr').hide();
            jQuery('#woocommerce_payu_return_error').closest('table').show();
        }
    }

    function sandbox_change() {
        if ( jQuery('#woocommerce_payu_api_version').val() == 'rest_api'
            && jQuery('#woocommerce_payu_sandbox').is(':checked')
        ) {
            jQuery('#woocommerce_payu_sandbox_pos_id').closest('tr').show();
            jQuery('#woocommerce_payu_sandbox_key_2').closest('tr').show();
            jQuery('#woocommerce_payu_sandbox_client_id').closest('tr').show();
            jQuery('#woocommerce_payu_sandbox_client_secret').closest('tr').show();

            jQuery('#woocommerce_payu_pos_id').closest('tr').hide();
            jQuery('#woocommerce_payu_key_2').closest('tr').hide();
            jQuery('#woocommerce_payu_client_id').closest('tr').hide();
            jQuery('#woocommerce_payu_client_secret').closest('tr').hide();
        }
        else {
            jQuery('#woocommerce_payu_sandbox_pos_id').closest('tr').hide();
            jQuery('#woocommerce_payu_sandbox_key_2').closest('tr').hide();
            jQuery('#woocommerce_payu_sandbox_client_id').closest('tr').hide();
            jQuery('#woocommerce_payu_sandbox_client_secret').closest('tr').hide();
            jQuery('#woocommerce_payu_pos_id').closest('tr').show();
            jQuery('#woocommerce_payu_key_2').closest('tr').show();
            jQuery('#woocommerce_payu_client_id').closest('tr').show();
            jQuery('#woocommerce_payu_client_secret').closest('tr').show();
        }
    }

    jQuery('<span>'+ payu_admin_object.protocol +' </span>').insertBefore('#woocommerce_payu_return_error, #woocommerce_payu_return_ok, #woocommerce_payu_return_reports');
    jQuery('#woocommerce_payu_return_error').val(payu_admin_object.site_url + '?wc-api=WC_Gateway_Payu&sessionId=%sessionId%&orderId=%orderId%&errorId=%error%');
    jQuery('#woocommerce_payu_return_ok').val(payu_admin_object.site_url + '?wc-api=WC_Gateway_Payu&sessionId=%sessionId%&orderId=%orderId%');
    jQuery('#woocommerce_payu_return_reports').val(payu_admin_object.site_url + '?wc-api=WC_Gateway_Payu&sessionId=%sessionId%&orderId=%orderId%');

    api_version_change();
    jQuery('#woocommerce_payu_api_version').on('change',function(){
        api_version_change();
    })
    sandbox_change();
    jQuery('#woocommerce_payu_sandbox').on('click',function(){
        sandbox_change();
    })
    ia_change();
    jQuery('#woocommerce_payu_payu_ia_enabled').on('change',function(){
        ia_change();
    })
    subscriptions_change();
    jQuery('#woocommerce_payu_payu_subscriptions_enabled').on('change',function(){
        subscriptions_change();
    })
});