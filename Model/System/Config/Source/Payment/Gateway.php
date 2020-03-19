<?php
/**
 * PayU_EasyPlus payment gateway source model
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model\System\Config\Source\Payment;

class Gateway implements \Magento\Framework\Option\ArrayInterface
{
	/**
	 * Options getter
	 *
	 * @return array
	 */
	public function toOptionArray()
	{
		return array(
            array(
                'value' => '0',
                'label' => __('Sandbox (testing)')
            ),
            array(
                'value' => '1',
                'label' => __('Live (real)')
            )
        );
	}

	/**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            '0' => __('Sandbox (testing)'),
            '1' => __('Live (real)')
        ];
    }
}