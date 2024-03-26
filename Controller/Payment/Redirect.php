<?php
/**
 *
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace PayU\EasyPlus\Controller\Payment;

use Exception;
use Magento\Framework\Controller\ResultFactory;
use PayU\EasyPlus\Controller\AbstractAction;

class Redirect extends AbstractAction
{
    /**
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $message = 'Unable to redirect to PayU.';

        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $url = $this->_getSession()->getData('checkout_redirect_url', true);

            if ($url) {
                return $resultRedirect->setPath($url);
            } else {
                $this->messageManager->addErrorMessage(
                    __('No redirect url. Unable to redirect to PayU.')
                );
            }
        } catch (Exception $exception) {
            $this->logger->debug(['error' => "Exception: " . $exception->getMessage()]);
            $this->messageManager->addExceptionMessage(
                $exception,
                __($message)
            );
        }

        $this->_returnCustomerQuote();

        return $resultRedirect->setPath('checkout/cart');
    }
}
