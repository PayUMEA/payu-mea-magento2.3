<?php

namespace PayU\EasyPlus\Model\Config\Structure\Element;

use \Magento\Config\Model\Config\Structure\Element\Section as OriginalSection;
use \PayU\EasyPlus\Helper\Config as ConfigHelper;
use \PayU\EasyPlus\Model\System\Config\Source\Payment\Method as PaymentMethodSourceModel;

/**
 * Plugin to add dynamically generated store config fields
 * to Sales -> Payment Methods -> PayU Easy and Business Merchant.
 *
 * @package PayU\EasyPlus\Model\Config\Config\Structure\Element
 */
class Section
{
    /**
     * @var \PayU\EasyPlus\Model\System\Config\Source\Payment\Method
     */
    protected $paymentMethodSourceModel;

    /**
     * @var \PayU\EasyPlus\Helper\Config
     */
    protected $configHelper;

    /**
     * Group constructor.
     * @param ConfigHelper $configHelper
     * @param PaymentMethodSourceModel $paymentMethodSourceModel
     */
    public function __construct(
        ConfigHelper $configHelper,
        PaymentMethodSourceModel $paymentMethodSourceModel
    ) {
        $this->configHelper = $configHelper;
        $this->paymentMethodSourceModel = $paymentMethodSourceModel;
    }

    /**
     * Get config options array of regions for given country
     *
     * @return array
     */
    protected function getAvailablePaymentMethods() : array {

        return $this->configHelper->getPayUStoreConfigByDynamicField('general', 'payment_methods');
    }

    /**
     * Get dynamic config fields (if any)
     *
     * @return array
     */
    protected function getDynamicConfigGroups() : array {
        $availablePaymentMethods = $this->getAvailablePaymentMethods();

        $dynamicConfigGroups = [];
        foreach($availablePaymentMethods as $index => $paymentMethod) {
            // Use a consistent prefix for dynamically generated fields
            // to allow them to be deterministic but not collide with any
            // preexisting fields.
            // ConfigHelper::PAYU_STORE_CONFIG_PATH_PREFIX == 'payu_store_config_'.
            $groupId = strtolower($paymentMethod);

            $dynamicConfigGroups[$groupId] = [    // Declare group information
                'id' => $groupId,                   // Use dynamic group ID
                'label' => __(
                    'Configuration for: %1',
                    $this->paymentMethodSourceModel->getValue($paymentMethod)
                ),
                'showInDefault' => '1',             // Show in default scope
                'showInWebsite' => '1',             // Show in website scope
                'showInStore' => '1',               // Show in store scope
                '_elementType' => 'group',
                'sortOrder' => ($index * 10),       // Generate unique and deterministic sortOrder values
                'children' => $this->getDynamicConfigFields($groupId),  // Use dynamic fields generated above
            ];
        }

        return $dynamicConfigGroups;
    }

    protected function getDynamicConfigFields(string $groupId)
    {
        $dynamicConfigFields['active'] = [
            'id' => 'active',
            'type' => 'select',
            'sortOrder' => 10,
            'showInDefault' => 1,
            'showInWebsite' => 1,
            'showInStore' => 0,
            'label' => __('Enabled'),
            'source_model' => 'Magento\Config\Model\Config\Source\Yesno',
            '_elementType' => 'field',
            'path' => implode(
                '/',
                [
                    ConfigHelper::CONFIG_PAYU_SECTION_ID,
                    $groupId
                ]
            )
        ];

        $dynamicConfigFields['environment'] = [
            'id' => 'environment',
            'type' => 'radios',
            'sortOrder' => 10,
            'showInDefault' => 1,
            'showInWebsite' => 1,
            'showInStore' => 0,
            'value' => 0,
            'label' => __('Environment'),
            'source_model' => 'PayU\EasyPlus\Model\System\Config\Source\Payment\Gateway',
            '_elementType' => 'field',
            'path' => implode(
                '/',
                [
                    ConfigHelper::CONFIG_PAYU_SECTION_ID,
                    $groupId
                ]
            )
        ];

        $dynamicConfigFields['api_username'] = [
            'id' => 'api_username',
            'type' => 'text',
            'sortOrder' => 20,
            'showInDefault' => 1,
            'showInWebsite' => 1,
            'showInStore' => 0,
            //'frontend_class' => 'validate-number',
            'label' => __('API Username'),
            '_elementType' => 'field',
            'path' => implode(
                '/',
                [
                    ConfigHelper::CONFIG_PAYU_SECTION_ID,
                    $groupId
                ]
            )
        ];

        $dynamicConfigFields['api_password'] = [
            'id' => 'api_password',
            'type' => 'obscure',
            'sortOrder' => 30,
            'showInDefault' => 1,
            'showInWebsite' => 1,
            'showInStore' => 0,
            'label' => __('API Password'),
            '_elementType' => 'field',
            'backend_model' => 'Magento\Config\Model\Config\Backend\Encrypted',
            'path' => implode(
                '/',
                [
                    ConfigHelper::CONFIG_PAYU_SECTION_ID,
                    $groupId
                ]
            )
        ];

        $dynamicConfigFields['safe_key'] = [
            'id' => 'safe_key',
            'type' => 'obscure',
            'sortOrder' => 40,
            'showInDefault' => 1,
            'showInWebsite' => 1,
            'showInStore' => 0,
            'label' => __('Safe key'),
            '_elementType' => 'field',
            'backend_model' => 'Magento\Config\Model\Config\Backend\Encrypted',
            'path' => implode(
                '/',
                [
                    ConfigHelper::CONFIG_PAYU_SECTION_ID,
                    $groupId
                ]
            )
        ];

        $dynamicConfigFields['payment_methods'] = [
            'id' => 'payment_methods',
            'type' => 'textarea',
            'sortOrder' => 50,
            'showInDefault' => 1,
            'showInWebsite' => 1,
            'showInStore' => 0,
            'label' => __('Payment Methods'),
            'comment' => __(
                'Enter supported payment method, one per line.'
            ),
            '_elementType' => 'field',
            'path' => implode(
                '/',
                [
                    ConfigHelper::CONFIG_PAYU_SECTION_ID,
                    $groupId
                ]
            )
        ];

        $dynamicConfigFields['redirect_channel'] = [
            'id' => 'redirect_channel',
            'type' => 'radios',
            'sortOrder' => 60,
            'showInDefault' => 1,
            'showInWebsite' => 1,
            'showInStore' => 0,
            'label' => __('Redirect Channel'),
            'comment' => __(
                'Only use <strong>Web</strong> if payment method is set to <strong>Discovery Miles</strong>'
            ),
            '_elementType' => 'field',
            'source_model' => 'PayU\EasyPlus\Model\System\Config\Source\Redirect\Channel',
            'path' => implode(
                '/',
                [
                    ConfigHelper::CONFIG_PAYU_SECTION_ID,
                    $groupId
                ]
            )
        ];

        $dynamicConfigFields['notification_url'] = [
            'id' => 'notification_url',
            'type' => 'text',
            'sortOrder' => 70,
            'showInDefault' => 1,
            'showInWebsite' => 1,
            'showInStore' => 0,
            'label' => __('Notification Url (IPN)'),
            '_elementType' => 'field',
            'path' => implode(
                '/',
                [
                    ConfigHelper::CONFIG_PAYU_SECTION_ID,
                    $groupId
                ]
            )
        ];

        $dynamicConfigFields['redirect_url'] = [
            'id' => 'redirect_url',
            'type' => 'text',
            'sortOrder' => 80,
            'showInDefault' => 1,
            'showInWebsite' => 1,
            'showInStore' => 0,
            'label' => __('Redirect Url'),
            '_elementType' => 'field',
            'path' => implode(
                '/',
                [
                    ConfigHelper::CONFIG_PAYU_SECTION_ID,
                    $groupId
                ]
            )
        ];

        $dynamicConfigFields['cancel_url'] = [
            'id' => 'cancel_url',
            'type' => 'text',
            'sortOrder' => 90,
            'showInDefault' => 1,
            'showInWebsite' => 1,
            'showInStore' => 0,
            'label' => __('Cancel Url'),
            '_elementType' => 'field',
            'path' => implode(
                '/',
                [
                    ConfigHelper::CONFIG_PAYU_SECTION_ID,
                    $groupId
                ]
            )
        ];

        return $dynamicConfigFields;
    }

    /**
     * Add dynamic config fields for each store configured
     *
     * @param OriginalSection $subject
     * @param callable $proceed
     * @param array $data
     * @param $scope
     * @return mixed
     */
    public function aroundSetData(OriginalSection $subject, callable $proceed, array $data, $scope) {
        // This method runs for every group.
        // Add a condition to check for the one to which we're
        // interested in adding fields.
        if($data['id'] == ConfigHelper::CONFIG_PAYU_SECTION_ID) {
            $dynamicGroups = $this->getDynamicConfigGroups();

            if(!empty($dynamicGroups)) {
                $data['children'] += $dynamicGroups;
            }
        }

        return $proceed($data, $scope);
    }
}