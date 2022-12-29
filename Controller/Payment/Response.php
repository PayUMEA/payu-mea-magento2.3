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

        $process_id = uniqid();
        $process_string = self::class;

        $this->_getSession()->setPayUProcessId($process_id);
        $this->_getSession()->setPayUProcessString($process_string);

        $this->_logger->info("($process_id) START $process_string");

        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $result = '';
        try {
            $payu = $this->_initPayUReference();

            // if there is an order - load it
            $orderId = $this->_getCheckoutSession()->getLastOrderId();

            /** @var Order $order */
            $order = $orderId ? $this->_orderFactory->create()->load($orderId) : false;

            if ('1' === $bypass_payu_redirect) {
                $this->_logger->info("($process_id) ($orderId) PayU Redirect Disabled, checking possible existing IPN status");

                $order_state = $order->getState();

                // If the order is already a success
                if (in_array($order_state, [
                    Order::STATE_PROCESSING,
                    Order::STATE_COMPLETE
                ])) {
                    $this->_logger->info("($process_id) ($orderId) PayU $process_string ALREADY SUCCESS (from IPN) -> Redirect User");
                    return $this->sendSuccessPage($order);
                }

                // Or still pending
                if (in_array($order_state, [
                    \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT,
                ])) {
                    $this->_logger->info("($process_id) ($orderId) PayU $process_string Order status pending");
                    return $this->sendPendingPage($order);
                }

                // Else there is a failure of some sort
                $this->messageManager->addExceptionMessage(
                    new LocalizedException(new Phrase('Unable to validate order')),
                    __('Unable to validate order')
                );
                $this->_returnCustomerQuote(true, $result);

                return $resultRedirect->setPath('checkout/cart');
            } else {
                $this->_logger->info("($process_id) ($orderId) PayU Redirect Enabled, processing redirect response.");
            }

            if ($order->getState() == Order::STATE_PROCESSING) {
                $this->_logger->info(
                    "($process_id) ($orderId) PayU $process_string ALREADY SUCCESS (from IPN) -> Redirect User"
                );

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
        } catch (LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e, __('Unable to validate order'));
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Unable to validate order'));
        }

        $this->_returnCustomerQuote(true, $result);

        return $resultRedirect->setPath('checkout/cart');
    }
}
