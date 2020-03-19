<?php
/**
 * PayU_EasyPlus redirect channel source model
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model\System\Config\Source\Redirect;

use Magento\Framework\Option\ArrayInterface;

/**
 *
 * PayU Redirect Channel Dropdown source
 */
class Channel implements ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'responsive',
                'label' => __('Responsive'),
            ],
            [
                'value' => 'web',
                'label' => __('Web')
            ],
        ];
    }
}
