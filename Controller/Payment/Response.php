<?php
/**
 * PayU_EasyPlus payment response validation controller
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Controller\Payment;

use Exception;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Sales\Model\Order;
use PayU\EasyPlus\Controller\AbstractAction;
use PayU\EasyPlus\Model\AbstractPayU;

class Response extends AbstractAction
{
    public function getRedirectConfigData($field, $storeId = null)
    {
        $path = 'payment/payumea_redirect_config/' . $field;
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Retrieve transaction information and validates payment
     *
     * @return \Magento\Framework\Controller\Result\Redirect|\Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        $bypass_payu_redirect = $this->getRedirectConfigData('bypass_payu_redirect');

        $processId = uniqid();
        $processString = self::class;

        $this->_getSession()->setPayUProcessId($processId);
        $this->_getSession()->setPayUProcessString($processString);

        $this->logger->debug(['info' => "($processId) START $processString"]);

        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $result = '';
        $orderId = '';

        try {
            $payu = $this->_initPayUReference();

            // if there is an order - load it
            $orderId = $this->_getCheckoutSession()->getLastOrderId();

            /** @var Order $order */
            $order = $orderId ? $this->_orderFactory->create()->load($orderId) : false;

            if ('1' === $bypass_payu_redirect) {
                $this->logger->debug([
                    'info' => "($processId) ($orderId) PayU Redirect Disabled, checking possible existing IPN status"
                ]);

                $orderState = $order->getState();

                // If the order is already a success
                if (in_array(
                    $orderState,
                    [
                        Order::STATE_PROCESSING,
                        Order::STATE_COMPLETE
                    ]
                )) {
                    $this->logger->debug([
                        'info' => "($processId) ($orderId) PayU $processString ALREADY SUCCESS (via IPN): Redirect User"
                    ]);

                    return $this->sendSuccessPage($order);
                }

                // Or still pending
                if (in_array(
                    $orderState,
                    [
                        Order::STATE_PENDING_PAYMENT,
                        AbstractPayU::MAGENTO_ORDER_STATE_PENDING
                    ]
                )) {
                    $this->logger->debug(['info' => "($processId) ($orderId) PayU $processString Order status pending"]);

                    return $this->sendPendingPage($order);
                }

                $result = 'Unable to validate order';
                // Else there is a failure of some sort
                $this->messageManager->addExceptionMessage(
                    new LocalizedException(new Phrase($result)),
                    __($result)
                );
                $this->_returnCustomerQuote(true, __($result));

                return $resultRedirect->setPath('checkout/cart');
            } else {
                $this->logger->debug([
                    'info' => "($processId) ($orderId) PayU Redirect Enabled, processing redirect response."
                ]);
            }

            if ($order->getState() == Order::STATE_PROCESSING) {
                $this->logger->debug([
                    'info' => "($processId) ($orderId) PayU $processString ALREADY SUCCESS (from IPN) -> Redirect User"
                ]);

                return $this->sendSuccessPage($order);
            }

            if ($payu && $order) {
                $this->response->setData('params', $payu);

                $result = $this->response->processReturn($order);

                if ($result !== true) {
                    $this->messageManager->addErrorMessage(__($result));
                } else {
                    return $this->sendSuccessPage($order);
                }
            }
        } catch (LocalizedException $localizedException) {
            $this->logger->debug([
                'error' => "LocalizedException: ($processId) ($orderId) " . $localizedException->getMessage()
            ]);
            $this->messageManager->addExceptionMessage($localizedException, __($localizedException->getMessage()));
        } catch (Exception $exception) {
            $this->logger->debug(['error' => "Exception: ($processId) ($orderId) " . $exception->getMessage()]);
            $this->messageManager->addExceptionMessage($exception, __($exception->getMessage()));
        }

        $this->_returnCustomerQuote(true, $result);

        return $resultRedirect->setPath('checkout/cart');
    }
}
