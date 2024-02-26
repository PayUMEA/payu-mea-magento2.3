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
        $bypassPayuRedirect = (bool)$this->getRedirectConfigData('bypass_payu_redirect');

        $processId = uniqid();
        $processClass = self::class;

        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $orderId = '';
        $alreadyProcessed = false;
        $message = 'Error encountered processing payment';

        try {
            $payu = $this->_initPayUReference();

            // if there is an order - load it
            $orderId = $this->_getCheckoutSession()->getLastOrderId() ??
                $this->_getCheckoutSession()->getData('last_order_id');

            /** @var Order $order */
            $order = $orderId ? $this->_orderRepository->get($orderId) : false;

            if (!$order) {
                throw new LocalizedException(__("Order no found"));
            }

            $orderState = $order->getState();
            $orderStatus = $order->getStatus();

            // If the order is already a success
            if ($order->hasInvoices() ||
                in_array(
                    $orderState,
                    [
                        Order::STATE_PROCESSING,
                        Order::STATE_COMPLETE
                    ]
                ) ||
                in_array(
                    $orderStatus,
                    [
                        Order::STATE_PROCESSING,
                        Order::STATE_COMPLETE
                    ]
                )
            ) {
                $alreadyProcessed = true;
            }

            if ($bypassPayuRedirect) {
                $this->logger->debug([
                    'info' => "($processId) ($orderId) $processClass Redirect Disabled, checking possible existing IPN status"
                ]);

                $orderState = $order->getState();

                // If the order is already a success
                if ($alreadyProcessed) {
                    $this->logger->debug([
                        'info' => "($processId) ($orderId) $processClass ALREADY SUCCESS (via IPN): Redirect User"
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
                    $this->logger->debug(['info' => "($processId) ($orderId) $processClass order status pending"]);

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
                    'info' => "($processId) ($orderId) $processClass Redirect Enabled, processing redirect response."
                ]);
            }

            /** @var Order $order */
            $order = $orderId ? $this->_orderRepository->get($orderId) : false;

            if ($alreadyProcessed) {
                $this->logger->debug([
                    'info' => "($processId) ($orderId) $processClass ALREADY SUCCESS (from IPN) -> Redirect User"
                ]);

                return $this->sendSuccessPage($order);
            }

            if ($payu) {
                $this->response->setData('params', $payu);

                $successful = $this->response->processReturn($order, $processId, $processClass);
                $message = $this->response->getDisplayMessage();

                if ($successful) {
                    return $this->sendSuccessPage($order);
                }

                if ($this->response->isPaymentPending() || $this->response->isPaymentProcessing()) {
                    $this->messageManager->addSuccessMessage($this->response->getDisplayMessage());

                    return $this->sendPendingPage($order);
                }

                $this->messageManager->addErrorMessage(__($message));
            }
        } catch (LocalizedException $localizedException) {
            $this->logger->debug([
                'error' => "LocalizedException: ($processId) ($orderId) $processClass" . $localizedException->getMessage()
            ]);
            $this->messageManager->addExceptionMessage($localizedException, __($localizedException->getMessage()));
            $this->clearSessionData();
        } catch (Exception $exception) {
            $this->logger->debug(['error' => "Exception: ($processId) ($orderId) $processClass" . $exception->getMessage()]);
            $this->messageManager->addExceptionMessage($exception, __($exception->getMessage()));
            $this->clearSessionData();
        }

        $this->_returnCustomerQuote(true, __($message));

        return $resultRedirect->setPath('checkout/cart');
    }
}
