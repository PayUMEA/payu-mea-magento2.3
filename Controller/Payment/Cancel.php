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
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use PayU\EasyPlus\Controller\AbstractAction;

class Cancel extends AbstractAction
{
    /**
     * Cancel Express Checkout
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $orderId = '';
        $processId = uniqid();

        try {
            $payUReference = $this->getPayUReference();

            // if there is an order - cancel it
            $orderId = $this->_getCheckoutSession()->getLastOrderId() ??
                $this->_getCheckoutSession()->getData('last_order_id');
            $quoteId = $this->_getCheckoutSession()->getLastSuccessQuoteId() ??
                $this->_getCheckoutSession()->getData('last_success_quote_id');

            /** @var Order $order */
            $order = $orderId ? $this->_orderRepository->get($orderId) : null;

            if ($payUReference &&
                $order &&
                $order->getQuoteId() == $quoteId
            ) {
                $this->response->setData('params', $payUReference);

                $this->response->processCancel($order);

                $this->messageManager->addErrorMessage(
                    __('Payment transaction unsuccessful. User canceled payment transaction.')
                );
            } else {
                $this->messageManager->addErrorMessage(
                    __('Payment cancellation unsuccessful. Order not found.')
                );
            }
        } catch (LocalizedException $localizedException) {
            $this->logger->debug(['error' => "Exception: ($processId) ($orderId) " . $localizedException->getMessage()]);
            $this->messageManager->addExceptionMessage($localizedException, $localizedException->getMessage());
        } catch (Exception $exception) {
            $this->logger->debug(['error' => "Exception: ($processId) ($orderId) " . $exception->getMessage()]);
            $this->messageManager->addExceptionMessage($exception, __('Unable to cancel Checkout'));
        }

        $this->_returnCustomerQuote(true);

        return $this->resultFactory
            ->create(ResultFactory::TYPE_REDIRECT)
            ->setPath('checkout/cart');
    }
}
