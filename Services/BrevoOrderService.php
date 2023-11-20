<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Brevo\Services;

use Thelia\Log\Tlog;
use Thelia\Model\Base\ProductQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\ProductSaleElementsQuery;

class BrevoOrderService
{
    public function __construct(
        private BrevoApiService $brevoApiService,
        private BrevoProductService $brevoProductService,
    )
    {
    }

    public function exportOrder(Order $order, $locale): void
    {
        $data = $this->getOrderData($order, $locale);

        try {
            $this->brevoApiService->sendPostEvent('https://api.brevo.com/v3/orders/status', $data);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('Brevo order export error:'.$exception->getMessage());
        }
    }

    public function exportOrderInBatch($limit, $offset, $locale): void
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

    protected function getOrderData(Order $order, $locale)
    {
        $invoiceAddress = $order->getOrderAddressRelatedByInvoiceOrderAddressId();
        $addressCountry = $invoiceAddress->getCountry();
        $addressCountry->setLocale($locale);

        $coupons = array_map(function ($coupon) {
            return $coupon['Code'];
        }, $order->getOrderCoupons()->toArray());

        return [
            'email' => $order->getCustomer()->getEmail(),
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
            'id' => $order->getRef(),
            'createdAt' => $order->getCreatedAt()?->format("Y-m-d\TH:m:s\Z"),
            'updatedAt' => $order->getUpdatedAt()?->format("Y-m-d\TH:m:s\Z"),
            'status' => $order->getOrderStatus()->getCode(),
            'amount' => round($order->getTotalAmount($tax), 2),
        ];
    }

    protected function getOrderProductsData(Order $order)
    {
        $orderProductsData = [];
        foreach ($order->getOrderProducts() as $orderProduct) {
            $orderProductsData[] = [
                'productId' => $orderProduct->getProductRef(),
                'quantity' => $orderProduct->getQuantity(),
                // 'variantId' => (string) $orderProduct->getProductSaleElementsId(),
                'price' => round((float) $orderProduct->getPrice(), 2),
            ];
        }

        return $orderProductsData;
    }
}
