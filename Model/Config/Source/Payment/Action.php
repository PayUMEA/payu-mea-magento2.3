<?php
/**
 * PayU_EasyPlus payment action source model
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model\Config\Source\Payment;

use Magento\Framework\Option\ArrayInterface;

/**
 *
 * PayU Payment Action Dropdown source
 */
class Action implements ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            /*[
                'value' => \Magento\Payment\Model\Method\AbstractMethod::ACTION_AUTHORIZE,
                'label' => __('Authorize Only')
            ],
            [
                'value' => \Magento\Payment\Model\Method\AbstractMethod::ACTION_AUTHORIZE_CAPTURE,
                'label' => __('Authorize and Capture')
            ],*/
            [
                'value' => \Magento\Payment\Model\Method\AbstractMethod::ACTION_ORDER,
                'label' => __('Order')
            ]
        ];
    }
}
