<?php

namespace PayU\EasyPlus\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PayU\EasyPlus\Model\ResourceModel\Transaction\Collection;
use PayU\EasyPlus\Model\ResourceModel\Transaction\CollectionFactory;

class CleanTransactionLog
{
    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $collectionFactory;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $collectionFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->collectionFactory = $collectionFactory;
    }

    public function execute()
    {
        if (!$this->getConfigValue('enable')) {
            return;
        }

        $logs = $this->getLogCollection();

        foreach ($logs->getItems() as $log) {
            $log->setId($log->getEntityId());
            $log->delete();
        }
    }

    /**
     * @return Collection
     */
    public function getLogCollection(): Collection
    {
        return $this->collectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter(
                'created_at',
                [
                    ['lteq' => $this->daysFilter()],
                    ['null' => true]
                ]
            );
    }

    /**
     * @return false|string
     */
    protected function daysFilter()
    {
        $days = (int)$this->getConfigValue('keep_log');
        $to = date("Y-m-d h:i:s");
        $from = strtotime("-$days days", strtotime($to));

        return date('Y-m-d h:i:s', $from);
    }

    /**
     * @param string $field
     * @param null|int|string $storeId
     * @return mixed
     */
    protected function getConfigValue(string $field, $storeId = null)
    {
        $path = 'payumea/txn_log/' . $field;

        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
