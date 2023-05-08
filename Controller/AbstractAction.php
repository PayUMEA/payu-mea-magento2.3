<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace PayU\EasyPlus\Controller;

use Exception;
use Magento\Checkout\Controller\Express\RedirectLoginInterface;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Url;
use Magento\Framework\App\Action\Action as AppAction;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Framework\Session\Generic;
use Magento\Framework\Url\Helper\Data;
use Magento\Payment\Model\Method\Logger;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use PayU\EasyPlus\Model\Error\Code;
use PayU\EasyPlus\Model\Response\Factory;

/**
 * Abstract Checkout Controller
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractAction extends AppAction implements RedirectLoginInterface
{
    /**
     * @var \Magento\Paypal\Model\Express\Checkout
     */
    protected $_checkout;

    /**
     * @var \PayU\EasyPlus\Model\PayUConfigProvider
     */
    protected $_config;

    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $_quote = false;

    /**
     * Config provider class
     *
     * @var string
     */
    protected $_configType;

    /**
     * Config method code
     *
     * @var string
     */
    protected $_configMethod;

    /**
     * @var Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var QuoteManagement
     */
    protected $_quoteManagement;

    /**
     * @var Generic
     */
    protected $_payuSession;

    /**
     * @var Data
     */
    protected $_urlHelper;

    /**
     * @var Url
     */
    protected $_customerUrl;

    /**
     * @var Code
     */
    protected $_errorCodes;

    /**
     * @var \PayU\EasyPlus\Model\Response
     */
    protected $response;
    /**
     * @var \Magento\Payment\Model\Method\Logger
     */
    protected $_logger;

    /** @var ScopeConfigInterface */
    protected $_scopeConfig;

    /** @var Logger */
    protected $logger;

    /**
     * AbstractAction constructor.
     * @param Context $context
     * @param Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param OrderFactory $orderFactory
     * @param Generic $payuSession
     * @param Data $urlHelper
     * @param Url $customerUrl
     * @param QuoteManagement $quoteManagement
     * @param Code $errorCodes
     * @param Factory $responseFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        OrderFactory $orderFactory,
        Generic $payuSession,
        Data $urlHelper,
        Url $customerUrl,
        QuoteManagement $quoteManagement,
        Code $errorCodes,
        Factory $responseFactory,
        ScopeConfigInterface $scopeConfig,
        Logger $logger
    ) {
        $this->_customerSession = $customerSession;
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        $this->_quoteManagement = $quoteManagement;
        $this->_payuSession = $payuSession;
        $this->_urlHelper = $urlHelper;
        $this->_customerUrl = $customerUrl;
        $this->_errorCodes = $errorCodes;
        $this->_scopeConfig = $scopeConfig;

        parent::__construct($context);

        $this->response = $responseFactory->create();
        $this->logger = $logger;
    }

    /**
     * Instantiate quote and checkout
     *
     * @return void
     * @throws LocalizedException
     */
    protected function _initCheckout()
    {
        $quote = $this->_getQuote();

        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->getResponse()->setStatusHeader(403, '1.1', 'Forbidden');

            throw new LocalizedException(__('We can\'t initialize Checkout.'));
        }
    }

    /**
     * Search for proper checkout reference in request or session or (un)set specified one
     * Combined getter/setter
     *
     * @param string|null $reference
     * @return $this|string
     * @throws LocalizedException
     */
    protected function _initPayUReference($reference = null)
    {
        if (null !== $reference) {
            if (false === $reference) {
                // security measure for avoid unsetting reference twice
                if (!$this->_getSession()->getCheckoutReference()) {
                    $this->_getSession()->unsCheckoutReference();

                    throw new LocalizedException(
                        __('PayU Checkout Reference does not exist.')
                    );
                }
            } else {
                $this->_getSession()->setCheckoutReference($reference);
            }

            return $this;
        }

        $reference = $this->getRequest()->getParam('PayUReference') ?:
            $this->getRequest()->getParam('payUReference');

        if ($reference) {
            if ($reference !== $this->_getSession()->getCheckoutReference()) {
                $this->logger->debug([
                    'info' => "PayU reference from request parameter: {$reference}, PayU reference in Magento session: " . $this->_getSession()->getCheckoutReference()
                ]);
                throw new LocalizedException(
                    __('A wrong PayU Checkout Reference was specified.')
                );
            }
        } else {
            $reference = $this->_getSession()->getCheckoutReference();
        }

        return $reference;
    }

    /**
     * PayU session instance getter
     *
     * @return Generic
     */
    protected function _getSession()
    {
        return $this->_payuSession;
    }

    /**
     * Return checkout session object
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    /**
     * Return checkout quote object
     *
     * @return \Magento\Quote\Model\Quote
     */
    protected function _getQuote()
    {
        if (!$this->_quote) {
            $this->_quote = $this->_checkoutSession->getQuote();
        }
        return $this->_quote;
    }

    /**
     * Returns before_auth_url redirect parameter for customer session
     * @return null
     */
    public function getCustomerBeforeAuthUrl()
    {
        return;
    }

    /**
     * Returns a list of action flags [flag_key] => boolean
     * @return array
     */
    public function getActionFlagList()
    {
        return [];
    }

    /**
     * Returns login url parameter for redirect
     * @return string
     */
    public function getLoginUrl()
    {
        return $this->_customerUrl->getLoginUrl();
    }

    /**
     * Returns action name which requires redirect
     * @return string
     */
    public function getRedirectActionName()
    {
        return 'redirect';
    }

    /**
     * Redirect to login page
     *
     * @return void
     */
    public function redirectLogin()
    {
        $this->_actionFlag->set('', 'no-dispatch', true);
        $this->_customerSession->setBeforeAuthUrl($this->_redirect->getRefererUrl());
        $this->getResponse()->setRedirect(
            $this->_urlHelper->addRequestParam($this->_customerUrl->getLoginUrl(), ['context' => 'checkout'])
        );
    }

    protected function clearSessionData()
    {
        $this->_getSession()->unsCheckoutReference();
        $this->_getSession()->unsCheckoutRedirectUrl();
        $this->_getSession()->unsCheckoutOrderIncrementId();
    }

    protected function sendPendingPage(Order $order)
    {
        $this->_getCheckoutSession()
            ->setLastQuoteId($order->getQuoteId())
            ->setLastSuccessQuoteId($order->getQuoteId());

        $this->_getCheckoutSession()
            ->setLastOrderId($order->getId())
            ->setLastRealOrderId($order->getIncrementId())
            ->setLastOrderStatus($order->getStatus());

        $this->messageManager->addSuccessMessage(
            __('Your order was placed and will be processed once payment is confirmed.')
        );

        $this->clearSessionData();

        return $this->_redirect('checkout/onepage/success');
    }

    protected function sendSuccessPage(Order $order)
    {
        $this->_getCheckoutSession()
            ->setLastQuoteId($order->getQuoteId())
            ->setLastSuccessQuoteId($order->getQuoteId());

        $this->_getCheckoutSession()
            ->setLastOrderId($order->getId())
            ->setLastRealOrderId($order->getIncrementId())
            ->setLastOrderStatus($order->getStatus());

        $this->messageManager->addSuccessMessage(
            __('Payment was successful and we received your order with much fanfare')
        );

        $this->clearSessionData();

        return $this->_redirect('checkout/onepage/success');
    }

    /**
     * Return customer quote
     *
     * @param bool $cancelOrder
     * @param Phrase|null $errorMsg
     * @return void
     */
    protected function _returnCustomerQuote(bool $cancelOrder = false, ?Phrase $errorMsg = null)
    {
        $incrementId = $this->_getCheckoutSession()->getLastRealOrderId();

        if ($incrementId) {
            /* @var $order Order */
            $order = $this->_objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($incrementId);
            if ($order->getId()) {
                try {
                    /** @var \Magento\Quote\Api\CartRepositoryInterface $quoteRepository */
                    $quoteRepository = $this->_objectManager->create('Magento\Quote\Api\CartRepositoryInterface');
                    /** @var \Magento\Quote\Model\Quote $quote */
                    $quote = $quoteRepository->get($order->getQuoteId());

                    $quote->setIsActive(true)->setReservedOrderId(null);
                    $quoteRepository->save($quote);
                    $this->_getCheckoutSession()->replaceQuote($quote);

                    $this->_getSession()->unsCheckoutOrderIncrementId($incrementId);
                    $this->_getSession()->unsetData('quote_id');

                    $this->clearSessionData();

                    if ($cancelOrder) {
                        $order->registerCancellation($errorMsg)->save();
                    }
                } catch (NoSuchEntityException $exception) {
                    $this->logger->debug(
                        [
                            'error' => ['message' => 'NoSuchEntityException: ' . $exception->getMessage()]
                        ]
                    );
                } catch (LocalizedException $localizedException) {
                    $this->logger->debug(
                        [
                            'error' => ['message' => 'LocalizedException: ' . $localizedException->getMessage()]
                        ]
                    );
                } catch (Exception $exception) {
                    $this->logger->debug(
                        [
                            'error' => ['message' => 'Exception: ' . $exception->getMessage()]
                        ]
                    );
                }
            }
        }
    }

    /**
     * @param string $httpCode
     * @param null $text
     */
    protected function respond(string $httpCode = '200', $text = null)
    {
        if ($httpCode === '200') {
            if (is_callable('fastcgi_finish_request')) {
                if ($text !== null) {
                    echo $text;
                }

                session_write_close();
                fastcgi_finish_request();

                return;
            }
        }

        ignore_user_abort(true);
        ob_start();

        if ($text !== null) {
            echo $text;
        }

        $serverProtocol = filter_input(INPUT_SERVER, 'SERVER_PROTOCOL', FILTER_SANITIZE_STRING);
        header($serverProtocol . " {$httpCode} OK");
        header('Content-Encoding: none');
        header('Content-Length: ' . ob_get_length());
        header('Connection: close');

        ob_end_flush();
        ob_flush();
        flush();
    }
}
