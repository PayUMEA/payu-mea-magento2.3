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

use Exception;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use PayU\EasyPlus\Controller\AbstractAction;
use PayU\EasyPlus\Helper\XmlHelper;

class Notify extends AbstractAction implements CsrfAwareActionInterface
{
    /**
     * Process Instant Payment Notification (IPN) from PayU
     * @throws NoSuchEntityException|LocalizedException
     * @throws Exception
     */
    public function execute()
    {
        $processId = uniqid();
        $processClass = self::class;

        $postData = file_get_contents("php://input");
        $sxe = simplexml_load_string($postData);

        $resultJson = $this->resultFactory
            ->create(ResultFactory::TYPE_JSON)
            ->setJsonData('{}');

        if (empty($sxe)) {
            $this->respond('500', 'Instant Payment Notification data is empty');

            return $resultJson;
        }

        $ipnData = XMLHelper::parseXMLToArray($sxe);

        if (!$ipnData) {
            $this->respond('500', 'Failed to decode Instant Payment Notification data.');

            return $resultJson;
        }

        $incrementId = $ipnData['MerchantReference'];
        $canProceed = $this->responseProcessor->canProceed($incrementId, $processId, $processClass);

        if (!$canProceed) {
            $this->respond();

            return $resultJson;
        }

        $this->logger->debug([
            'info' => "($processId) ($incrementId) $processClass START"
        ]);

        /** @var Order $order */
        $order = $incrementId ? $this->_orderFactory->create()->loadByIncrementIdAndStoreId(
            $incrementId,
            $this->_storeManager->getStore()->getId()
        ) : false;

        if (!$order || ((int)$order->getId() <= 0)) {
            $this->respond('500', 'Failed to load order.');

            return $resultJson;
        }

        $this->respond();
        $this->response->processNotify($order, $ipnData, $processId, $processClass);
        $this->responseProcessor->updateTransactionLog($incrementId, $processId);

        return $resultJson;
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
