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
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use PayU\EasyPlus\Controller\AbstractAction;
use PayU\EasyPlus\Model\AbstractPayU;

class Response extends AbstractAction
{
    public function getRedirectConfigData($field, $storeId = null)
    {
        $path = 'payumea/redirect/' . $field;

        return $this->_scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Retrieve transaction information and validates payment
     *
     * @return \Magento\Framework\Controller\Result\Redirect|ResponseInterface
     * @throws Exception
     */
    public function execute()
    {
        $processId = uniqid();
        $processClass = self::class;
        $alreadyProcessed = false;

        $orderId = $this->_getCheckoutSession()->getLastRealOrderId() ??
            $this->_getCheckoutSession()->getData('last_real_order_id');

        try {
            $payUReference = $this->getPayUReference();

            $canProceed = $this->responseProcessor->canProceed($orderId, $processId, $processClass);

            if (!$canProceed) {
                $page = $this->responseProcessor->redirectTo($orderId, $payUReference);

                switch ($page) {
                    case \PayU\EasyPlus\Model\Processor\Response::SUCCESS_PAGE:
                        return $this->sendSuccessPage();
                    case \PayU\EasyPlus\Model\Processor\Response::FAILED_PAGE:
                        return $this->sendFailedPage();
                    default:
                        return $this->sendPendingPage();
                }
            }

            $this->logger->debug([
                'info' => "($processId) ($orderId) $processClass START"
            ]);

            $order = $this->_orderFactory->create()->loadByIncrementId($orderId);

            if (!$order) {
                return $this->sendFailedPage('Order no found');
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

            $bypassPayuRedirect = (bool)$this->getRedirectConfigData('bypass');

            if ($bypassPayuRedirect) {
                $this->logger->debug([
                    'info' => "($processId) ($orderId) $processClass Redirect Disabled, checking possible existing IPN status"
                ]);

                // If the order is already a success
                if ($alreadyProcessed) {
                    $this->logger->debug([
                        'info' => "($processId) ($orderId) $processClass ALREADY SUCCESS (via IPN): Redirect User"
                    ]);
                    $this->responseProcessor->updateTransactionLog($orderId, $processId);

                    return $this->sendSuccessPage();
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
                    $this->responseProcessor->updateTransactionLog($orderId, $processId);

                    return $this->sendPendingPage();
                }

                return $this->sendFailedPage();
            } else {
                $this->logger->debug([
                    'info' => "($processId) ($orderId) $processClass Redirect Enabled, processing redirect response."
                ]);
            }

            if ($alreadyProcessed) {
                $this->logger->debug([
                    'info' => "($processId) ($orderId) $processClass ALREADY SUCCESS (from IPN) -> Redirect User"
                ]);
                $this->responseProcessor->updateTransactionLog($orderId, $processId);

                return $this->sendSuccessPage();
            }

            if ($payUReference) {
                $this->response->setData('params', $payUReference);

                $successful = $this->response->processReturn($order, $processId, $processClass);

                $this->responseProcessor->updateTransactionLog($orderId, $processId);

                if ($successful) {
                    return $this->sendSuccessPage();
                }

                $message = $this->response->getDisplayMessage();

                if ($this->response->isCancelPayflex($order)) {
                    $this->messageManager->addErrorMessage($message);
                    
                    return $this->returnToCart();
                }

                if ($this->response->isPaymentPending() || $this->response->isPaymentProcessing()) {
                    $this->messageManager->addNoticeMessage($message);

                    return $this->sendPendingPage();
                }

                if ($this->response->isPaymentFailed()) {
                    return $this->sendFailedPage($message);
                }
            }
        } catch (LocalizedException|Exception $exception) {
            $this->logger->debug([
                'error' => "($processId) ($orderId) $processClass" . $exception->getMessage()
            ]);
            $this->messageManager->addExceptionMessage($exception, __($exception->getMessage()));
            $this->clearSessionData();
        }

        $this->responseProcessor->updateTransactionLog($orderId, $processId);

        return $this->returnToCart();
    }
}
