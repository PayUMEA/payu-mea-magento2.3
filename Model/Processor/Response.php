<?php
/**
 * Copyright © 2024 PayU Financial Services. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace PayU\EasyPlus\Model\Processor;

use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\OrderFactory;
use PayU\EasyPlus\Model\ResourceModel\TransactionFactory as PayUTransactionResourceFactory;
use PayU\EasyPlus\Model\TransactionFactory as PayUTransactionFactory;

class Response
{
    public const PENDING_PAGE = 1;
    public const SUCCESS_PAGE = 2;
    public const FAILED_PAGE = 3;

    /**
     * @var Logger
     */
    protected Logger $logger;

    /**
     * @var OrderFactory
     */
    protected OrderFactory $orderFactory;

    /**
     * @var PayUTransactionFactory
     */
    protected PayUTransactionFactory $payUTransactionFactory;

    /**
     * @var PayUTransactionResourceFactory
     */
    protected PayUTransactionResourceFactory $payUTransactionResourceFactory;

    /**
     * @param Logger $logger
     * @param OrderFactory $orderFactory
     * @param PayUTransactionFactory $payUTransactionFactory
     * @param PayUTransactionResourceFactory $payUTransactionResourceFactory
     */
    public function __construct(
        Logger $logger,
        OrderFactory $orderFactory,
        PayUTransactionFactory $payUTransactionFactory,
        PayUTransactionResourceFactory $payUTransactionResourceFactory
    ) {
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
        $this->payUTransactionFactory = $payUTransactionFactory;
        $this->payUTransactionResourceFactory = $payUTransactionResourceFactory;
    }
    /**
     * @param string $incrementId
     * @param string $processId
     * @param string $processClass
     * @return bool
     * @throws Exception
     */
    public function canProceed(string $incrementId, string $processId, string $processClass): bool
    {
        $transaction = $this->payUTransactionFactory->create();
        $resourceModel = $this->payUTransactionResourceFactory->create();
        $resourceModel->load($transaction, $incrementId, 'increment_id');

        if ($transaction->getId() > 0 && $transaction->getLock() && $transaction->getStatus() === 'processing') {
            return false;
        }

        $transaction->setIncrementId($incrementId)
            ->setLock(true)
            ->setStatus('processing')
            ->setProcessId($processId)
            ->setProcessClass($processClass);

        try {
            $resourceModel->save($transaction);
        } catch (AlreadyExistsException $exception) {
            $this->logger->debug([
                'error' => "$incrementId ($processId) $processClass attempted to process PayU transaction"
            ]);

            return false;
        }

        return true;
    }

    /**
     * @param string $incrementId
     * @param string $processId
     * @return void
     * @throws AlreadyExistsException|Exception
     */
    public function updateTransactionLog(string $incrementId, string $processId)
    {
        $transaction = $this->payUTransactionFactory->create();
        $resourceModel = $this->payUTransactionResourceFactory->create();
        $resourceModel->load($transaction, $incrementId, 'increment_id');

        if ($transaction->getId() === 0) {
            return;
        }

        if ($transaction->getProcessId() !== $processId) {
            return;
        }

        try {
            $transaction->setStatus('complete');
            $transaction->setLock(false);
            $resourceModel->save($transaction);
        } catch (AlreadyExistsException $exception) {
            // It's fine we are just updating
        }
    }

    public function redirectTo(string $incrementId, string $payUReference): int
    {
        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
        $payment = $order->getPayment();

        $response = $payment->getMethodInstance()->fetchTransactionInfo($payment, $payUReference);

        switch ($response->getTransactionState()) {
            case 'FAILED':
            case 'TIMEOUT':
            case 'EXPIRED':
                $page = self::FAILED_PAGE;
                break;
            case 'NEW':
            case 'PROCESSING':
                $page = self::PENDING_PAGE;
                break;
            default:
                $page = self::SUCCESS_PAGE;
        }

        return $page;
    }
}
