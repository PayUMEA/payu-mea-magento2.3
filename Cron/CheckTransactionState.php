<?php

namespace PayU\EasyPlus\Cron;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PayU\EasyPlus\Model\AbstractPayU;
use PayU\EasyPlus\Model\Api\Api;
use PayU\EasyPlus\Model\Api\Factory;
use PayU\EasyPlus\Model\Response;
use Psr\Log\LoggerInterface;

class CheckTransactionState
{
    /**
     * @var string
     */
    protected string $processId;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $_logger;

    /**
     * @var Api
     */
    protected Api $_easyPlusApi;

    /**
     * @var EncryptorInterface
     */
    protected EncryptorInterface $_encryptor;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $_storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $_scopeConfig;

    /**
     * @var OrderFactory
     */
    protected OrderFactory $_orderFactory;

    /**
     * @var SearchCriteriaBuilder
     */
    protected SearchCriteriaBuilder $_searchCriteriaBuilder;

    /**
     * @var Registry|null
     */
    protected ?Registry $_coreRegistry = null;

    /**
     * @var string|null
     */
    protected ?string $_code = null;

    /**
     * @var string|null
     */
    protected ?string $_payUReference = null;

    /**
     * @var State
     */
    private State $_state;

    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $_orderCollectionFactory;

    /**
     * @var OrderSender
     */
    private OrderSender $_orderSender;

    /**
     * @var InvoiceSender
     */
    private InvoiceSender $_invoiceSender;

    /**
     * @var InvoiceService
     */
    private InvoiceService $_invoiceService;

    /**
     * @var Transaction
     */
    private Transaction $_transaction;

    /**
     * @var Config
     */
    private Config $_orderConfig;

    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $_orderRepository;

    /**
     * CheckTransactionState constructor.
     * @param State $state
     * @param LoggerInterface $logger
     * @param Factory $apiFactory
     * @param EncryptorInterface $encryptor
     * @param StoreManagerInterface $storeManager
     * @param Registry $registry
     * @param ScopeConfigInterface $scopeConfig
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CollectionFactory $orderCollectionFactory
     * @param OrderSender $orderSender
     * @param InvoiceSender $invoiceSender
     * @param InvoiceService $invoiceService
     * @param Transaction $transaction
     * @param Config $orderConfig
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        State $state,
        LoggerInterface $logger,
        Factory $apiFactory,
        EncryptorInterface $encryptor,
        StoreManagerInterface $storeManager,
        Registry $registry,
        ScopeConfigInterface $scopeConfig,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CollectionFactory $orderCollectionFactory,
        OrderSender $orderSender,
        InvoiceSender $invoiceSender,
        InvoiceService $invoiceService,
        Transaction $transaction,
        Config $orderConfig,
        OrderFactory $orderFactory
    ) {
        $this->_state = $state;
        $this->_logger = $logger;
        $this->_easyPlusApi = $apiFactory->create();
        $this->_encryptor = $encryptor;
        $this->_storeManager = $storeManager;
        $this->_coreRegistry = $registry;
        $this->_scopeConfig = $scopeConfig;
        $this->_orderRepository = $orderRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_orderSender = $orderSender;
        $this->_invoiceSender = $invoiceSender;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
        $this->_orderConfig = $orderConfig;
        $this->_orderFactory = $orderFactory;
    }

    /**
     * @param Response $response
     * @param OrderInterface $order
     * @throws LocalizedException
     */
    public function processReturn(Response $response, OrderInterface $order)
    {
        $transactionNotes = "<strong>-----PAYU STATUS CHECKED ---</strong><br />";

        if (!$response->getResultCode() || (in_array($response->getResultCode(), ['POO5', 'EFTPRO_003', '999', '305']))) {
            $this->_logger->info("($this->processId) No resultCode");
            $this->_logger->info($response->toJson());

            return;
        }

        if (!$response->getTransactionState()
            || (!in_array(
                $response->getTransactionState(),
                ['NEW', 'PROCESSING', 'SUCCESSFUL', 'AWAITING_PAYMENT', 'FAILED', 'TIMEOUT', 'EXPIRED']
            ))
        ) {
            $this->_logger->info("($this->processId) No transactionState");
            $this->_logger->info($response->toJson());

            return;
        }

        $totalDue = $response->getTotalDue() ?? $order->getTotalDue();

        $transactionNotes .= "PayU Result Code: " . $response->getResultCode() . "<br />";
        $transactionNotes .= "PayU Reference: " . $response->getTranxId() . "<br />";
        $transactionNotes .= "PayU Message: " . $response->getResultMessage() . "<br />";
        $transactionNotes .= "PayU Payment Status: " . $response->getTransactionState() . "<br /><br />";
        $transactionNotes .= "Order Amount: " . $totalDue . "<br />";
        $transactionNotes .= "Amount Paid: " . $response->getTotalCaptured() . "<br />";
        $transactionNotes .= "Merchant Reference : " . $response->getInvoiceNum() . "<br />";

        switch ($response->getTransactionState()) {
            case 'SUCCESSFUL':
                $this->invoiceAndNotifyCustomer($order);
                break;
            case 'FAILED':
            case 'TIMEOUT':
            case 'EXPIRED':
                $order->cancel();
                break;
        }

        $order->addStatusHistoryComment($transactionNotes, true);
        $this->_orderRepository->save($order);
    }

    /**
     * @return Collection
     */
    public function getOrderCollection(): Collection
    {
        return $this->_orderCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter(
                'status',
                [
                    'in' => explode(',', $this->getCRONConfigData('order_status'))
                ]
            );
    }

    /**
     * @throws LocalizedException|Exception
     */
    public function execute()
    {
        $bypassPayuCron = $this->getCRONConfigData('bypass');

        if ('1' ===  $bypassPayuCron) {
            $this->_logger->info("PayU CRON DISABLED");

            return;
        }

        $processId = uniqid();
        $this->processId = $processId;

        $this->_logger->info("PayU CRON Started, PID: $processId");

        $orders = $this->getOrderCollection();

        foreach ($orders->getItems() as $order) {
            $payment = $order->getPayment();
            $additionalInfo = $payment->getAdditionalInformation();
            $code = $payment->getData('method');

            $id = $order->getEntityId();
            $this->_logger->info("($processId) Check: $id");

            if (false === strpos($code, 'payumea')) {
                $this->_logger->info("($processId) Not PayU");

                continue;
            }

            if (isset($additionalInfo["fraud_details"])) {
                if (in_array($additionalInfo["fraud_details"]["return"]["transactionState"], ['SUCCESSFUL'])) {
                    $this->_logger->info("($processId) ($id) Already Success");

                    continue;
                }

                $payUReference = $additionalInfo["fraud_details"]["return"]["payUReference"];
            } else {
                if (!isset($additionalInfo["payUReference"])) {
                    $this->_logger->info("($processId) No Details");

                    continue;
                }
                $payUReference = $additionalInfo["payUReference"];
            }

            $txnState = $additionalInfo["fraud_details"]["return"]["transactionState"] ?? '';

            switch ($txnState) {
                case AbstractPayU::TRANS_STATE_SUCCESSFUL:
                case AbstractPayU::TRANS_STATE_FAILED:
                case AbstractPayU::TRANS_STATE_EXPIRED:
                case AbstractPayU::TRANS_STATE_TIMEOUT:
                    $this->_logger->info(" ($id) Transaction in a final state: $txnState");
                    break;
                default:

                    if (!$this->shouldDoCheck($order)) {
                        $this->_logger->info("($processId) ($id) Check not timed");
                        break;
                    }

                    $this->_logger->info("($processId) ($id) Doing Check");
                    $this->_code = $code;
                    $this->_payUReference = $payUReference;
                    $this->initializeApi($order->getStoreId());

                    $result = $this->_easyPlusApi->checkTransaction($this->_payUReference);
                    $order = $this->_orderRepository->get($order->getId());

                    if ($order->getState() == Order::STATE_PROCESSING) {
                        $this->_logger->info("Order Completed, no need to run... order id = " . $order->getId());

                        break;
                    }

                    if ($order->hasInvoices()) {
                        if (
                            $order->getState() == Order::STATE_PENDING_PAYMENT &&
                            $result->isPaymentSuccessful()
                        ) {
                            $order->setState('processing')
                                ->setStatus('processing');
                            $this->_orderRepository->save($order);
                        }

                        $this->_logger->info("($processId) Already Invoiced, no need to run... order id = " . $order->getId());

                        break;
                    }

                    try {
                        $this->processReturn($result, $order);
                    } catch (Exception $exception) {
                        $this->_logger->info($exception->getMessage());
                        $this->_logger->info($result->toJson());
                    }

                    $order->setUpdatedAt(null);
                    $order->save();
                    break;
            }
        }

        $this->_logger->info("PayU CRON Ended, PID: $processId");
    }

    protected function shouldDoCheck($order): bool
    {
        $createdAt = strtotime($order->getCreatedAt());
        $updatedAt = strtotime($order->getUpdatedAt());

        $timeNow = time();

        $minutesCreated = (int) ceil(($timeNow - $createdAt) / 60);
        $minutesUpdated = $minutesCreated - (int) ceil(($timeNow - $updatedAt) / 60);

        $cronDelay = $this->getCRONConfigData('delay');

        if (empty($cronDelay)) {
            $cronDelay = "5";
        }

        $this->_logger->info("($this->processId) minutes_created: $minutesCreated - Delay: $cronDelay");
        $this->_logger->info("($this->processId) minutes_updated: $minutesUpdated - Delay: $cronDelay");

        $minutesCreated = $minutesCreated - $cronDelay;
        $minutesUpdated = $minutesUpdated - $cronDelay;

        $ranges = [];
        $ranges[] = [1, 4];
        $ranges[] = [5, 9];
        $ranges[] = [10, 19];
        $ranges[] = [20, 29];
        $ranges[] = [30, 59];
        $ranges[] = [(60),(2 * 60) - 1];
        $ranges[] = [(2 * 60), (3 * 60) - 1];
        $ranges[] = [(3 * 60), (6 * 60) - 1];
        $ranges[] = [(6 * 60), (12 * 60) - 1];
        $ranges[] = [(12 * 60), (24 * 60) - 1];

        for ($i = 1; $i <= 31; $i++) {
            $ii = $i * 24;
            $ranges[] = [($ii * 60), ($ii * 60) - 1];
        }

        foreach ($ranges as $v) {
            if (
                (
                    ($v[0] <= $minutesCreated) &&
                    ($minutesCreated <= $v[1])
                )  &&
                (
                    !(($v[0]  <= $minutesUpdated) &&
                        ($minutesUpdated <= $v[1]))
                )
            ) {
                return true;
            }
        }

        if (((744 <= $minutesCreated))  && (!((744 <= $minutesUpdated)))) {
            return true;
        }

        $this->_logger->info("($this->processId) Check Not Needed");

        return false;
    }

    protected function initializeApi($storeId = null)
    {
        $this->_easyPlusApi->setSafeKey($this->getValue('safe_key', $storeId));
        $this->_easyPlusApi->setUsername($this->getValue('api_username', $storeId));
        $this->_easyPlusApi->setPassword($this->getValue('api_password', $storeId));
        $this->_easyPlusApi->setMethodCode($this->_code);
    }

    public function getValue($key, $storeId = null)
    {
        if (in_array($key, ['safe_key', 'api_password'])) {
            return $this->_encryptor->decrypt($this->getConfigData($key, $storeId));
        }

        return $this->getConfigData($key, $storeId);
    }

    public function getConfigData($field, $storeId = null)
    {
        $path = 'payment/' . $this->_code . '/' . $field;

        return $this->_scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getCRONConfigData($field, $storeId = null)
    {
        $path = 'payumea/cron/' . $field;

        return $this->_scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    protected function invoiceAndNotifyCustomer(Order $order)
    {
        $id = $order->getIncrementId();

        try {
            $order->setCanSendNewEmailFlag(true);
            $this->_orderSender->send($order);

            $this->_logger->info("($this->processId) ($id) PayU CRON: can_invoice (initial check): " . $order->canInvoice());

            if ($order->canInvoice()) {

                /**
                 * 2021/06/16 Double Invoice Correction
                 * Force reload order state to check status just before update,
                 * discard invoice if status changed since start of process
                 */
                $orderStatus = $this->_orderFactory->create()->loadByIncrementId($order->getIncrementId());
                $this->_logger->info('($this->process_id) ($id) PayU CRON: can_invoice (double check): ' . $orderStatus->canInvoice());

                if (!$orderStatus->canInvoice()) {
                    // Simply just skip this section
                    goto cannot_invoice_marker;
                }

                $status = $this->_orderConfig->getStateDefaultStatus('processing');
                $order->setState("processing")->setStatus($status);
                $this->_orderRepository->save($order);

                $invoice = $this->_invoiceService->prepareInvoice($order);
                $invoice->register();
                $invoice->save();
                $transactionService = $this->_transaction->addObject(
                    $invoice
                )->addObject(
                    $invoice->getOrder()
                );
                $transactionService->save();

                $this->_logger->info(" ($this->processId) ($id) PayU CRON: INVOICED");
                $this->_invoiceSender->send($invoice);

                //send notification code
                $order->addStatusHistoryComment(
                    __('Notified customer about invoice #%1.', $invoice->getId())
                )->setIsCustomerNotified(true);
                $this->_orderRepository->save($order);
            } else {
                /**
                 * Double Invoice Correction
                 * 2021/06/16
                 */
                cannot_invoice_marker:
                $this->_logger->info('($this->process_id)  ($id) Already invoiced, skip');
            }
        } catch (Exception $e) {
            throw new LocalizedException("Error encountered while capturing your order");
        }
    }
}
