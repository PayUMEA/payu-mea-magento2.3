<?php
/**
 * PayU_EasyPlus response/error code repository
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model\Error;

class Code 
{
	protected $payuErrorCodes = [
		'999' => 'PayU Timeout'
	];

	public function getPayUErrorCodes()
	{
		return $this->payuErrorCodes;
	}

	public function getAllErrorCodes()
	{
		return array_merge($this->payuErrorCodes);
	}
}