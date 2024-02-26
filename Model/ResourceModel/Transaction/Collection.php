<?php
/**
 * Copyright © 2024 PayU Financial Services. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace PayU\EasyPlus\Model\ResourceModel\Transaction;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use PayU\EasyPlus\Model\Transaction;

/**
 * Resource PayU EasyPlus debug collection model
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';
    protected $_eventPrefix = 'payu_easyplus_transaction_collection';
    protected $_eventObject = 'transaction_collection';

    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            Transaction::class,
            \PayU\EasyPlus\Model\ResourceModel\Transaction::class
        );
        //$this->_map['fields']['entity_id'] = 'main_table.entity_id';
    }
}
