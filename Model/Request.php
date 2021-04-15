<?php
/**
 * PayU_EasyPlus payment request
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


namespace PayU\EasyPlus\Model;

use PayU\EasyPlus\Helper\Data;
use Magento\Sales\Model\Order;
use Magento\Framework\DataObject;

class Request extends DataObject
{
    /**
     * Set PayU data to request.
     *
     * @param AbstractPayment $paymentMethod
     * @param Order $order
     * @param Data $helper
     * @throws
     * @return $this
     */
	public function setConstantData(
        AbstractPayment $paymentMethod,
        Order $order,
        $helper
    ) {
        $this->setData('Api', $paymentMethod->getApi()->getApiVersion())
            ->setData('Safekey', $paymentMethod->getApi()->getSafeKey())
            ->setData('TransactionType', 'PAYMENT')
            ->setData('AdditionalInformation', array(
                'merchantReference'         => $order->getIncrementId(),
                'notificationUrl'           => $helper->getNotificationUrl($paymentMethod->getCode()),
                'cancelUrl'                 => $helper->getCancelUrl($paymentMethod->getCode()),
                'returnUrl'                 => $helper->getReturnUrl($paymentMethod->getCode()),
                'supportedPaymentMethods'   => $paymentMethod->getConfigData('payment_methods'),
                'redirectChannel'           => $paymentMethod->getConfigData('redirect_channel'),
                'secure3d'                  => 'True'
            ));

        return $this;
	}

    /**
     * Set entity data to request
     *
     * @param Order $order
     * @param AbstractPayment $paymentMethod
     * @return $this
     */

	public function setDataFromOrder(
        Order $order,
        AbstractPayment $paymentMethod
    ) {
	    $this->setData('Basket', array(
            'description' => sprintf('#%s, %s', $order->getIncrementId(), $order->getCustomerEmail()),
            'amountInCents' => ($order->getBaseTotalDue() * 100),
            'currencyCode' => $paymentMethod->getValue('currency'))
        )
        ->setData('Customer', array(
            'merchantUserId' => $order->getCustomerId(),
            'email' => $order->getCustomerEmail(),
            'firstName' => $order->getCustomerFirstName(),
            'lastName' => $order->getCustomerLastName()
            //,
            //'mobile' => $order->getShippingAddress()->getTelephone(),
            //'regionalId' => preg_replace('/\D\D/', '27', str_replace("KE", "27", $order->getShippingAddress()->getCountryId())),
            //'countryCode' => preg_replace('/\D\D/', '27', str_replace("KE", "27", $order->getShippingAddress()->getCountryId()))
        )
        );

        return $this;
    }
}
