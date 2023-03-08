<?php
/**
 * PayU_EasyPlus payment config provider
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Helper\Data as PaymentHelper;

/**
 * Class PayUConfigProvider
 *
 * General payment method configuration provider
 */
class PayUConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCodes = [
        CreditCard::CODE,
        Ebucks::CODE,
        Rcs::CODE,
        RcsPlc::CODE,
        EFTPro::CODE,
        DiscoveryMiles::CODE,
        Mobicred::CODE,
        Ucount::CODE,
        Fasta::CODE,
        Mpesa::CODE,
        AirtelMoney::CODE,
        MobileBanking::CODE,
        MtnMobile::CODE,
        Tigopesa::CODE,
        Equitel::CODE,
        Payflex::CODE,
        MoreTyme::CODE,
        CapitecPay::CODE
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];

    /**
     * @var Repository
     */
     protected $assetRepo;

    /**
     * @param PaymentHelper $paymentHelper
     * @param Repository $assetRepo
     *
     * @throws LocalizedException
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        Repository $assetRepo
    ) {
        $this->assetRepo = $assetRepo;
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [];

        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $config['payment']['imageSrc'][$code] = $this->getPaymentMethodImageUrl($code);
            }
        }

        return $config;
    }

    /**
     * Get PayU "mark" image URL
     * Supposed to be used on payment methods selection
     *
     * @param string $code
     * @return string
     */
    public function getPaymentMethodImageUrl($code)
    {
        return $this->assetRepo->getUrl('PayU_EasyPlus::images/' . $code . '.png');
    }
}
