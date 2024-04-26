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

use Exception;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use PayU\EasyPlus\Model\Api\Api;
use PayU\EasyPlus\Model\Api\Factory;
use PayU\EasyPlus\Model\Error\Code;
use stdClass;

/**
 * Class Response
 *
 * @package PayU\EasyPlus\Model
 */
class Response extends DataObject
{
    /**
     * @var Code
     */
    protected Code $errorCode;

    /**
     * @var Api
     */
    protected Api $api;

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

    public function isPaymentNew(): bool
    {
        return $this->getReturn()->successful
            && $this->getTransactionState() == AbstractPayU::TRANS_STATE_NEW;
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

    /**
     * @return bool
     */
    public function isPaymentFailed(): bool
    {
        return ($this->getReturn()->successful === true || $this->getReturn()->successful === false)
            && in_array(
                $this->getTransactionState(),
                [AbstractPayU::TRANS_STATE_FAILED, AbstractPayU::TRANS_STATE_EXPIRED, AbstractPayU::TRANS_STATE_TIMEOUT]
            );
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
    public function hasPaymentMethod(): bool
    {
        return property_exists($this->getReturn(), 'paymentMethodsUsed');
    }

    public function getPaymentMethod()
    {
        return $this->hasPaymentMethod() ? $this->getReturn()->paymentMethodsUsed : null;
    }

    /**
     * @return bool
     */
    public function isPaymentMethodCc(): bool
    {
        return $this->hasPaymentMethod() && $this->checkPaymentMethodCc();
    }

    public function checkPaymentMethodCc(): bool
    {
        $paymentMethods = $this->getPaymentMethod();

        if (is_array($paymentMethods)) {
            foreach ($paymentMethods as $method) {
                if (property_exists($method, 'gatewayReference')) {
                    return true;
                }
            }
        } else {
            if (property_exists($paymentMethods, 'gatewayReference')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public function getGatewayReference(): string
    {
        $gatewayReference = 'N/A';
        $paymentMethods = $this->getPaymentMethod();

        if (is_array($paymentMethods)) {
            foreach ($paymentMethods as $method) {
                if (property_exists($method, 'gatewayReference')) {
                    $gatewayReference = $method->gatewayReference;
                }
            }
        } else {
            if (property_exists($paymentMethods, 'gatewayReference')) {
                $gatewayReference = $paymentMethods->gatewayReference;
            }
        }

        return $gatewayReference;
    }

    /**
     * @return string
     */
    public function getCcNumber(): string
    {
        $cardNumber = 'N/A';
        $hasCcNumber = $this->hasPaymentMethod() && $this->isPaymentMethodCc();

        if ($hasCcNumber) {
            $paymentMethods = $this->getPaymentMethod();

            if (is_array($paymentMethods)) {
                foreach ($paymentMethods as $method) {
                    if (property_exists($method, 'cardNumber')) {
                        $cardNumber = $method->cardNumber;
                    }
                }
            } else {
                if (property_exists($paymentMethods, 'cardNumber')) {
                    $cardNumber = $paymentMethods->cardNumber;
                }
            }
        }

        return $cardNumber;
    }

    public function getTotalCaptured()
    {
        $total = 0;

        if ($this->isPaymentNew()) {
            return $total;
        }

        $paymentMethods = $this->getPaymentMethod();

        if (!$paymentMethods) {
            return $total;
        }

        if (is_a($paymentMethods, stdClass::class, true) &&
            !property_exists($paymentMethods, 'amountInCents')
        ) {
            return $total;
        }

        if (is_a($paymentMethods, stdClass::class, true) &&
            property_exists($paymentMethods, 'amountInCents')
        ) {
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
    public function processReturn(Order $order, string $processId, string $processClass): bool
    {
        $result = false;
        $payment = $order->getPayment();
        $method = $payment->getMethodInstance();

        try {
            $result = $method->process($this->getParams(), $processId, $processClass);
        } catch (LocalizedException|Exception $exception) {
            return false;
        }

        $response = $method->getResponse();
        $this->setData($response->getData());

        if ($response->isPaymentSuccessful()) {
            $result = true;
        } elseif ($response->isPaymentPending() || $response->isPaymentProcessing()) {
            $result = false;
        }

        return $result;
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
     * @param Order $order
     * @param array $data
     * @param string $processId
     * @param string $processClass
     * @throws LocalizedException
     */
    public function processNotify(Order $order, array $data, string $processId, string $processClass)
    {
        $payment = $order->getPayment();
        $payment->getMethodInstance()->processNotification($order, $data, $processId, $processClass);
    }

    /**
     * Is Canceled Payflex transaction
     *
     * @param Order $order
     * @return bool
     */
    public function isCancelPayflex(Order $order)
    {
        $payment = $order->getPayment();
        $method = $payment->getMethodInstance();

        return $method->getCode() === Payflex::CODE && $this->isPaymentProcessing();
    }
}
