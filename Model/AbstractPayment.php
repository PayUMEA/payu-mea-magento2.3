<?php
/**
 * PayU_EasyPlus payment method model
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Session\Generic;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PayU\EasyPlus\Helper\Data as FrontendHelper;
use PayU\EasyPlus\Helper\DataFactory;
use PayU\EasyPlus\Model\Api\Api;
use PayU\EasyPlus\Model\Api\Factory;

/**
 * Redirect payment method model for all payment methods except Discovery Miles
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractPayment extends AbstractPayU
{
    const CODE = '';

    /**
     * How long to wait for transaction to become unlocked
     */
    private const LOCK_TIMEOUT = 5;

    /**
     * Static lock prefix for locking
     */
    private const LOCK_PREFIX = 'PAYU_TXN_';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isGateway = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canOrder = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseCheckout = true;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canUseInternal = false;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isInitializeNeeded = false;

    protected $_isOffline = false;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_canFetchTransactionInfo = true;

    protected $_easyPlusApi                 = false;
    protected $_dataFactory                 = false;
    protected $_requestFactory              = false;
    protected $_responseFactory             = false;
    protected $_storeManager                = false;
    protected $_checkoutSession             = false;
    protected $_session                     = false;
    protected $_response                    = null;
    protected $_paymentData                 = false;
    protected $_minAmount                   = null;
    protected $_maxAmount                   = null;
    protected $_supportedCurrencyCodes      = [ 'NGN', 'ZAR', 'KES', 'TZS', 'ZMW', 'USD'];

    /**
     * Fields that should be replaced in debug with '***'
     *
     * @var array
     */
    protected $_debugReplacePrivateDataKeys = ['Safekey'];

    /**
     * @var TransactionRepositoryInterface
     */
    protected $transactionRepository;

    protected $orderFactory;
    protected $quoteRepository;
    protected $orderSender;
    protected $invoiceSender;
    protected $_encryptor;

    /**
     * @var InvoiceService
     */
    protected $_invoiceService;

    /**
     * @var OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var BuilderInterface
     */
    protected $transactionBuilder;

    /**
     * @var Config
     */
    protected $orderConfig;

    /**
     * @var InvoiceRepositoryInterface
     */
    protected $_invoiceRepository;

    /**
     * @var LockManagerInterface
     */
    protected LockManagerInterface $lockManager;

    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        StoreManagerInterface $storeManager,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        Logger $logger,
        Session $checkoutSession,
        Generic $session,
        TransactionRepositoryInterface $transactionRepository,
        BuilderInterface $transactionBuilder,
        InvoiceService $invoiceService,
        Factory $apiFactory,
        DataFactory $dataFactory,
        Request\Factory $requestFactory,
        Response\Factory $responseFactory,
        OrderFactory $orderFactory,
        CartRepositoryInterface $quoteRepository,
        OrderSender $orderSender,
        OrderRepositoryInterface $orderRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        InvoiceSender $invoiceSender,
        Config $orderConfig,
        LockManagerInterface $lockManager,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            null,
            null,
            $data
        );

        $this->_encryptor = $encryptor;
        $this->_dataFactory = $dataFactory;
        $this->_requestFactory = $requestFactory;
        $this->_responseFactory = $responseFactory;
        $this->_storeManager = $storeManager;
        $this->_checkoutSession = $checkoutSession;
        $this->transactionRepository = $transactionRepository;
        $this->transactionBuilder = $transactionBuilder;
        $this->_invoiceService = $invoiceService;
        $this->_orderRepository = $orderRepository;
        $this->_easyPlusApi = $apiFactory->create();
        $this->_session = $session;
        $this->_paymentData = $paymentData;
        $this->orderFactory = $orderFactory;
        $this->quoteRepository = $quoteRepository;
        $this->orderSender = $orderSender;
        $this->invoiceSender = $invoiceSender;
        $this->_invoiceRepository = $invoiceRepository;
        $this->orderConfig = $orderConfig;
        $this->lockManager = $lockManager;

        $this->initializeApi();

        $this->_minAmount = $this->getValue('min_order_total');
        $this->_maxAmount = $this->getValue('max_order_total');
    }

    /**
     * Store setter
     *
     * @param Store|int $store
     * @return $this
     * @throws NoSuchEntityException
     */
    public function setStore($store)
    {
        $this->setData('store', $store);

        if (null === $store) {
            $store = $this->_storeManager->getStore()->getId();
            $this->setData('store', $store);
        }

        return $this;
    }

    /**
     * Get api
     *
     * @return Api
     */
    public function getApi()
    {
        return $this->_easyPlusApi;
    }

    /**
     * Get api
     *
     * @return Response
     */
    public function getResponse()
    {
        if (null === $this->_response) {
            $this->_response = $this->_responseFactory->create();
        }

        return $this->_response;
    }

    /**
     * Fill response with data.
     *
     * @param array $postData
     * @return $this
     */
    public function setResponseData($postData)
    {
        $this->getResponse()->setData('return', $postData);

        return $this;
    }

    /**
     * Getter for specified value according to set payment method code
     *
     * @param mixed $key
     * @param null $storeId
     * @return mixed
     */
    public function getValue($key, $storeId = null)
    {
        if (in_array($key, ['safe_key', 'api_password'])) {
            return $this->_encryptor->decrypt($this->getConfigData($key, $storeId));
        }

        return $this->getConfigData($key, $storeId);
    }

    /**
     * Define if debugging is enabled
     *
     * @return bool
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     * @api
     * @deprecated 100.2.0
     */
    public function getDebugFlag()
    {
        return (bool)(int)$this->getConfigData('debug');
    }

    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            return false;
        }

        return parent::canUseForCurrency($currencyCode);
    }

    /**
     * Check response code came from PayU.
     *
     * @return void
     * @throws LocalizedException In  of Declined or Error response from Authorize.net
     */
    public function checkTransactionState(): void
    {
        $state = $this->getResponse()->getTransactionState();

        switch ($state) {
            case self::TRANS_STATE_SUCCESSFUL:
            case self::TRANS_STATE_PROCESSING:
            case self::TRANS_STATE_AWAITING_PAYMENT:
                break;
            case self::TRANS_STATE_FAILED:
            case self::TRANS_STATE_EXPIRED:
            case self::TRANS_STATE_TIMEOUT:
                throw new LocalizedException(
                    $this->_dataFactory->create('frontend')
                        ->wrapGatewayError($this->getResponse()->getResultMessage())
                );
            default:
                throw new LocalizedException(
                    __('There was a payment verification error.')
                );
        }
    }

    /**
     * Check transaction id came with response data
     * @throws LocalizedException In case of bad transaction id.
     */
    public function checkTransId()
    {
        if (!$this->getResponse()->getTranxId()) {
            throw new LocalizedException(
                __('Payment verification error: invalid PayU reference')
            );
        }
    }

    /**
     * Compare amount with amount from the response from PayU.
     *
     * @param float $amount
     * @return bool
     */
    protected function matchAmount($amount)
    {
        $amountPaid = $this->getResponse()->getTotalCaptured();

        return sprintf('%.2F', $amount) == sprintf('%.2F', $amountPaid);
    }

    /**
     * Initialize PayU API credentials
     */
    protected function initializeApi()
    {
        $this->_easyPlusApi->setSafeKey($this->getValue('safe_key'));
        $this->_easyPlusApi->setUsername($this->getValue('api_username'));
        $this->_easyPlusApi->setPassword($this->getValue('api_password'));
        $this->_easyPlusApi->setMethodCode(static::CODE);
    }

    /**
     * Order payment
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws LocalizedException
     */
    public function order(InfoInterface $payment, $amount)
    {
        $this->_setupTransaction($payment, $amount);

        return $this;
    }

    /**
     * Setup transaction before redirect
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws LocalizedException
     */
    protected function _setupTransaction(InfoInterface $payment, $amount)
    {
        $response = null;
        $this->validateAmount($amount);

        /** @var Order $order */
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        $helper = $this->_dataFactory->create('frontend');
        $request = $this->generateRequestFromOrder($order, $helper);
        $payUReference = 'N/A';

        try {
            $response = $this->_easyPlusApi->doSetTransaction($request->getData());

            $this->debugData(['info' => 'SetTransaction']);
            $this->debugData(['request' => $request->getData()]);
            $this->debugData(['response' => $response]);

            if ($response->return->successful) {
                $payUReference = $response->return->payUReference;

                // Set PayU session variables
                $this->_session->setCheckoutReference($payUReference);
                $this->_session->setCheckoutOrderIncrementId($order->getIncrementId());
                $this->_easyPlusApi->setPayUReference($payUReference);
                $this->_session->setCheckoutRedirectUrl($this->_easyPlusApi->getRedirectUrl());

                $message = 'Amount of %1 is pending approval. Redirecting to PayU.<br/>'
                    . 'PayU reference "%2"<br/>';
                $message = __(
                    $message,
                    $order->getBaseCurrency()->formatTxt($amount),
                    $payUReference
                );

                $order->setState(Order::STATE_PENDING_PAYMENT)
                    ->setStatus(Order::STATE_PENDING_PAYMENT)
                    ->setCustomerNoteNotify(false)
                    ->addCommentToStatusHistory($message);

                $payment->setSkipOrderProcessing(true);
                $payment->setAdditionalInformation('payUReference', $payUReference);
            } else {
                throw new LocalizedException(
                    __('SetupTransaction was not successful, error encountered. Reference: '
                        . $payUReference . '. Result code: ' . $response->return->resultCode
                        . '. Message: ' . $response->return->resultMessage)
                );
            }
        } catch (Exception $e) {
            $this->debugData([
                'error' => $e->getMessage(),
                'response' => $response
            ]);

            $this->clearSessionData();

            throw new LocalizedException(__('Oops! Payment gateway encountered an error.'));
        }

        return $this;
    }

    /**
     * Process order cancellation
     *
     * @param $params
     * @throws LocalizedException
     * @throws Exception
     */
    public function processCancellation($params)
    {
        $response = $this->_easyPlusApi->doGetTransaction($params, $this);

        $payUReference = $response->getTranxId();

        //operate with order
        $orderIncrementId = $response->getInvoiceNum();

        $message = 'Payment transaction of amount of %1 was canceled by user on PayU.<br/>' . 'PayU reference: %2<br/>';

        $isError = false;

        if ($orderIncrementId) {
            /* @var $order Order */
            $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
            //check payment method
            $payment = $order->getPayment();
            if (!$payment || $payment->getMethod() != $this->getCode()) {
                throw new LocalizedException(
                    __("This payment didn't work out because we can't find this order.")
                );
            }
            if ($order->getId()) {
                //operate with order
                $message = __(
                    $message,
                    $order->getBaseCurrency()->formatTxt($order->getBaseTotalDue()),
                    $payUReference
                );

                $order->addCommentToStatusHistory($message);
                $this->_orderRepository->save($order);
            } else {
                $isError = true;
            }
        } else {
            $isError = true;
        }

        if ($isError) {
            $responseText = $this->_dataFactory->create('frontend')
                ->wrapGatewayError($response->getResultMessage());
            $responseText = $responseText
                ?: __("This payment didn't work out because we can't find this order.");

            throw new LocalizedException($responseText);
        }
    }

    /**
     * Process PayU IPN
     *
     * @param array $data
     * @param Order $order
     * @param string $processId
     * @param string $processClass
     * @return void
     * @throws LocalizedException
     */
    public function processNotification(Order $order, array $data, string $processId, string $processClass)
    {
        if ($order->getState() == strtolower(AbstractPayU::TRANS_STATE_PROCESSING) ||
            $order->getStatus() == strtolower(AbstractPayU::TRANS_STATE_PROCESSING)
        ) {
            $this->debugData(['info' => "IPN ($processId): Order already processed.", 'response' => $data]);

            return;
        }

        $payUReference = $data['PayUReference'];
        $response = $this->_easyPlusApi->doGetTransaction($payUReference, $this);
        $resultCode = $response->getResultCode();

        $transactionNotes = "<strong>-----PAYU NOTIFICATION RECEIVED---</strong><br />";
        //Checking the response from the SOAP call to see if IPN is valid
        if (isset($resultCode) && (!in_array($resultCode, ['POO5', 'EFTPRO_003', '999', '305']))) {
            if (isset($data['TransactionState'])
                && (
                    in_array(
                        $data['TransactionState'],
                        [
                            'PROCESSING',
                            'SUCCESSFUL',
                            'AWAITING_PAYMENT',
                            'FAILED',
                            'TIMEOUT',
                            'EXPIRED'
                        ]
                    )
                )
            ) {
                $amountPaid = 0.0;
                $paymentMethods = [];
                $amountDue = $response->getTotalDue() / 100;

                if ($response->hasPaymentMethod()) {
                    $paymentMethods = $data['PaymentMethodsUsed'];

                    if (!is_array($paymentMethods)) {
                        $paymentMethods = [$paymentMethods];
                    }

                    foreach ($paymentMethods as $paymentMethod) {
                        if (array_key_exists('AmountInCents', $paymentMethod)) {
                            $amountPaid += ($paymentMethod['AmountInCents'] / 100);
                        }
                    }
                }

                $transactionNotes .= "Order Amount: " . $amountDue . "<br />";
                $transactionNotes .= "Amount Paid: " . $amountPaid . "<br />";
                $transactionNotes .= "Merchant Reference : " . $response->getInvoiceNum() . "<br />";
                $transactionNotes .= "PayU Reference: " . $payUReference . "<br />";
                $transactionNotes .= "PayU Payment Status: " . $response->getTransactionState() . "<br /><br />";

                if (!empty($paymentMethods)) {
                    if (is_array($paymentMethods)) {
                        $transactionNotes .= "<strong>Payment Method Details:</strong>";
                        foreach ($paymentMethods as $type => $paymentMethod) {
                            $transactionNotes .= "<br />===" . $type . "===";
                            foreach ($paymentMethod as $key => $value) {
                                $transactionNotes .= "<br />&nbsp;&nbsp;=> " . $key . ": " . $value;
                            }
                            $transactionNotes .= '<br />';
                        }
                    }
                }

                $this->setResponseData($response->getReturn());

                switch ($data['TransactionState']) {
                    // Payment completed
                    case 'SUCCESSFUL':
                        $order->addCommentToStatusHistory($transactionNotes, 'processing');
                        $this->captureOrderAndPayment($order, $processId, $processClass);
                        break;
                    case 'PROCESSING':
                        $order->addCommentToStatusHistory($transactionNotes);
                        break;
                    case 'FAILED':
                    case 'TIMEOUT':
                    case 'EXPIRED':
                        $order->addCommentToStatusHistory($transactionNotes);
                        $order->cancel();
                        break;
                    default:
                        $order->addCommentToStatusHistory($transactionNotes, true);
                    break;
                }

                $this->_orderRepository->save($order);
                $this->debugData(['info' => "IPN ($processId): Processing complete."]);
            } else {
                $transactionNotes = '<strong>Payment unsuccessful: </strong><br />';
                $transactionNotes .= "PayU Reference: " . $response->getTranxId() . "<br />";
                $transactionNotes .= "Point Of Failure: " . $response->getPointOfFailure() . "<br />";
                $transactionNotes .= "Result Code: " . $response->getResultCode();
                $transactionNotes .= "Result Message: " . $response->getResultMessage();

                $order->addCommentToStatusHistory($transactionNotes);
                $order->cancel();
                $this->_orderRepository->save($order);
                $this->debugData(['info' => "IPN ($processId): Payment transaction failed. Payment status unknown"]);
            }
        } else {
            $transactionNotes = '<strong>Payment unsuccessful: </strong><br />';
            $transactionNotes .= "PayU Reference: " . $response->getTranxId() . "<br />";
            $transactionNotes .= "Point Of Failure: " . $response->getPointOfFailure() . "<br />";
            $transactionNotes .= "Result Code: " . $response->getResultCode();
            $transactionNotes .= "Result Message: " . $response->getResultMessage();

            $order->addCommentToStatusHistory($transactionNotes);
            $order->cancel();
            $this->_orderRepository->save($order);
            $this->debugData(['info' => "IPN ($processId): PayU payment transaction failed"]);
        }
    }

    /**
     * Operate with order using data from $_POST which came from PayU by Return URL.
     *
     * @param string $params PayU reference
     * @param string $processId
     * @param string $processClass
     * @return bool
     * @throws LocalizedException In case of validation error or order capture error
     * @throws Exception
     */
    public function process(string $params, string $processId, string $processClass): bool
    {
        $isError = false;
        $response = $this->_easyPlusApi->doGetTransaction($params, $this);
        $this->_response = $response;
        $incrementId = $response->getInvoiceNum();

        if ($incrementId) {
            $order = $this->orderFactory->create()->loadByIncrementIdAndStoreId(
                $incrementId,
                $this->_storeManager->getStore()->getId()
            );
            $payment = $order->getPayment();

            $this->debugData(['info' => "($processId) ($incrementId) $processClass: START."]);

            // check payment method
            if (!$payment || $payment->getMethod() != $this->getCode()) {
                return false;
            }

            if ($order->getId()) {
                try {
                    // Everything looks good, so capture order
                    $this->captureOrderAndPayment($order, $processId, $processClass);
                } catch (LocalizedException|Exception $exception) {
                    $isError = true;
                    $this->debug(['error' => $exception->getMessage()]);
                }
            } else {
                $isError = true;
            }
        } else {
            $isError = true;
        }

        if ($isError) {
            return false;
        }

        return true;
    }

    /**
     * Operate with order using information from PayU.
     * Capture order.
     *
     * @param Order $order
     * @param string $processId
     * @param string $processClass
     * @return void
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function captureOrderAndPayment(Order $order, string $processId, string $processClass)
    {
        $response = $this->getResponse();
        /* @var $payment InfoInterface| Payment */
        $payment = $order->getPayment();
        $this->_importToPayment($response, $payment);

        try {
            $this->checkTransId();
            $this->checkTransactionState();
        } catch (Exception $e) {
            $this->declineOrder($order, $response, true);
            throw $e;
        }

        $this->processPaymentFraudStatus($payment);
        $this->addStatusCommentOnUpdate($payment, $response);
        $this->_orderRepository->save($order);

        // Check for a Pending order request
        if ($payment->getIsTransactionPending()) {
            $this->_orderRepository->save($order);

            return;
        }

        // Check for a Processing order status
        if ($payment->getIsTransactionProcessing()) {
            $this->_orderRepository->save($order);

            return;
        }

        // Should be equal for capturing order.
        // Decline the order if amount does not match.
        if (!$this->matchAmount($payment->getBaseAmountOrdered())) {
            $message = __(
                'Something went wrong: the paid amount does not match the order amount.'
                . ' Please correct this and try again.'
            );
            $this->declineOrder($order, $response, true);
            throw new LocalizedException($message);
        }

        $this->invoiceAndNotifyCustomer($order, $processId, $processClass);
    }

    /**
     * Generate invoice and notify customer
     *
     * @param Order $order
     * @param $processId
     * @param $processClass
     * @throws LocalizedException
     */
    protected function invoiceAndNotifyCustomer(Order $order, $processId, $processClass)
    {
        $id = $order->getIncrementId();

        if (!$this->lockManager->lock(self::LOCK_PREFIX . $id, self::LOCK_TIMEOUT)) {
            $message = "($processId) ($id) $processClass: could not acquire lock for invoice txn, skipping.";
            $this->debugData(['info' => $message]);

            goto cannot_invoice_marker;
        }

        try {
            $status = $this->orderConfig->getStateDefaultStatus('processing');
            $order->setState("processing")->setStatus($status);
            $this->_orderRepository->save($order);

            $order->setCanSendNewEmailFlag(true);
            $this->orderSender->send($order);

            $this->debugData(
                [
                    'info' => " ($processId) ($id) $processClass: can_invoice (initial check): " . $order->canInvoice()
                ]
            );

            if ($order->canInvoice()) {
                /**
                 * 2020/10/23 Double Invoice Correction
                 * Force reload order state to check status just before update,
                 * discard invoice if status changed since start of process
                 */
                $currentOrder = $this->orderFactory->create()->loadByIncrementId($order->getIncrementId());
                $this->debugData([
                    'info' => " ($processId) ($id) $processClass: can_invoice (double check): " . $currentOrder->canInvoice()
                ]);

                if (!$currentOrder->canInvoice()) {
                    // Simply just skip this section
                    goto cannot_invoice_marker;
                }

                if ($this->lockManager->isLocked(self::LOCK_PREFIX . $id)) {
                    $message = "($processId) ($id) $processClass: acquired lock for invoice txn";
                    $this->debugData(['info' => $message]);

                    $invoice = $this->_invoiceService->prepareInvoice($order);
                    $invoice->register();
                    $this->_invoiceRepository->save($invoice);

                    $this->debugData(['info' => " ($processId) ($id) $processClass: INVOICED"]);

                    $this->invoiceSender->send($invoice);

                    //send notification code
                    $order->addCommentToStatusHistory(
                        __('Notified customer about invoice #%1.', $invoice->getId())
                    )->setIsCustomerNotified(true);

                    $this->_orderRepository->save($order);
                }
            } else {
                /**
                 * Double Invoice Correction
                 * 2020/10/23
                 */
                cannot_invoice_marker:
                $this->debugData(['info' => " ($processId) ($id) $processClass: Already invoiced, skip"]);
            }
        } catch (Exception $e) {
            $this->debugData(['error' => $e->getMessage()]);
            throw new LocalizedException(__("Error encountered while capturing your order"));
        } finally {
            $this->lockManager->unlock(self::LOCK_PREFIX . $id);
        }
    }

    /**
     * Process fraud status
     *
     * @param Payment $payment
     * @return $this
     */
    protected function processPaymentFraudStatus(Payment $payment)
    {
        try {
            $fraudDetailsResponse = $this->fetchTransactionFraudDetails();
            $fraudData = $fraudDetailsResponse->getData();

            if (empty($fraudData)) {
                $payment->setIsFraudDetected(false);

                return $this;
            }

            $payment->setIsFraudDetected(true);
            $payment->setAdditionalInformation('fraud_details', $fraudData);
        } catch (Exception $e) {
            //this request is optional
        }

        return $this;
    }

    /**
     * Generate request object and fill its fields from Quote or Order object
     *
     * @param Order $order Quote or order object.
     * @param FrontendHelper $helper
     * @return Request
     */
    public function generateRequestFromOrder(Order $order, $helper)
    {
        return $this->_requestFactory->create()
            ->setConstantData($this, $order, $helper)
            ->setDataFromOrder($order, $this);
    }

    /**
     * Register order cancellation. Return money to customer if needed.
     *
     * @param Order $order
     * @param Response $response
     * @param bool $voidPayment
     * @return void
     */
    public function declineOrder(Order $order, Response $response, bool $voidPayment = false)
    {
        $payment = $order->getPayment();

        try {
            if (
                $voidPayment &&
                $response->getTranxId() &&
                strtoupper($response->getTransactionType()) == self::REQUEST_TYPE_PAYMENT
            ) {
                $this->addStatusCommentOnUpdate($payment, $response);
                $order->cancel();
                $this->_orderRepository->save($order);
            }
        } catch (Exception $e) {
            //quiet decline
            $this->_logger->critical($e);
            $this->debugData(['error' => $e->getMessage()]);
        }
    }

    /**
     * Fetch transaction details info
     *
     * @param InfoInterface $payment
     * @param string $transactionId
     * @throws LocalizedException
     * @return Response
     */
    public function fetchTransactionInfo(InfoInterface $payment, $transactionId)
    {
        return $this->_easyPlusApi->fetchTransactionInfo($this, $payment, $transactionId);
    }

    /**
     * Fetch fraud details
     * @throws LocalizedException
     * @return DataObject
     */
    public function fetchTransactionFraudDetails()
    {
        $response = $this->getResponse();
        $responseData = new DataObject();
        $fraudTransaction = $response->getFraudTransaction();

        if (!isset($fraudTransaction)) {
            return $responseData;
        }

        $responseData->setFdsFilterAction(
            $fraudTransaction->FDSFilterAction
        );
        $responseData->setAvsResponse((string)$fraudTransaction->AVSResponse);
        $responseData->setCardCodeResponse((string)$fraudTransaction->cardCodeResponse);
        $responseData->setCavvResponse((string)$fraudTransaction->CAVVResponse);
        $responseData->setFraudFilters($this->getFraudFilters($fraudTransaction->FDSFilters));

        return $responseData;
    }

    /**
     * Get fraud filters
     *
     * @param \Magento\Framework\Simplexml\Element $fraudFilters
     * @return array
     */
    protected function getFraudFilters($fraudFilters)
    {
        $result = [];

        foreach ($fraudFilters->FDSFilter as $filer) {
            $result[] = [
                'name' => (string)$filer->name,
                'action' => (string)$filer->action
            ];
        }

        return $result;
    }

    /**
     * Import payment info to payment
     *
     * @param Response $response
     * @param InfoInterface $payment
     * @return void
     */
    protected function _importToPayment($response, $payment)
    {
        $payment->setTransactionId($response->getTranxId())
            ->setParentTransactionId(null)
            ->setIsTransactionClosed(true)
            ->setTransactionAdditionalInfo(self::REAL_TRANSACTION_ID_KEY, $response->getTranxId());

        $this->_easyPlusApi->importPaymentInfo($response, $payment);
    }

    /**
     * Add comment on order status update
     *
     * @param Payment $payment
     * @param DataObject $response
     */
    protected function addStatusCommentOnUpdate(Payment $payment, DataObject $response)
    {
        $transactionId = $response->getTranxId();

        if ($payment->getIsTransactionApproved()) {
            $message = __(
                'Transaction %1 has been approved. <br />Amount %2.<br />PayU transaction status: %3',
                $transactionId,
                $payment->getOrder()->getBaseCurrency()->formatTxt($payment->getAmountOrdered()),
                $response->getTransactionState()
            );
            $payment->getOrder()
                ->addCommentToStatusHistory(
                    $message . '<br/>Message: ' . $response->getResultMessage(),
                    'processing'
                );
            return;
        } elseif ($payment->getIsTransactionPending()) {
            $message = __(
                'Transaction %1 is pending payment. <br />Amount %2. <br />PayU transaction status: %3',
                $transactionId,
                $payment->getOrder()->getBaseCurrency()->formatTxt($payment->getAmountOrdered()),
                $response->getTransactionState()
            );
        } elseif ($payment->getIsTransactionDenied()) {
            $message = __(
                'Transaction %1 has been voided/declined. <br />Amount %2. <br />PayU transaction status: %3.',
                $transactionId,
                $payment->getOrder()->getBaseCurrency()->formatTxt($payment->getAmountOrdered()),
                $response->getTransactionState()
            );
        } elseif ($payment->getIsTransactionProcessing()) {
            $message = __(
                'Transaction %1 is still in processing. <br />Amount %2. <br />PayU transaction status: %3.',
                $transactionId,
                $payment->getOrder()->getBaseCurrency()->formatTxt($payment->getAmountOrdered()),
                $response->getTransactionState()
            );
        }

        $payment->getOrder()
            ->addCommentToStatusHistory(
                $message . '<br/>Message: ' . $response->getResultMessage()
            );
    }

    /**
     * Is active
     *
     * @param int|null $storeId
     * @return bool
     * @deprecated 100.2.0
     */
    public function isActive($storeId = null)
    {
        return (bool)(int)$this->getConfigData('active', $storeId);
    }

    /**
     * Check whether payment method can be used
     * @param CartInterface|Quote|null $quote
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null)
    {
        return parent::isAvailable($quote) && $this->isMethodAvailable();
    }

    /**
     * Check whether method available for checkout or not
     *
     * @param null $methodCode
     *
     * @return bool
     */
    public function isMethodAvailable($methodCode = null)
    {
        $methodCode = $methodCode ?: $this->_code;

        return $this->isMethodActive($methodCode);
    }

    /**
     * Check whether method active in configuration and supported for merchant country or not
     *
     * @param string $methodCode method code
     * @return bool
     *
     * @todo: refactor this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function isMethodActive($methodCode)
    {
        $methodCode = $methodCode ?: $this->_code;
        $isEnabled = (bool)$this->getConfigData('active');

        return $this->isMethodSupportedForCountry($methodCode) && $isEnabled;
    }

    /**
     * Check whether method supported for specified country or not
     *
     * @param string|null $method
     * @param string|null $countryCode
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function isMethodSupportedForCountry($method = null, $countryCode = null)
    {
        return true;
    }

    /**
     * Validate amount is within threshold
     *
     * @param $amount float amount to validate
     * @throws LocalizedException
     */
    protected function validateAmount($amount)
    {
        if ($amount <= 0 || $amount < $this->_minAmount || $amount > $this->_maxAmount) {
            throw new LocalizedException(__('Invalid amount for checkout with this payment method.'));
        }
    }

    protected function clearSessionData()
    {
        $this->_session->unsCheckoutReference();
        $this->_session->unsCheckoutOrderIncrementId();
        $this->_session->unsCheckoutRedirectUrl();

        $this->_checkoutSession->resetCheckout();
    }
}
