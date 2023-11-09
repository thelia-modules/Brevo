<?php

namespace Brevo\Services;

use Brevo\Api\BrevoClient;
use Thelia\Log\Tlog;
use Thelia\Model\Base\ProductQuery;
use Thelia\Model\Cart;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\Product;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Tools\URL;
use function Clue\StreamFilter\fun;

class BrevoOrderService
{
    public function __construct(private BrevoApiService $brevoApiService)
    {
    }

    public function exportOrder(Order $order, $locale)
    {
        $data = $this->getOrderData($order, $locale);

        try {
            $this->brevoApiService->sendPostEvent('https://api.brevo.com/v3/orders/status', $data);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('Brevo order export error:'.$exception->getMessage());
        }
    }

    public function exportOrderInBatch($limit, $offset, $locale)
    {
        $orders = ProductQuery::create()
            ->setLimit($limit)
            ->setOffset($offset)
        ;

        $data = [];

        /** @var Order $order */
        foreach ($orders as $order) {
            $data[] = $this->getOrderData($order, $locale);
        }

        $batchData['orders'] = $data;

        try {
            $this->brevoApiService->sendPostEvent('https://api.brevo.com/v3/orders/status/batch', $batchData);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('Brevo order export error:'.$exception->getMessage());
        }
    }

    public function getOrderData(Order $order, $locale)
    {
        $invoiceAddress = $order->getOrderAddressRelatedByInvoiceOrderAddressId();
        $addressCountry = $invoiceAddress->getCountry();
        $addressCountry->setLocale($locale);

        $coupons = array_map(function ($coupon) {
            return $coupon['Code'];
        }, $order->getOrderCoupons()->toArray());

        return [
            'products' => $this->getOrderProductsData($order),
            'billing' => [
                'address' => $invoiceAddress->getAddress1(),
                'city' => $invoiceAddress->getCity(),
                'countryCode' => $addressCountry->getIsoalpha2(),
                'phone' => $invoiceAddress->getCellphone(),
                'postCode' => $invoiceAddress->getZipcode(),
                'paymentMethod' => $order->getPaymentModuleInstance()->getCode(),
                'region' => $addressCountry->getTitle(),
            ],
            'coupon' => $coupons,
            'id' => (string)$order->getId(),
            'createdAt' => $order->getCreatedAt()->format("Y-m-d\TH:m:s\Z"),
            'updatedAt' => $order->getUpdatedAt()->format("Y-m-d\TH:m:s\Z"),
            'status' => $order->getOrderStatus()->getCode(),
            'amount' => round($order->getTotalAmount($tax), 2),
            'email' => $order->getCustomer()->getEmail()
        ];
    }

    protected function getOrderProductsData(Order $order)
    {
        $orderProductsData = [];
        foreach ($order->getOrderProducts() as $orderProduct) {
            $pse = ProductSaleElementsQuery::create()->findPk($orderProduct->getProductSaleElementsId());
            $orderProductsData[] = [
                'productId' => (string)$pse->getId(),
                'quantity' => $orderProduct->getQuantity(),
                'variantId' => (string)$orderProduct->getProductSaleElementsId(),
                'price' => round((float)$orderProduct->getPrice(), 2)
            ];
        }

        return $orderProductsData;
    }

}