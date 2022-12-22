/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'payumea_creditcard',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/creditcard-method'
            },
            {
                type: 'payumea_payflex',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/payflex-method'
            },
            {
                type: 'payumea_ebucks',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/ebucks-method'
            },
            {
                type: 'payumea_rcs',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/rcs-method'
            },
            {
                type: 'payumea_rcs_plc',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/rcs_plc-method'
            },
            {
                type: 'payumea_eftpro',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/eftpro-method'
            },
            {
                type: 'payumea_discoverymiles',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/discoverymiles-method'
            },
            {
                type: 'payumea_mobicred',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/mobicred-method'
            },
            {
                type: 'payumea_ucount',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/ucount-method'
            },
            {
                type: 'payumea_fasta',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/fasta-method'
            },
            {
                type: 'payumea_mpesa',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/mpesa-method'
            },
            {
                type: 'payumea_airtel_money',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/airtel_money-method'
            },
            {
                type: 'payumea_mobile_banking',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/mobile_banking-method'
            },
            {
                type: 'payumea_mtn_mobile',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/mtn_mobile-method'
            },
            {
                type: 'payumea_tigopesa',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/tigopesa-method'
            },
            {
                type: 'payumea_equitel',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/equitel-method'
            },
            {
                type: 'payumea_moretyme',
                component: 'PayU_EasyPlus/js/view/payment/method-renderer/moretyme-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
