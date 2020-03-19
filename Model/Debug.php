<?php
/**
 * PayU_EasyPlus debug model
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model;

/**
 * @method \PayU\EasyPlus\Model\ResourceModel\Debug _getResource()
 * @method \PayU\EasyPlus\Model\ResourceModel\Debug getResource()
 * @method string getRequestBody()
 * @method \PayU\EasyPlus\Model\Debug setRequestBody(string $value)
 * @method string getResponseBody()
 * @method \PayU\EasyPlus\Model\Debug setResponseBody(string $value)
 * @method string getRequestSerialized()
 * @method \PayU\EasyPlus\Model\Debug setRequestSerialized(string $value)
 * @method string getResultSerialized()
 * @method \PayU\EasyPlus\Model\Debug setResultSerialized(string $value)
 * @method string getRequestDump()
 * @method \PayU\EasyPlus\Model\Debug setRequestDump(string $value)
 * @method string getResultDump()
 * @method \PayU\EasyPlus\Model\Debug setResultDump(string $value)
 */
class Debug extends \Magento\Framework\Model\AbstractModel
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init('PayU\EasyPlus\Model\ResourceModel\Debug');
    }
}
