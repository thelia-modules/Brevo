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

use Brevo\Brevo;
use Brevo\Trait\DataExtractorTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;
use Thelia\Model\Base\ProductQuery;
use Thelia\Model\Cart;
use Thelia\Model\ConfigQuery;
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
    use DataExtractorTrait;

    protected string $baseSourceFilePath;

    protected array $metaDataMapping = [];

    public function __construct(private BrevoApiService $brevoApiService, protected EventDispatcherInterface $dispatcher)
    {
        if (null === $this->baseSourceFilePath = ConfigQuery::read('images_library_path', null)) {
            $this->baseSourceFilePath = THELIA_LOCAL_DIR.'media'.DS.'images';
        } else {
            $this->baseSourceFilePath = THELIA_ROOT.$this->baseSourceFilePath;
        }

        $mappingString = ConfigQuery::read(Brevo::BREVO_METADATA_MAPPING);

        if (!empty($mappingString) && null === $this->metaDataMapping = json_decode($mappingString, true)) {
            throw new TheliaProcessException('Product metadata mapping error: JSON data seems invalid, please check syntax.');
        }
    }

    public function getObjName()
    {
        return 'product';
    }

    public function getCount()
    {
        return ProductQuery::create()->count();
    }

    public function export(Product $product, $locale, Currency $currency, Country $country): void
    {
        $data = $this->getProductItem($product, $locale, $currency, $country);
        $data['updateEnabled'] = true;

        try {
            $this->brevoApiService->sendPostEvent('https://api.brevo.com/v3/products', $data);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('Brevo product export error:'.$exception->getMessage());
        }
    }

    public function exportInBatch($limit, $offset, $locale, Currency $currency, Country $country): void
    {
        $products = ProductQuery::create()
            ->setLimit($limit)
            ->setOffset($offset)
        ;

        $data = [];

        /** @var Product $product */
        foreach ($products as $product) {
            $data[] = $this->getProductItem($product, $locale, $currency, $country);
        }

        $batchData['products'] = $data;
        $batchData['updateEnabled'] = true;

        try {
            $this->brevoApiService->sendPostEvent('https://api.brevo.com/v3/products/batch', $batchData);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('Brevo product export error:'.$exception->getMessage());
        }
    }

    protected function getProductItem(Product $product, $locale, Currency $currency, Country $country)
    {
        $product->setLocale($locale);
        $productPrice = $product->getDefaultSaleElements()->getPricesByCurrency($currency);

        return $this->getProductData(
            $product->getTitle(),
            $product->getDescription(),
            $product->getDefaultSaleElements()->getEanCode(),
            $product->getRef(),
            $productPrice->getPrice(),
            $productPrice->getPromoPrice(),
            $product->getTaxedPrice($country, $productPrice->getPrice()),
            $product->getTaxedPromoPrice($country, $productPrice->getPrice()),
            $product->getDefaultSaleElements()->getPromo(),
            $currency,
            $product
        );
    }

    public function getCartItems(Cart $cart, $locale)
    {
        $data = [];

        $country = $cart->getCustomer()?->getDefaultAddress()->getCountry() ?? Country::getDefaultCountry();

        foreach ($cart->getCartItems() as $cartItem) {
            $product = $cartItem->getProduct()->setLocale($locale);

            $cartItemData = $this->getProductData(
                $product->getTitle(),
                $product->getDescription(),
                $product->getDefaultSaleElements()->getEanCode(),
                $product->getRef(),
                (float) $cartItem->getPrice(),
                (float) $cartItem->getPromoPrice(),
                $cartItem->getTaxedPrice($country),
                $cartItem->getTaxedPromoPrice($country),
                $cartItem->getPromo(),
                $cart->getCurrency(),
                $product
            );

            // Add quantity
            $cartItemData['quantity'] = $cartItem->getQuantity();

            $data[] = $cartItemData;
        }

        return $data;
    }

    public function getOrderItems(Order $order)
    {
        $data = [];

        $currency = $order->getCurrency();

        /** @var OrderProduct $orderProduct */
        foreach ($order->getOrderProducts() as $orderProduct) {
            $pse = ProductSaleElementsQuery::create()->findPk($orderProduct->getProductSaleElementsId());

            $totalTaxes = $totalPromoTaxes = 0;

            // Get tax total
            foreach ($orderProduct->getOrderProductTaxes() as $orderProductTax) {
                $totalTaxes += (float) $orderProductTax->getAmount();
                $totalPromoTaxes += (float) $orderProductTax->getPromoAmount();
            }

            $productData = $this->getProductData(
                $orderProduct->getTitle(),
                $orderProduct->getDescription(),
                $orderProduct->getEanCode(),
                $orderProduct->getProductRef(),
                (float) $orderProduct->getPrice(),
                (float) $orderProduct->getPromoPrice(),
                (float) $orderProduct->getPrice() + $totalTaxes,
                (float) $orderProduct->getPromoPrice() + $totalPromoTaxes,
                $orderProduct->getWasInPromo(),
                $currency,
                $pse?->getProduct()
            );

            // Add quantity
            $productData['quantity'] = $orderProduct->getQuantity();

            $data[] = $productData;
        }

        return $data;
    }

    protected function getProductData(
        $title, $description, $eanCode, $ref,
        float $price, float $promoPrice,
        float $taxedPrice, float $taxedPromoPrice,
        bool $isPromo,
        ?Currency $currency,
        ?Product $product): array
    {
        if (!$isPromo) {
            $promoPrice = $price;
        }

        $price = round($price, 2);
        $promoPrice = round($promoPrice, 2);
        $taxedPrice = round($taxedPrice, 2);
        $taxedPromoPrice = round($taxedPromoPrice, 2);
        $discount = round(100 * (1 - $promoPrice / $price), 2);

        $productData = [
            'id' => $ref,
            'name' => $title,
            'description' => $description,
            'sku' => $eanCode ?? $ref,
            'price' => $promoPrice,
            'price_taxinc ' => $taxedPromoPrice,
            'oldPrice' => $price,
            'oldPrice_taxinc' => $taxedPrice,
            'discount' => $discount,
            'currency' => $currency?->getCode(),
        ];

        if (null !== $product) {
            $categories = $product->getCategories();
            $categoryIds = array_map(function ($category) {
                return (string) $category['Id'];
            }, $categories->toArray());

            $productData['categories'] = $categoryIds;
            $productData['url'] = URL::getInstance()?->absoluteUrl($product->getUrl());
            $productData['imageUrl'] = $this->getProductImageUrl($product);
            $productData['metaInfo'] = $this->getMappedValues(
                $this->metaDataMapping,
                'product_metadata_query',
                'product',
                'product.id',
                $product->getId(),
            );

            // Add (or override) standard fields
            $mappedFields = $this->getMappedValues(
                $this->metaDataMapping,
                'product_query',
                'product',
                'product.id',
                $product->getId(),
            );

            $productData = array_merge($productData, $mappedFields);
        }

        return $productData;
    }

    protected function getProductImageUrl(Product $product): ?string
    {
        if (null === $productImage = ProductImageQuery::create()
                ->filterByProductId($product->getId())
                ->filterByVisible(1)
                ->orderBy('position')->findOne()
        ) {
            return null;
        }

        // Put source image file path
        $sourceFilePath = sprintf(
            '%s/%s/%s',
            $this->baseSourceFilePath,
            'product',
            $productImage->getFile()
        );

        // Create image processing event
        $event = (new ImageEvent())
            ->setSourceFilepath($sourceFilePath)
            ->setCacheSubdirectory('product')
        ;

        try {
            // Dispatch image processing event
            $this->dispatcher->dispatch($event, TheliaEvents::IMAGE_PROCESS);

            return $event->getFileUrl();
        } catch (\Exception $ex) {
            // Ignore the result
        }

        return null;
    }
}
