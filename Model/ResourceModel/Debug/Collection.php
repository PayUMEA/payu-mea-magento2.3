<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace PayU\EasyPlus\Model\ResourceModel\Debug;

/**
 * Resource PayU EasyPlus debug collection model
 */
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            'PayU\EasyPlus\Model\Debug',
            'PayU\EasyPlus\Model\ResourceModel\Debug'
        );
    }
}
