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

use PayU\EasyPlus\Controller\AbstractAction;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;

class Cancel extends AbstractAction
{
    /**
     * Cancel Express Checkout
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $payu = $this->_initPayUReference();
          
            // if there is an order - cancel it
            $orderId = $this->_getCheckoutSession()->getLastOrderId();

            /** @var \Magento\Sales\Model\Order $order */
            $order = $orderId ? $this->_orderFactory->create()->load($orderId) : false;
            if ($payu && $order
                && $order->getQuoteId() == $this->_getCheckoutSession()->getLastSuccessQuoteId())
            {
                $this->response->setData('params', $payu);

                $this->response->processCancel($order);

                $this->messageManager->addErrorMessage(
                    __('Payment transaction unsuccessful. User canceled payment transaction.')
                );
            } else {
                $this->messageManager->addErrorMessage(
                    __('Payment unsuccessful. Failed to reload cart.')
                );
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Unable to cancel Checkout'));
        }

        $this->_returnCustomerQuote(true);

        return $resultRedirect->setPath('checkout/cart');
    }
}