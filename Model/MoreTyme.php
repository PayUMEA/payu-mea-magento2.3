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

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;

/**
 * Payment model for payment method MoreTyme
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class MoreTyme extends AbstractPayment
{
    const CODE = 'payumea_moretyme';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::CODE;

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
        $payURedirect = $this->_session->getCheckoutRedirectUrl();

        if (!$payURedirect) {
            return $this->_setupTransaction($payment, $amount);
        }

        $payment->setSkipOrderProcessing(true);

        $payment->getOrder()->setState(Order::STATE_PENDING_PAYMENT)
            ->setStatus(Order::STATE_PENDING_PAYMENT);

        return $this;
    }
}
