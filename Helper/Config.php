<?php

namespace PayU\EasyPlus\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;

class Config extends AbstractHelper
{
    /**
     * Config path of target section
     */
    const CONFIG_PAYU_SECTION_ID = 'payu_redirectpayment';

    /**
     * Get PayU store configuration from dynamic fields by payment method
     *
     * @param string $paymentMethod
     * @param string $key
     * @param string $scopeType
     * @param null $scopeCode
     * @return array
     */
    public function getPayUStoreConfigByDynamicField(
        string $paymentMethod,
        string $key,
        $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        $scopeCode = null
    ) : array {
        $configPath = implode(
            '/',
            [
                self::CONFIG_PAYU_SECTION_ID,
                $paymentMethod,
                $key
            ]
        );

        $rawValue = $this->scopeConfig->getValue($configPath, $scopeType, $scopeCode);

        if(!$rawValue)
            return [];

        // Split on either comma or newline to accommodate both multiselect
        // and textarea field types.
        $parsedValues = preg_split('/[,\n]/', $rawValue);

        return $parsedValues;
    }
}