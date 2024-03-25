<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace PayU\EasyPlus\Controller;

use Exception;
use Magento\Checkout\Controller\Express\RedirectLoginInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Url;
use Magento\Framework\App\Action\Action as AppAction;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\Generic;
use Magento\Framework\Url\Helper\Data;
use Magento\Payment\Model\Method\Logger;
use Magento\Paypal\Model\Express\Checkout;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;
use PayU\EasyPlus\Model\Error\Code;
use PayU\EasyPlus\Model\PayUConfigProvider;
use PayU\EasyPlus\Model\Processor\Response as ResponseProcessor;
use PayU\EasyPlus\Model\Response;
use PayU\EasyPlus\Model\Response\Factory;

/**
 * Abstract Checkout Controller
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractAction extends AppAction implements RedirectLoginInterface
{
    /**
     * @var Checkout
     */
    protected Checkout $_checkout;

    /**
     * @var PayUConfigProvider
     */
    protected PayUConfigProvider $_config;

    /**
     * @var Quote
     */
    protected Quote $_quote;

    /**
     * @var string
     */
    protected string $_configType;

    /**
     * @var string
     */
    protected string $_configMethod;

    /**
     * @var Session
     */
    protected Session $_customerSession;

    /**
     * @var CheckoutSession
     */
    protected CheckoutSession $_checkoutSession;

    /**
     * @var OrderFactory
     */
    protected OrderFactory $_orderFactory;

    /**
     * @var QuoteManagement
     */
    protected QuoteManagement $_quoteManagement;

    /**
     * @var Generic
     */
    protected Generic $_payuSession;

    /**
     * @var Data
     */
    protected Data $_urlHelper;

    /**
     * @var Url
     */
    protected Url $_customerUrl;

    /**
     * @var Code
     */
    protected Code $_errorCodes;

    /**
     * @var Response
     */
    protected Response $response;

    /**
     * @var Logger
     */
    protected Logger $_logger;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $_scopeConfig;

    /** @var Logger */
    protected Logger $logger;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $_storeManager;

    /**
     * @var CartRepositoryInterface
     */
    protected CartRepositoryInterface $_quoteRepository;

    /**
     * @var OrderRepositoryInterface
     */
    protected OrderRepositoryInterface $_orderRepository;

    /**
     * @var ResponseProcessor
     */
    protected ResponseProcessor $responseProcessor;

    /**
     * AbstractAction constructor.
     * @param Context $context
     * @param Session $customerSession
     * @param CheckoutSession $checkoutSession
     * @param OrderFactory $orderFactory
     * @param Generic $payuSession
     * @param Data $urlHelper
     * @param Url $customerUrl
     * @param QuoteManagement $quoteManagement
     * @param Code $errorCodes
     * @param Factory $responseFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     * @param CartRepositoryInterface $quoteRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param ResponseProcessor $responseProcessor
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        CheckoutSession $checkoutSession,
        OrderFactory $orderFactory,
        Generic $payuSession,
        Data $urlHelper,
        Url $customerUrl,
        QuoteManagement $quoteManagement,
        Code $errorCodes,
        Factory $responseFactory,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        StoreManagerInterface $storeManager,
        CartRepositoryInterface $quoteRepository,
        OrderRepositoryInterface $orderRepository,
        ResponseProcessor $responseProcessor
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
        $this->_storeManager = $storeManager;
        $this->_quoteRepository = $quoteRepository;
        $this->_orderRepository = $orderRepository;
        $this->responseProcessor = $responseProcessor;

        parent::__construct($context);

        $this->logger = $logger;
        $this->response = $responseFactory->create();
    }

    /**
     * Search for proper checkout reference in request or session or (un)set specified one
     * Combined getter/setter
     *
     * @return $this|string
     * @throws LocalizedException
     */
    protected function getPayUReference()
    {
        $reference = $this->getRequest()->getParam('PayUReference') ?:
            $this->getRequest()->getParam('payUReference');

        if ($reference) {
            $payUReference = $this->_getSession()->getCheckoutReference() ??
                $this->_getSession()->getData('checkout_reference');

            if ($payUReference && $reference !== $payUReference) {
                $this->logger->debug([
                    'info' => "PayU reference from request parameter: {$reference}, PayU reference in Magento session: "
                        . $payUReference
                ]);
                throw new LocalizedException(
                    __('A wrong PayU Checkout Reference was specified.')
                );
            }
        } else {
            $reference = $this->_getSession()->getCheckoutReference() ??
                $this->_getSession()->getData('checkout_reference');
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
     * @return Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function _getQuote(): Quote
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
        return null;
    }

    /**
     * Returns a list of action flags [flag_key] => boolean
     * @return array
     */
    public function getActionFlagList(): array
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

    protected function clearSessionData()
    {
        $this->_getSession()->unsQuoteId();
        $this->_getSession()->unsCheckoutReference();
        $this->_getSession()->unsCheckoutRedirectUrl();
        $this->_getSession()->unsCheckoutOrderIncrementId();
    }

    /**
     * @return ResponseInterface
     */
    protected function sendPendingPage(): ResponseInterface
    {
        $this->messageManager->addNoticeMessage(
            __('Your order was placed and will be processed once payment is confirmed.')
        );

        $this->clearSessionData();

        return $this->_redirect('checkout/onepage/success');
    }

    /**
     * @return ResponseInterface
     */
    protected function sendSuccessPage(): ResponseInterface
    {
        $this->messageManager->addSuccessMessage(
            __('Payment was successful and we received your order with much fanfare')
        );

        $this->clearSessionData();

        return $this->_redirect('checkout/onepage/success');
    }

    /**
     * @param string|null $message
     * @return ResponseInterface
     */
    protected function sendFailedPage(?string $message = null): ResponseInterface
    {
        $this->messageManager->addErrorMessage(
            __($message ?? 'Payment was unsuccessful')
        );

        $this->clearSessionData();

        return $this->_redirect('checkout/onepage/failure');
    }

    /**
     * Return customer quote
     *
     * @param bool $cancelOrder
     * @return void
     */
    protected function _returnCustomerQuote(bool $cancelOrder = false)
    {
        $incrementId = $this->_getCheckoutSession()->getLastRealOrderId() ??
            $this->_getCheckoutSession()->getData('last_real_order_id');
        $quoteId = $this->_getCheckoutSession()->getLastSuccessQuoteId() ??
            $this->_getCheckoutSession()->getData('last_success_quote_id');

        $order = $incrementId ? $this->_orderFactory->create()->loadByIncrementId($incrementId) : null;

        if ($order &&
            $order->getId() &&
            $order->getQuoteId() == $quoteId
        ) {
            try {
                /** @var Quote $quote */
                $quote = $this->_quoteRepository->get($order->getQuoteId());
                $quote->setIsActive(true)->setReservedOrderId(null);
                $this->_quoteRepository->save($quote);
                $this->_getCheckoutSession()->replaceQuote($quote);

                $this->clearSessionData();

                if ($cancelOrder) {
                    $order->cancel();
                    $this->_orderRepository->save($order);
                }
            } catch (NoSuchEntityException $exception) {
                $this->logger->debug(
                    [
                            'error' => ['message' => 'NoSuchEntityException: ' . $exception->getMessage()]
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
