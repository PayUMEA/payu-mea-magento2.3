<?php

namespace PayU\EasyPlus\Block\Adminhtml\Order\View\Tab;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;

class Custom extends Template implements TabInterface
{
    /**
     * Template
     *
     * @var string
     */
    protected $_template = 'PayU_EasyPlus::order/view/tab/custom.phtml';

    /**
     * Core registry
     *
     * @var Registry
     */
    protected $coreRegistry = null;

    /**
     * @var FormKey
     */
    protected $formKey;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormKey $formKey
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormKey $formKey,
        array $data = []
    ) {
        $this->coreRegistry = $registry;

        parent::__construct($context, $data);

        $this->formKey = $formKey;
    }

    /**
     * Retrieve order model instance
     *
     * @return Order
     */
    public function getOrder()
    {
        return $this->coreRegistry->registry('current_order');
    }

    /**
     * {@inheritdoc}
     */
    public function getTabLabel()
    {
        return __('PayU Transaction');
    }

    /**
     * {@inheritdoc}
     */
    public function getTabTitle()
    {
        return __('PayU Transaction');
    }

    /**
     * {@inheritdoc}
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isHidden()
    {
        return false;
    }

    /**
     * Get Tab Class
     *
     * @return string
     */
    public function getTabClass()
    {
        return 'ajax only';
    }

    /**
     * Get Class
     *
     * @return string
     */
    public function getClass()
    {
        return $this->getTabClass();
    }

    /**
     * Get Tab Url
     *
     * @return string
     */
    public function getTabUrl()
    {
        return $this->getUrl('payu_easyplus/*/customTab', ['_current' => true]);
    }

    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }

    public function getAjaxUrl()
    {
        return $this->getUrl('payu_easyplus/index/index');
    }
}
