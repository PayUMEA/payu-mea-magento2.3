<?php
/**
 * PayU_EasyPlus data helper model
 *
 * @category    PayU
 * @package     PayU_EasyPlus
 * @author      Kenneth Onah
 * @copyright   PayU South Africa (http://payu.co.za)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace PayU\EasyPlus\Block;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Info;
use PayU\EasyPlus\Helper\DataFactory;

/**
 * Class Iframe
 */
class Main extends Info
{
    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var DataFactory
     */
    protected $dataFactory;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * Constructor
     *
     * @param Context $context
     * @param Registry $registry
     * @param DataFactory $dataFactory
     * @param ManagerInterface $messageManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        DataFactory $dataFactory,
        ManagerInterface $messageManager,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->dataFactory = $dataFactory;
        $this->messageManager = $messageManager;

        parent::__construct($context, $data);
    }

    /**
     * Get helper data
     *
     * @param string $area
     * @throws LocalizedException
     * @return \PayU\EasyPlus\Helper\Backend\Data|\PayU\EasyPlus\Helper\Data
     */
    public function getHelper($area)
    {
        return $this->dataFactory->create($area);
    }

    /**
     * {inheritdoc}
     */
    protected function _beforeToHtml()
    {
        $this->addSuccessMessage();

        return parent::_beforeToHtml();
    }

    /**
     * Add success message
     *
     * @return void
     */
    private function addSuccessMessage()
    {
        $params = $this->getParams();
        if (isset($params['PayUReference'])) {
            $this->messageManager->addSuccess(__('Redirecting to payment gateway...Please wait'));
        }
    }
}
