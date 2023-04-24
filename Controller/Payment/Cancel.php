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
        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $orderId = '';
        $processId = uniqid();
        $processString = self::class;

        $this->logger->debug(['info' => "($processId) START $processString"]);

        try {
            $payu = $this->_initPayUReference();

            // if there is an order - cancel it
            $orderId = $this->_getCheckoutSession()->getLastOrderId();

            /** @var \Magento\Sales\Model\Order $order */
            $order = $orderId ? $this->_orderFactory->create()->load($orderId) : false;

            if ($payu &&
                $order &&
                $order->getQuoteId() == $this->_getCheckoutSession()->getLastSuccessQuoteId()
            ) {
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
        } catch (LocalizedException $localizedException) {
            $this->logger->debug(['error' => "Exception: ($processId) ($orderId) " . $localizedException->getMessage()]);
            $this->messageManager->addExceptionMessage($localizedException, $localizedException->getMessage());
        } catch (Exception $exception) {
            $this->logger->debug(['error' => "Exception: ($processId) ($orderId) " . $exception->getMessage()]);
            $this->messageManager->addExceptionMessage($exception, __('Unable to cancel Checkout'));
        }

        $this->_returnCustomerQuote(true);

        return $resultRedirect->setPath('checkout/cart');
    }
}
