<?php
/**
 * PayU_EasyPlus cancelled checkout controller
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Controller\Payment;

use PayU\EasyPlus\Helper\XmlHelper;
use PayU\EasyPlus\Controller\AbstractAction;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Notify extends AbstractAction implements CsrfAwareActionInterface
{
    /**
     * Process Instant Payment Notification (IPN) from PayU
     */
    public function execute()
    {

        $postData = file_get_contents("php://input");
        $sxe = simplexml_load_string($postData);

        if(empty($sxe)) {
            http_response_code('500');
        }

        $ipnData = XMLHelper::parseXMLToArray($sxe);

        if($ipnData) {
            $incrementId = $ipnData['MerchantReference'];
            /** @var \Magento\Sales\Model\Order $order */
            $order = $incrementId ? $this->_orderFactory->create()->loadByIncrementId($incrementId) : false;
            if ($order) {
                $this->response->processNotify($ipnData, $order);
                http_response_code('200');
            } else {
                http_response_code('500');
            }
        } else {
            http_response_code('500');
        }
    }
    /**
     * @param RequestInterface $request
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }
}
