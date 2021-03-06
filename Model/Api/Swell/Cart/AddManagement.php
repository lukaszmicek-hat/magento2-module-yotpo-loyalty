<?php

namespace Yotpo\Loyalty\Model\Api\Swell\Cart;

class AddManagement implements \Yotpo\Loyalty\Api\Swell\Cart\AddManagementInterface
{

    /**
     * @var \Yotpo\Loyalty\Helper\Data
     */
    protected $_yotpoHelper;

    /**
     * @var \Yotpo\Loyalty\Helper\Schema
     */
    protected $_yotpoSchemaHelper;

    /**
     * @var \Magento\Quote\Model\QuoteFactory
     */
    protected $_quoteFactory;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $_quoteRepository;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $_productRepository;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $_customerFactory;

    /**
     * @var \Magento\Quote\Model\QuoteRepository\SaveHandler
     */
    protected $_quoteRepositorySaveHandler;

    /**
     * @param \Yotpo\Loyalty\Model\Api\Swell\Guard $swellApiGuard
     * @param \Yotpo\Loyalty\Helper\Data $yotpoHelper
     * @param \Yotpo\Loyalty\Helper\Schema $yotpoSchemaHelper
     * @param \Magento\Quote\Model\QuoteFactory $quoteFactory
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Customer\Model\CustomerFactory $customerFactory
     * @param \Magento\Quote\Model\QuoteRepository\SaveHandler $quoteRepositorySaveHandler
    */
    public function __construct(
        \Yotpo\Loyalty\Model\Api\Swell\Guard $swellApiGuard,
        \Yotpo\Loyalty\Helper\Data $yotpoHelper,
        \Yotpo\Loyalty\Helper\Schema $yotpoSchemaHelper,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Quote\Model\QuoteRepository\SaveHandler $quoteRepositorySaveHandler
    ) {
        //$swellApiGuard will be initialized from it's __construct
        $this->_yotpoHelper = $yotpoHelper;
        $this->_yotpoSchemaHelper = $yotpoSchemaHelper;
        $this->_quoteFactory = $quoteFactory;
        $this->_quoteRepository = $quoteRepository;
        $this->_productRepository = $productRepository;
        $this->_customerFactory = $customerFactory;
        $this->_quoteRepositorySaveHandler = $quoteRepositorySaveHandler;
    }

    /**
     * {@inheritdoc}
     */
    public function getAdd()
    {
        try {
            //Extract Request Params:
            $quoteId = intval($this->_yotpoHelper->getRequest()->getParam('quote_id'));
            $sku = $this->_yotpoHelper->getRequest()->getParam('sku');
            $price = floatval($this->_yotpoHelper->getRequest()->getParam('price'));
            $qty = floatval($this->_yotpoHelper->getRequest()->getParam('qty'));
            $redemptionId = intval($this->_yotpoHelper->getRequest()->getParam('redemption_id'));
            $pointsUsed = intval($this->_yotpoHelper->getRequest()->getParam('points_used'));
            //================================================================//

            if (!is_numeric($price)) {
                $price = 0.0;
            }
            if (!is_numeric($qty)) {
                $qty = 1;
            }

            $quote = $this->_quoteFactory->create()->load($quoteId);
            if (!$quote->getId()) {
                $this->_yotpoHelper->sendApiJsonResponse([
                    "error" => 'There is no quote with this quote_id'
                ]);
            }
            $couponCode = $quote->getCouponCode();
            if ($couponCode) {
                $quote->setCouponCode("")->setTotalsCollectedFlag(false)->collectTotals()->save()->load($quoteId);
            }
            $product = $this->_productRepository->get($sku);
            if (!$product->getId()) {
                $this->_yotpoHelper->sendApiJsonResponse([
                    "error" => 'There is no product with this SKU'
                ]);
            }

            $request = new \Magento\Framework\DataObject([
                'product' => $product->getId(),
                'qty' => $qty,
                'custom_price' => $price,
                'original_custom_price' => $price,
                'swell_redemption_id' => $redemptionId,
                'swell_points_used' => $pointsUsed
            ]);
            $quoteItem = $quote->addProduct($product, $request);
            $quoteItem
                ->setCustomPrice($price)
                ->setOriginalCustomPrice($price)
                ->setSwellRedemptionId($redemptionId)
                ->setSwellPointsUsed($pointsUsed)
                ->save();

            $quote->save();
            $quote->setTotalsCollectedFlag(false)->collectTotals()->save();

            if ($quote->getCustomerId()) {
                $customer = $this->_customerFactory->create()->load($quote->getCustomerId());
                if ($customer->getId()) {
                    $customer->setData('yotpo_force_cart_reload', 1);
                    $customerData = $customer->getDataModel();
                    $customerData->setData('yotpo_force_cart_reload', 1);
                    $customer->updateData($customerData);
                    $customer->save();
                }
            }

            if ($couponCode) {
                try {
                    $quote->setCouponCode($couponCode)->setTotalsCollectedFlag(false)->collectTotals()->save()->load($quoteId);
                } catch (\Exception $e) {
                    $this->_yotpoHelper->sendApiJsonResponse([
                        "success" => true,
                        "message" => "[Yotpo API - Add(ToCart) - WARNING] " . $e->getMessage()
                    ]);
                }
            }

            $this->_yotpoHelper->sendApiJsonResponse([
                "success" => true
            ]);
        } catch (\Exception $e) {
            $this->_yotpoHelper->log("[Yotpo API - Add(ToCart) - ERROR] " . $e->getMessage() . "\n" . print_r($e->getTraceAsString(), true), "error");
            $this->_yotpoHelper->sendApiJsonResponse([
                "error" => 'An error has occurred trying to add item to cart'
            ]);
        }
        $this->_yotpoHelper->sendApiJsonResponse([]);
    }
}
