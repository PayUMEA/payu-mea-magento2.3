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

    public function isPaymentSuccessful()
    {
        return $this->getReturn()->successful
            && $this->getTransactionState() == AbstractPayU::TRANS_STATE_SUCCESSFUL;
    }

    /**
     * @return bool
     */
    public function isPaymentPending()
    {
        return $this->getReturn()->successful
            && $this->getTransactionState() == AbstractPayU::TRANS_STATE_AWAITING_PAYMENT;
    }

    /**
     * @return bool
     */
    public function isPaymentProcessing()
    {
        return $this->getReturn()->successful
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

    public function isPaymentMethodCc()
    {
        return isset($this->getReturn()->paymentMethodUsed->Creditcard);
    }

    public function getGatewayReference()
    {
        return isset($this->getReturn()->paymentMethodUsed->gatewayReference);
    }

    public function getCcNumber()
    {
        return isset($this->getReturn()->paymentMethodUsed->cardNumber);
    }

    public function getTotalCaptured()
    {
        $paymentMethod = $this->getReturn()->paymentMethodsUsed;

        if (!$paymentMethod) {
            $paymentMethod = $this->getReturn()->basket;
        }

        return ($paymentMethod->amountInCents / 100);
    }

    public function getDisplayMessage()
    {
        return $this->getReturn()->displayMessage;
    }

    public function isFraudDetected()
    {
        return isset($this->getReturn()->fraud->resultCode);
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

    /**
     * Process return from PayU after payment
     *
     * @param Order $order
     * @return bool|string
     */
    public function processReturn($order)
    {
        $payment = $order->getPayment();
        $payment->getMethodInstance()->process($this->getParams(), $this);

        /** @var \PayU\EasyPlus\Model\Response $response */
        $response = $payment->getMethodInstance()->getResponse();

        if ($response->isPaymentSuccessful()) {
            $this->api->importPaymentInfo($response, $payment);

            return true;
        } elseif ($response->isPaymentPending()) {
            $this->api->importPaymentInfo($response, $payment);

            return true;
        } else {
            $message = $response->getDisplayMessage();
            $payment->getMethodInstance()->declineOrder($order, $response, true, $message);

            return $message;
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
