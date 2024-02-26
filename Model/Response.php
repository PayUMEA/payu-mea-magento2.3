<?php
/**
 * PayU_EasyPlus payement response validation model
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use PayU\EasyPlus\Model\Api\Api;
use PayU\EasyPlus\Model\Api\Factory;
use PayU\EasyPlus\Model\Error\Code;

/**
 * Class Response
 *
 * @package PayU\EasyPlus\Model
 */
class Response extends DataObject
{
    protected $errorCode;

    /**
     * @var Api
     */
    protected $api;

    /**
     * @param Code $errorCodes
     * @param Factory $apiFactory
     * @param array $data
     */
    public function __construct(
        Code $errorCodes,
        Factory $apiFactory,
        array $data = []
    ) {
        $this->errorCode = $errorCodes;
        $this->api = $apiFactory->create();

        parent::__construct($data);
    }

    public function getReturn()
    {
        return $this->getData('return');
    }

    public function isPaymentSuccessful(): bool
    {
        return $this->getReturn()->successful
            && $this->getTransactionState() == AbstractPayU::TRANS_STATE_SUCCESSFUL;
    }

    /**
     * @return bool
     */
    public function isPaymentPending(): bool
    {
        return $this->getReturn()->successful
            && $this->getTransactionState() == AbstractPayU::TRANS_STATE_AWAITING_PAYMENT;
    }

    /**
     * @return bool
     */
    public function isPaymentProcessing(): bool
    {
        return ($this->getReturn()->successful === true || $this->getReturn()->successful === false)
            && $this->getTransactionState() == AbstractPayU::TRANS_STATE_PROCESSING;
    }

    public function getTranxId()
    {
        return $this->getReturn()->payUReference;
    }

    public function getInvoiceNum()
    {
        return $this->getReturn()->merchantReference;
    }

    public function getResultCode()
    {
        return $this->getReturn()->resultCode;
    }

    public function getResultMessage()
    {
        return $this->getReturn()->resultMessage;
    }

    /**
     * @return bool
     */
    public function hasPaymentMethod()
    {
        return isset($this->getReturn()->paymentMethodsUsed);
    }

    public function getPaymentMethod()
    {
        return $this->getReturn()->paymentMethodsUsed;
    }

    /**
     * @return bool
     */
    public function isPaymentMethodCc()
    {
        return $this->hasPaymentMethod() && isset($this->getReturn()->paymentMethodsUsed->cardNumber);
    }

    /**
     * @return string
     */
    public function getGatewayReference(): string
    {
        return $this->hasPaymentMethod() ? $this->getReturn()->paymentMethodsUsed->gatewayReference : '';
    }

    /**
     * @return string
     */
    public function getCcNumber()
    {
        return $this->hasPaymentMethod() ? $this->getReturn()->paymentMethodsUsed->cardNumber : '';
    }

    public function getTotalCaptured()
    {
        $total = 0;
        $paymentMethods = $this->getPaymentMethod();

        if (!$paymentMethods) {
            return $total;
        }

        if (is_a($paymentMethods, \stdClass::class, true)) {
            return ($paymentMethods->amountInCents / 100);
        }

        foreach ($paymentMethods as $paymentMethod) {
            $total += $paymentMethod->amountInCents;
        }

        // Prevent division by zero
        return (max($total, 1) / 100);
    }

    public function getDisplayMessage()
    {
        return $this->getReturn()->displayMessage;
    }

    /**
     * @return bool
     */
    public function isFraudDetected()
    {
        return isset($this->getReturn()->fraud) && $this->getReturn()->fraud->resultCode;
    }

    public function getTransactionState()
    {
        return isset($this->getReturn()->transactionState) ?
            $this->getReturn()->transactionState :
            '';
    }

    public function getTransactionType()
    {
        return isset($this->getReturn()->transactionType) ?
            $this->getReturn()->transactionType :
            '';
    }

    public function getPointOfFailure()
    {
        return $this->getReturn()->pointOfFailure;
    }

    public function getFraudTransaction()
    {
        return isset($this->getReturn()->transaction) ? $this->getReturn()->transaction : null;
    }

    public function getTotalDue()
    {
        return isset($this->getReturn()->basket) ? $this->getReturn()->basket->amountInCents : 0;
    }

    /**
     * Process return from PayU after payment
     *
     * @param Order $order
     * @param string $processId
     * @param string $processClass
     * @return bool
     * @throws LocalizedException
     */
    public function processReturn(Order $order, $processId, $processClass): bool
    {
        $payment = $order->getPayment();
        $payment->getMethodInstance()->process($this->getParams(), $processId, $processClass);

        /** @var Response $response */
        $response = $payment->getMethodInstance()->getResponse();
        $this->setData($response->getData());

        if ($response->isPaymentSuccessful()) {
            return true;
        } elseif ($response->isPaymentPending() || $response->isPaymentProcessing()) {
            return false;
        } else {
            $payment->getMethodInstance()->declineOrder($order, $response, true);

            return false;
        }
    }

    /**
     * Process user payment cancellation
     *
     * @param Order $order
     * @throws LocalizedException
     */
    public function processCancel(Order $order)
    {
        $payment = $order->getPayment();
        $payment->getMethodInstance()->processCancellation($this->getParams());
    }

    /**
     * Process Instant Payment Notification
     *
     * @param array $data
     * @param Order $order
     * @param string $processId
     * @throws LocalizedException
     */
    public function processNotify($data, $order, $processId, $processClass)
    {
        $payment = $order->getPayment();
        $payment->getMethodInstance()->processNotification($data, $order, $processId, $processClass);
    }
}
