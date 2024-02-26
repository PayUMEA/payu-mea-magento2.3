
require([
    "jquery"
], function ($) {
    $("body").on('click', '#payu_easyplus_txn_status', function(e) {
        $.ajax({
            showLoader: true,
            url: window.PayUAjaxCheck,
            type: "POST",
            data: {
                form_key: window.FORM_KEY,
                order_id: window.PayUAjaxOrderId
            },
            dataType: 'json'
        }).done(function (response) {
            $("#txn_data").html('<pre>' + JSON.stringify(response.data, undefined, 2) + '</pre>')
        });
    });
});
