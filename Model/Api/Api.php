<?php
/**
 * PayU_EasyPlus PayU API
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Model\Api;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Payment\Model\InfoInterface;
use Magento\Store\Model\ScopeInterface;
use PayU\EasyPlus\Model\AbstractPayment;
use PayU\EasyPlus\Model\Response\Factory;
use Psr\Log\LoggerInterface;

class Api extends DataObject
{
    private static $ns = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

    protected $scopeConfig;
    private static $_soapClient;

    // @var string The base sandbox URL for the PayU API endpoint.
    protected $sandboxUrl = 'https://staging.payu.co.za/service/PayUAPI';
    protected $sandboxCheckoutUrl = 'https://staging.payu.co.za/rpp.do?PayUReference=%s';

    // @var string The base live URL for the PayU API endpoint.
    protected $liveUrl = 'https://secure.payu.co.za/service/PayUAPI';
    protected $liveCheckoutUrl = 'https://secure.payu.co.za/rpp.do?PayUReference=%s';

    // @var string The PayU safe key to be used for requests.
    protected $safeKey;

    // @var string|null The version of the PayU API to use for requests.
    protected $apiVersion = 'ONE_ZERO';

    protected $username;
    protected $password;
    protected $merchantRef;
    protected $payuReference;
    protected $methodCode;

    /** var PayU\EasyPlus\Model\Response */
    protected $response;

    /** var PayU\EasyPlus\Model\Response\Factory */
    protected $responseFactory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    protected $wsdlUrl;
    protected $checkoutUrl;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Factory $responseFactory,
        LoggerInterface $logger,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;

        parent::__construct($data);

        $this->responseFactory = $responseFactory;
        $this->_logger = $logger;
    }

    /**
     * @return string The safe key used for requests.
     */
    public function getSafeKey()
    {
        return $this->safeKey;
    }

    /**
     * Sets the safe key to be used for requests.
     *
     * @param string $safeKey
     */
    public function setSafeKey($safeKey)
    {
        $this->safeKey = $safeKey;
    }

    /**
     * @return string The API version used for requests. null if we're using the
     *    latest version.
     */
    public function getApiVersion()
    {
        return $this->apiVersion;
    }

    /**
     * @return string The soap user used for requests.
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Sets the soap username to be used for requests.
     *
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * @return string The soap password used for requests.
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Sets the soap password to be used for requests.
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @return string The merchant reference to identify captured payments..
     */
    public function getMerchantReference()
    {
        return $this->merchantRef;
    }

    /**
     * Sets the merchant reference to identify captured payments.
     *
     * @param string $merchantRef
     * @return Api
     */
    public function setMerchantReference($merchantRef)
    {
        $this->merchantRef = $merchantRef;

        return $this;
    }

    /**
     * @return string The reference from PayU.
     */
    public function getPayUReference()
    {
        return $this->payuReference;
    }

    /**
     * Sets the PayU reference.
     *
     * @param string $reference
     * @return Api
     */
    public function setPayUReference($reference)
    {
        $this->payuReference = $reference;

        return $this;
    }

    /**
     * @return string The payment method type.
     */
    public function getMethodCode()
    {
        return $this->methodCode;
    }

    /**
     * Sets the payment method type.
     *
     * @param string $methodCode
     * @return Api
     */
    public function setMethodCode($methodCode)
    {
        $this->methodCode = $methodCode;

        return $this;
    }

    /**
     * @return string The soap wsdl endpoint to send requests.
     */
    public function getSoapEndpoint()
    {
        return $this->wsdlUrl;
    }

    /**
     * @return string The redirect payment page url to be used for requests.
     */
    public function getRedirectUrl()
    {
        return sprintf($this->checkoutUrl, $this->getPayUReference());
    }

    /**
     * @return \PayU\EasyPlus\Model\Response The return data.
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Sets the redirect payment page url to be used for requests.
     */
    public function setGatewayEndpoint()
    {
        $methodCode = $this->getMethodCode();
        $environment = $this->scopeConfig->getValue(
            "payment/{$methodCode}/environment",
            ScopeInterface::SCOPE_STORE
        );
        if (!$environment) {
            $this->wsdlUrl = $this->sandboxUrl;
            $this->checkoutUrl = $this->sandboxCheckoutUrl;
        } else {
            $this->wsdlUrl = $this->liveUrl;
            $this->checkoutUrl = $this->liveCheckoutUrl;
        }
    }

    private function getSoapHeader()
    {
        $header  = '<wsse:Security SOAP-ENV:mustUnderstand="1" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">';
        $header .= '<wsse:UsernameToken wsu:Id="UsernameToken-9" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">';
        $header .= '<wsse:Username>' . $this->getUsername() . '</wsse:Username>';
        $header .= '<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">' . $this->getPassword() . '</wsse:Password>';
        $header .= '</wsse:UsernameToken>';
        $header .= '</wsse:Security>';

        return $header;
    }

    public function checkTransaction($reference)
    {
        $soapClient = $this->getSoapSingleton();

        $data['Api'] = $this->getApiVersion();
        $data['Safekey'] = $this->getSafeKey();
        $data['AdditionalInformation']['payUReference'] = $reference;

        $result = $soapClient->getTransaction($data);

        $this->response = $this->responseFactory->create();

        $this->response->setData('return', $result->return);

        return $this->response;
    }

    /**
     * @param $txn_id
     * @param AbstractPayment $redirectPayment
     * @return \PayU\EasyPlus\Model\Response
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function doGetTransaction($txn_id, AbstractPayment $redirectPayment)
    {
        $reference = isset($txn_id['PayUReference']) ? $txn_id['PayUReference'] : $txn_id;
        $this->setMethodCode($redirectPayment->getCode());
        $soapClient = $this->getSoapSingleton();

        $data['Api'] = $this->getApiVersion();
        $data['Safekey'] = $redirectPayment->getValue('safe_key');
        $data['AdditionalInformation']['payUReference'] = $reference;

        $redirectPayment->debugData(['request' => $data]);

        $result = $soapClient->getTransaction($data);

        $result = json_decode(json_encode($result));

        $this->response = $this->responseFactory->create();

        $this->response->setData('return', $result->return);

        return $this->response;
    }

    public function doSetTransaction($requestData)
    {
        $response = $this->getSoapSingleton()->setTransaction($requestData);

        return json_decode(json_encode($response));
    }

    private function getSoapSingleton()
    {
        if (is_null(self::$_soapClient)) {
            $this->setGatewayEndpoint();
            $header = $this->getSoapHeader();
            $soapWsdlUrl = $this->getSoapEndpoint() . '?wsdl';
            $this->wsdlUrl = $soapWsdlUrl;

            $headerBody = new \SoapVar($header, XSD_ANYXML, null, null, null);
            $soapHeader = new \SOAPHeader(self::$ns, 'Security', $headerBody, true);

            self::$_soapClient = new \SoapClient($soapWsdlUrl, ['trace' => 1, 'exception' => 0]);
            self::$_soapClient->__setSoapHeaders($soapHeader);
        }

        return self::$_soapClient;
    }

    /**
     * @param AbstractPayment $redirectPayment
     * @param InfoInterface $payment
     * @param $transactionId
     * @return \PayU\EasyPlus\Model\Response
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function fetchTransactionInfo(AbstractPayment $redirectPayment, InfoInterface $payment, $transactionId)
    {
        $response = $this->doGetTransaction($transactionId, $redirectPayment);

        $this->importPaymentInfo($this->response, $payment);

        return $response;
    }

    /**
     * Transfer transaction/payment information from API instance to order payment
     *
     * @param DataObject $from
     * @param InfoInterface $to
     * @return Api
     */
    public function importPaymentInfo(DataObject $from, InfoInterface $to)
    {
        /**
         * Detect payment review and/or frauds
         */
        if ($from->isFraudDetected()) {
            $to->setIsTransactionPending(true);
            $to->setIsFraudDetected(true);
        }

        // give generic info about transaction state
        if ($from->isPaymentSuccessful()) {
            $to->setIsTransactionApproved(true);
        } elseif ($from->isPaymentPending()) {
            $to->setIsTransactionPending(true);
        } elseif ($from->isPaymentProcessing()) {
            $to->setIsTransactionProcessing(true);
        } else {
            $to->setIsTransactionDenied(true);
        }

        return $this;
    }
}
