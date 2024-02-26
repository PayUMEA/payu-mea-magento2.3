<?php
/**
 * Copyright © 2024 PayU Financial Services. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace PayU\EasyPlus\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use PayU\EasyPlus\Model\Api\Data\GridInterface;

/**
 */
class Transaction extends AbstractModel implements IdentityInterface, GridInterface
{
    /**
     * Name of object id field
     *
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * BlogManager Blog cache tag.
     */
    const CACHE_TAG = 'payu_payu_transaction';

    /**
     * @var string
     */
    protected $_cacheTag = 'payu_payu_transaction';
    /**
     * @var string
     */
    protected $_eventPrefix = 'payu_payu_transaction';

    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Transaction::class);
    }

    /**
     * Get Identities
     *
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * Get Increment Id
     *
     * @return string|null
     */
    public function getIncrementId()
    {
        return parent::getData(self::INCREMENT_ID);
    }

    /**
     * Set Increment Id
     *
     * @param string $incrementId
     * @return Transaction
     */
    public function setIncrementId($incrementId)
    {
        return $this->setData(self::INCREMENT_ID, $incrementId);
    }

    /**
     * Get Lock
     *
     * @return bool
     */
    public function getLock()
    {
        return parent::getData(self::LOCK);
    }

    /**
     * Set Lock
     *
     * @param bool $lock
     * @return Transaction
     */
    public function setLock($lock)
    {
        return $this->setData(self::LOCK, $lock);
    }

    /**
     * Get Status
     *
     * @return string
     */
    public function getStatus()
    {
        return parent::getData(self::STATUS);
    }

    /**
     * Set Status
     *
     * @param string $status
     * @return Transaction
     */
    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * Get Process Id
     *
     * @return string
     */
    public function getProcessId()
    {
        return parent::getData(self::PROCESS_ID);
    }

    /**
     * Set Process Id
     *
     * @param string $processId
     * @return Transaction
     */
    public function setProcessId($processId)
    {
        return $this->setData(self::PROCESS_ID, $processId);
    }

    /**
     * Get Process Class
     *
     * @return string
     */
    public function getProcessClass()
    {
        return parent::getData(self::PROCESS_CLASS);
    }

    /**
     * Set Process Class
     *
     * @param string $processClass
     * @return Transaction
     */
    public function setProcessClass($processClass)
    {
        return $this->setData(self::PROCESS_CLASS, $processClass);
    }

    /**
     * Get CreatedAt.
     *
     * @return varchar
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }
    /**
     * Set CreatedAt.
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }
}
