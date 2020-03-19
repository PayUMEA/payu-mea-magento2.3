<?php

namespace PayU\EasyPlus\Observer\Frontend;

use Magento\Sales\Model\Order;
use Magento\Framework\Event\ObserverInterface;

class SetPaymentInstanceToRegistry implements ObserverInterface
{
	/**
     *
     * @var \Magento\Framework\Registry
     */
    protected $coreRegistry;

    /**
     * @param \Magento\Framework\Registry $coreRegistry
     */
    public function __construct(
        \Magento\Framework\Registry $coreRegistry
    ) {
        $this->coreRegistry = $coreRegistry;
    }
	public function execute(\Magento\Framework\Event\Observer $observer)
	{
		/* @var $order Order */
        $order = $observer->getEvent()->getData('order');
        //$order->setState(Order::STATE_PENDING_PAYMENT);
        $this->coreRegistry->register('order', $order, true);
	}
}