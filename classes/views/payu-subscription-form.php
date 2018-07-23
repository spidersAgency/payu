<input type="hidden" name="payu_subscription" id="payu_subscription" value="1">
<input type="hidden" name="payu_token_type" id="payu_token_type" value="">
<input type="hidden" name="payu_value" id="payu_value" value="">
<input type="hidden" name="payu_masked_card" id="payu_masked_card" value="">
<input type="hidden" name="payu_type" id="payu_type" value="">
<button style="display:none;" id="payu_place_order"></button>
<script
        src="<?php echo $widget_url; ?>"
        pay-button="#payu_place_order"
        merchant-pos-id="<?php echo $merchant_pos_id; ?>"
        shop-name="<?php echo $shop_name; ?>"
        total-amount="<?php echo $total_amount; ?>"
        currency-code="<?php echo $currency_code; ?>"
        customer-language="<?php echo $customer_language; ?>"
        store-card="<?php echo $store_card; ?>"
        recurring-payment="<?php echo $recurring_payment; ?>"
        customer-email="<?php echo $customer_email; ?>"
        sig="<?php echo $sig; ?>"
        success-callback="payu_subscription_callback"
>
</script>
<script type="text/javascript">
    function payu_subscription_callback(response) {
        jQuery.payuResponse = response;
        fillSignUpForm(response);
        jQuery('#place_order').click();
    }

    function fillSignUpForm(response) {
        jQuery('#payu_token_type').val(response.tokenType);
        jQuery('#payu_value').val(response.value);
        jQuery('#payu_masked_card').val(response.maskedCard);
        jQuery('#payu_type').val(response.type);
    }

    (function ($) {
        $(document).ready(function () {
            $(document).on('click', '#place_order', function (e) {
                var payuResponse = jQuery.payuResponse;

                if (!payuResponse && jQuery('#payment_method_payu_recurring').is(':checked')) {
                    e.preventDefault();
                    $('#payu_place_order').click();
                } else {
                    fillSignUpForm(payuResponse);
                }
            })
        })
    })(jQuery);

</script>