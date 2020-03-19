<?php


namespace PayU\EasyPlus\Controller\Adminhtml\Index;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\PageFactory;

class Index extends \Magento\Backend\App\Action
{
    /** @var \PayU\EasyPlus\Model\Api\Api  */
    protected $_easyPlusApi;

    /** @var \Magento\Framework\Encryption\EncryptorInterface  */
    protected $_encryptor;

    /** @var \Magento\Store\Model\StoreManagerInterface  */
    protected $_storeManager;

    protected $_scopeConfig;


    /** @var \Magento\Framework\Registry|null  */
    protected $coreRegistry = null;

    /** @var null  */
    protected $_code = null;

    /** @var null  */
    protected $_payUReference = null;

    public function __construct(
        Context $context,
        \PayU\EasyPlus\Model\Api\Factory $apiFactory,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {
        parent::__construct($context);
        $this->_easyPlusApi = $apiFactory->create();
        $this->_encryptor = $encryptor;
        $this->_storeManager = $storeManager;
        $this->coreRegistry = $registry;
        $this->_scopeConfig = $scopeConfig;
    }


    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        // We need an order number
        $order_id = $this->getRequest()->getPostValue('order_id');

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->create('\Magento\Sales\Model\Order')->load($order_id);
        $payment = $order->getPayment();

        $this->_code = $payment->getData('method');

        $additional_info = $payment->getData('additional_information');

        $this->_payUReference = $additional_info["fraud_details"]["return"]["payUReference"];


        // We must get some config settings
        $this->initializeApi();

        $result = $this->_easyPlusApi->checkTransaction($this->_payUReference);

        $return = $result->getData('return');

        if(!$return->successful) {
            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $resultJson->setData(["message" => "Could not determine order status", "suceess" => false]);
            return $resultJson;
        }


        $test = 1;

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData(["message" => ("Good"), "suceess" => true]);
        return $resultJson;
    }



    protected function initializeApi()
    {
        $this->_easyPlusApi->setSafeKey($this->getValue('safe_key'));
        $this->_easyPlusApi->setUsername($this->getValue('api_username'));
        $this->_easyPlusApi->setPassword($this->getValue('api_password'));
        $this->_easyPlusApi->setMethodCode($this->_code);
    }


    public function getValue($key, $storeId = null)
    {
        if(in_array($key, ['safe_key', 'api_password']))
            return $this->_encryptor->decrypt($this->getConfigData($key, $storeId));

        return $this->getConfigData($key, $storeId);
    }


    public function getConfigData($field, $storeId = null)
    {
        $path = 'payment/' . $this->_code . '/' . $field;
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }









}
