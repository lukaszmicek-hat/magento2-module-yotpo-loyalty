<?php

namespace Yotpo\Loyalty\Model\Api\Swell\Index;

class ThirtyDayOrderVolumeManagement implements \Yotpo\Loyalty\Api\Swell\Index\ThirtyDayOrderVolumeManagementInterface
{

    /**
     * @var \Yotpo\Loyalty\Helper\Data
     */
    protected $_yotpoHelper;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $_orderCollectionFactory;

    /**
     * @param \Yotpo\Loyalty\Model\Api\Swell\Guard $swellApiGuard
     * @param \Yotpo\Loyalty\Helper\Data $yotpoHelper
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
    */
    public function __construct(
        \Yotpo\Loyalty\Model\Api\Swell\Guard $swellApiGuard,
        \Yotpo\Loyalty\Helper\Data $yotpoHelper,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
    ) {
        //$swellApiGuard will be initialized from it's __construct
        $this->_yotpoHelper = $yotpoHelper;
        $this->_orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getThirtyDayOrderVolume()
    {
        $collection = $this->_orderCollectionFactory->create()
            ->addAttributeToSelect('entity_id')
            ->addAttributeToFilter("store_id", ["in" => $this->_yotpoHelper->getStoreIdsBySwellApiKey()])
            ->addAttributeToFilter('created_at', ['from' => date('Y-m-d', strtotime("-30 day"))]);

        $orderStates = array_filter(explode(',', $this->_yotpoHelper->getRequest()->getParam('state')));
        if (!empty($orderStates)) {
            $collection->addAttributeToFilter('state', ["in" => $orderStates]);
        }

        $this->_yotpoHelper->sendApiJsonResponse([
            "orders" => $collection->count()
        ]);
    }
}
