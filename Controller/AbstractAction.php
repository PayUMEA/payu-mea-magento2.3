<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace PayU\EasyPlus\Controller;

use Magento\Checkout\Controller\Express\RedirectLoginInterface;
use Magento\Framework\App\Action\Action as AppAction;
use Magento\Framework\Exception\LocalizedException;
use PayU\EasyPlus\Model\CreditCard;
use PayU\EasyPlus\Model\DiscoveryMiles;
use PayU\EasyPlus\Model\Ebucks;
use PayU\EasyPlus\Model\EFTPro;
use PayU\EasyPlus\Model\Mobicred;
use PayU\EasyPlus\Model\Ucount;
use PayU\EasyPlus\Model\Rcs;
use PayU\EasyPlus\Model\RcsPlc;
use PayU\EasyPlus\Model\Fasta;
use PayU\EasyPlus\Model\Mpesa;
use PayU\EasyPlus\Model\AirtelMoney;
use PayU\EasyPlus\Model\MobileBanking;
use PayU\EasyPlus\Model\MtnMobile;
use PayU\EasyPlus\Model\Tigopesa;
use PayU\EasyPlus\Model\Equitel;

/**
 * Abstract Checkout Controller
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractAction extends AppAction implements RedirectLoginInterface
{
    protected $configTypes = [
        CreditCard::CODE => 'PayU\EasyPlus\Model\PayUConfigProvider',
        Ebucks::CODE => 'PayU\EasyPlus\Model\PayUConfigProvider',
        EFTPro::CODE => 'PayU\EasyPlus\Model\PayUConfigProvider',
        DiscoveryMiles::CODE => 'PayU\EasyPlus\Model\PayUConfigProvider',
        Mobicred::CODE => 'PayU\EasyPlus\Model\PayUConfigProvider',
        Ucount::CODE => 'PayU\EasyPlus\Model\PayUConfigProvider',
        Rcs::CODE => 'PayU\EasyPlus\Model\PayUConfigProvider',
        RcsPlc::CODE => 'PayU\EasyPlus\Model\PayUConfigProvider',
        Fasta::CODE => 'PayU\EasyPlus\Model\PayUConfigProvider',
        Mpesa::CODE => 'PayU\EasyPlus\Model\PayUConfigProvider',
        AirtelMoney::CODE => 'PayU\EasyPlus\Model\PayUConfigProvider',
        MobileBanking::CODE => 'PayU\EasyPlus\Model\PayUConfigProvider',
        MtnMobile::CODE => 'PayU\EasyPlus\Model\PayUConfigProvider',
        Tigopesa::CODE => 'PayU\EasyPlus\Model\PayUConfigProvider',
        Equitel::CODE => 'PayU\EasyPlus\Model\PayUConfigProvider'
    ];

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
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Quote\Model\QuoteManagement
     */
    protected $_quoteManagement;

    /**
     * @var \Magento\Framework\Session\Generic
     */
    protected $_payuSession;

    /**
     * @var \Magento\Framework\Url\Helper\Data
     */
    protected $_urlHelper;

    /**
     * @var \Magento\Customer\Model\Url
     */
    protected $_customerUrl;

    /**
     * @var \PayU\EasyPlus\Model\Api\Api
     */
    protected $_api;

    /**
     * @var \PayU\EasyPlus\Model\Error\Code
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


    /** @var \Magento\Framework\App\Config\ScopeConfigInterface  */
    protected $_scopeConfig;

    /**
     * AbstractAction constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Framework\Session\Generic $payuSession
     * @param \Magento\Framework\Url\Helper\Data $urlHelper
     * @param \Magento\Customer\Model\Url $customerUrl
     * @param \Magento\Quote\Model\QuoteManagement $quoteManagement
     * @param \PayU\EasyPlus\Model\Error\Code $errorCodes
     * @param \PayU\EasyPlus\Model\Response\Factory $responseFactory
     * @param \Magento\Payment\Model\Method\Logger $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Session\Generic $payuSession,
        \Magento\Framework\Url\Helper\Data $urlHelper,
        \Magento\Customer\Model\Url $customerUrl,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \PayU\EasyPlus\Model\Error\Code $errorCodes,
        \PayU\EasyPlus\Model\Response\Factory $responseFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
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
        $this->_logger = $logger;

    }

    /**
     * Instantiate quote and checkout
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _initCheckout()
    {
        $quote = $this->_getQuote();
        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->getResponse()->setStatusHeader(403, '1.1', 'Forbidden');
            throw new \Magento\Framework\Exception\LocalizedException(__('We can\'t initialize Checkout.'));
        }
    }

    /**
     * Search for proper checkout reference in request or session or (un)set specified one
     * Combined getter/setter
     *
     * @param string|null $reference
     * @return $this|string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _initPayUReference($reference = null)
    {
        if (null !== $reference) {
            if (false === $reference) {
                // security measure for avoid unsetting reference twice
                if (!$this->_getSession()->getCheckoutReference()) {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('PayU Checkout Reference does not exist.')
                    );
                }
                $this->_getSession()->unsCheckoutReference();
            } else {
                $this->_getSession()->setCheckoutReference($reference);
            }
            return $this;
        }
        $reference = $this->getRequest()->getParam('PayUReference') ?:
            $this->getRequest()->getParam('payUReference');

        if ($reference) {
            if ($reference !== $this->_getSession()->getCheckoutReference()) {
                throw new \Magento\Framework\Exception\LocalizedException(
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
     * @return \Magento\Framework\Session\Generic
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


    protected function sendPendingPage(\Magento\Sales\Model\Order $order)
    {
        $this->_getCheckoutSession()
            ->setLastQuoteId($order->getQuoteId())
            ->setLastSuccessQuoteId($order->getQuoteId());

        $this->_getCheckoutSession()
            ->setLastOrderId($order->getId())
            ->setLastRealOrderId($order->getIncrementId())
            ->setLastOrderStatus($order->getStatus());

        $this->messageManager->addSuccessMessage(
            __('Your order was placed ann will be processed once payment is validated.')
        );

        $this->clearSessionData();

        return $this->_redirect('checkout/onepage/success');
    }

    protected function sendSuccessPage(\Magento\Sales\Model\Order $order)
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
     * @param string $errorMsg
     * @return void
     */
    protected function _returnCustomerQuote($cancelOrder = false, $errorMsg = '')
    {
        $incrementId = $this->_getCheckoutSession()->getLastRealOrderId();
        if ($incrementId) {
            /* @var $order \Magento\Sales\Model\Order */
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
                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                } catch (LocalizedException $localizedException){
                } catch (\Exception $exception) {
                }
            }
        }
    }
}
