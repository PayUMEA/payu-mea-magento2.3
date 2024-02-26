<?php

namespace PayU\EasyPlus\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Registry;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PayU\EasyPlus\Model\Api\Api;
use PayU\EasyPlus\Model\Api\Factory;

class Index extends Action
{
    /**
     * @var string?
     */
    protected $code = null;

    /**
     * @var Api
     */
    protected $easyPlusApi;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Registry?
     */
    protected $coreRegistry = null;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    public function __construct(
        Context $context,
        Factory $apiFactory,
        EncryptorInterface $encryptor,
        StoreManagerInterface $storeManager,
        Registry $registry,
        ScopeConfigInterface $scopeConfig,
        OrderRepositoryInterface $orderRepository,
    ) {
        parent::__construct($context);

        $this->easyPlusApi = $apiFactory->create();
        $this->encryptor = $encryptor;
        $this->storeManager = $storeManager;
        $this->coreRegistry = $registry;
        $this->scopeConfig = $scopeConfig;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $orderId = $this->getRequest()->getPostValue('order_id');

        $order = $this->orderRepository->get($orderId);
        $payment = $order->getPayment();

        $this->code = $payment->getData('method');
        $additionalInfo = $payment->getData('additional_information');
        $payUReference = $additionalInfo["payUReference"];

        $this->initializeApi($order->getStoreId());
        $result = $this->easyPlusApi->checkTransaction($payUReference);
        $return = $result->getData('return');

        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        if (!$return) {
            $resultJson->setData([
                'message' => 'Could not determine order status',
                'success' => false
            ]);

            return $resultJson;
        }

        $resultJson->setData(['message' => 'Good', 'success' => true, 'data' => $return]);

        return $resultJson;
    }

    /**
     * @param int $storeId
     * @return void
     */
    protected function initializeApi($storeId)
    {
        $this->easyPlusApi->setSafeKey($this->getValue('safe_key', $storeId));
        $this->easyPlusApi->setUsername($this->getValue('api_username', $storeId));
        $this->easyPlusApi->setPassword($this->getValue('api_password', $storeId));
        $this->easyPlusApi->setMethodCode($this->code);
    }

    public function getValue($key, $storeId = null)
    {
        if (in_array($key, ['safe_key', 'api_password'])) {
            return $this->encryptor->decrypt($this->getConfigData($key, $storeId));
        }

        return $this->getConfigData($key, $storeId);
    }

    public function getConfigData($field, $storeId = null)
    {
        $path = 'payment/' . $this->code . '/' . $field;
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
