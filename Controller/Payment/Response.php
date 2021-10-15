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

use PayU\EasyPlus\Controller\AbstractAction;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;

class Response extends AbstractAction
{
    /**
     * Retrieve transaction information and validates payment
     *
     * @return \Magento\Framework\Controller\Result\Redirect|\Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {

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


            /** @var \Magento\Sales\Model\Order $order */
            $order = $orderId ? $this->_orderFactory->create()->load($orderId) : false;

            if($order->getState() == \Magento\Sales\Model\Order::STATE_PROCESSING) {
                $this->_logger->info("($process_id) ($orderId) PayU $process_string ALREADY SUCCESS (from IPN) -> Redirect User");
                return $this->sendSuccessPage($order);
            }

            if($payu && $order) {

                $this->response->setData('params', $payu);

                $result = $this->response->processReturn($order);

                if($result !== true) {
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
