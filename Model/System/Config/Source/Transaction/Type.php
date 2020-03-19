<?php
/**
 * PayU_EasyPlus payment type source model
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model\System\Config\Source\Transaction;

class Type implements \Magento\Framework\Option\ArrayInterface
{
	// Load PayU type of payment processing 
	protected $txns = array( 
		'RESERVE' => 'Reserve (Authorize)',
		'PAYMENT' => 'Payment (Authorize & Capture)',
		//'REGISTER_LINK' => 'Register Link',
	);

	/**
	 * Options getter
	 *
	 * @return array
	 */
	public function toOptionArray()
	{
		$txns_array = array();
		foreach ($this->txns as $key => $value ) {
			$txns_array[] = array( 'value' => $key, 'label' => $value );
		}
		
		return $txns_array;
	}

	/**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
    	$txns_array = array();
    	foreach ($this->txns as $key => $value ) {
			$txns_array[$key] = $value ;
		}
		return $txns_array;
    }
}