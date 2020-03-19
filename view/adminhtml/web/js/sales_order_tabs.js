
require([
    "jquery"
], function ($) {
    console.log('loaded');

    $("body").on('click', '#payu_easyplus_transstate', function(e) {

        console.log("window.PayUAjaxCheck: ", window.PayUAjaxCheck);
        console.log("window.PayUAjaxFormKey: ", window.PayUAjaxFormKey);
        console.log("FORM_KEY: ", FORM_KEY);
        console.log(" window.FORM_KEY: ",  window.FORM_KEY);
        console.log("$.mage.cookies: ", $.mage.cookies);

        //your code to send ajax request here
        $.ajax({
            showLoader: true,
            url: window.PayUAjaxCheck,
            type: "POST",
            data: {
                form_key: window.FORM_KEY,
                order_id: window.PayUAjaxOrderId
            },
            dataType: 'json'
        }).done(function (data) {
            console.log(data);
        });

    });

});
