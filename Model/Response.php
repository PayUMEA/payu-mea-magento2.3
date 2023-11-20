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
    protected $api;

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
        $paymentMethod = $this->getPaymentMethod();

        if (!$paymentMethod) {
            $paymentMethod = $this->getReturn()->basket;
        }

        return ($paymentMethod->amountInCents / 100);
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
        return $this->getReturn()->transactionState;
    }

    public function getTransactionType()
    {
        return $this->getReturn()->transactionType;
    }

    public function getPointOfFailure()
    {
        return $this->getReturn()->pointOfFailure;
    }

    public function getFraudTransaction()
    {
        return isset($this->getReturn()->transaction) ? $this->getReturn()->transaction : null;
    }

    /**
     * Process return from PayU after payment
     *
     * @param Order $order
     * @return bool
     * @throws LocalizedException
     */
    public function processReturn(Order $order): bool
    {
        $payment = $order->getPayment();
        $payment->getMethodInstance()->process($this->getParams());

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
     * @throws LocalizedException
     */
    public function processNotify($data, $order)
    {
        $payment = $order->getPayment();
        $payment->getMethodInstance()->processNotification($data, $order);
    }
}
