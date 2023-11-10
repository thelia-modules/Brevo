<?php

namespace Brevo\Services;

use Thelia\Log\Tlog;
use Thelia\Model\Base\ProductQuery;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\Product;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Tools\URL;

class BrevoProductService
{
    public function __construct(private BrevoApiService $brevoApiService)
    {
    }

    public function exportProduct(Product $product, $locale, Currency $currency, Country $country)
    {
        $data = $this->getProductData($product, $locale, $currency, $country);
        $data['updateEnabled'] = true;

        try {
            $this->brevoApiService->sendPostEvent('https://api.brevo.com/v3/products', $data);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('Brevo product export error:'.$exception->getMessage());
        }
    }

    public function exportProductInBatch($limit, $offset, $locale, Currency $currency, Country $country)
    {
        $products = ProductQuery::create()
            ->setLimit($limit)
            ->setOffset($offset)
        ;

        $data = [];

        /** @var Product $product */
        foreach ($products as $product) {
            $data[] = $this->getProductData($product, $locale, $currency, $country);
        }

        $batchData['products'] = $data;
        $batchData['updateEnabled'] = true;

        try {
            $this->brevoApiService->sendPostEvent('https://api.brevo.com/v3/products/batch', $batchData);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('Brevo product export error:'.$exception->getMessage());
        }
    }

    public function getProductData(Product $product, $locale, Currency $currency, Country $country)
    {
        $product->setLocale($locale);
        $imagePath = ProductImageQuery::create()->filterByProductId($product->getId())->orderByPosition()->findOne()?->getFile();

        $productPrice = $product->getDefaultSaleElements()->getPricesByCurrency($currency);
        $categories = $product->getCategories();
        $categoryIds = array_map(function ($category){
            return (string)$category['Id'];
        }, $categories->toArray());

         return [
            'categories' => $categoryIds,
            'id' => (string)$product->getId(),
            'name' => $product->getTitle(),
            'url' => URL::getInstance()?->absoluteUrl($product->getUrl()),
            'image' => $imagePath ? URL::getInstance()?->absoluteUrl('/cache/images/product/' . $imagePath) : null,
            'sku' => $product->getRef(),
            'price' => round((float)$product->getTaxedPrice($country, $productPrice->getPrice()), 2)
        ];
    }


    public function getItemsByOrder(Order $order, $locale)
    {
        $data = [];

        /** @var OrderProduct $orderProduct */
        foreach ($order->getOrderProducts() as $orderProduct) {

            $pse  = ProductSaleElementsQuery::create()->findPk($orderProduct->getProductSaleElementsId());

            $productData = [
                'name' => $orderProduct->getTitle(),
                'ref' => $orderProduct->getProductRef(),
                'price' => round($orderProduct->getPrice(), 2),
                'quantity' => $orderProduct->getQuantity(),
            ];

            if (null !== $pse) {
                $product = $pse->getProduct();
                $imagePath = ProductImageQuery::create()->filterByProductId($pse->getProductId())->orderByPosition()->findOne()?->getFile();

                $productData['url'] = URL::getInstance()?->absoluteUrl($product->getUrl());
                $productData['image'] = $imagePath ? URL::getInstance()?->absoluteUrl('/cache/images/product/' . $imagePath) : null;
            }

            $data[] = $productData;
        }

        return $data;
    }
    public function getItemsByCart(Cart $cart, $locale)
    {
        $data = [];

        foreach ($cart->getCartItems() as $cartItem) {
            $product = $cartItem->getProduct();
            $product->setLocale($locale);

            $imagePath = ProductImageQuery::create()->filterByProductId($product->getId())->orderByPosition()->findOne()?->getFile();

            $data[] = [
                'name' => $product->getTitle(),
                'ref' => $cartItem->getProduct()->getRef(),
                'price' => round($cartItem->getPrice(), 2),
                'quantity' => $cartItem->getQuantity(),
                'url' => URL::getInstance()?->absoluteUrl($cartItem->getProduct()->getUrl()),
                'image' => $imagePath ? URL::getInstance()?->absoluteUrl('/cache/images/product/' . $imagePath) : null
            ];
        }

        return $data;
    }

}