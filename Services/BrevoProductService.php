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
        $data = $this->getData($product, $locale, $currency, $country);
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
            $data[] = $this->getData($product, $locale, $currency, $country);
        }

        $batchData['products'] = $data;
        $batchData['updateEnabled'] = true;

        try {
            $this->brevoApiService->sendPostEvent('https://api.brevo.com/v3/products/batch', $batchData);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('Brevo product export error:'.$exception->getMessage());
        }
    }

    public function getData(Product $product, $locale, Currency $currency, Country $country)
    {
        $product->setLocale($locale);

        $productPrice = $product->getDefaultSaleElements()->getPricesByCurrency($currency);
        $productSku = $product->getDefaultSaleElements()->getEanCode();
        $categories = $product->getCategories();
        $categoryIds = array_map(function ($category) {
            return (string) $category['Id'];
        }, $categories->toArray());

        // Get first product image
        $imageUrl = null;

        if (null !== $productImage = ProductImageQuery::create()
            ->filterByProductId($product->getId())
            ->filterByVisible(1)
            ->orderBy('position')->findOne()
        ) {
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
                $imageUrl = $event->getFileUrl();
            } catch (\Exception $ex) {
                // Ignore the result
                $a = 1;
            }
        }

        return [
            'categories' => $categoryIds,
            'id' => (string) $product->getId(),
            'name' => $product->getTitle(),
            'url' => $product->getUrl($locale),
            'image' => $imageUrl,
            'sku' => $productSku ?? $product->getRef(),
            'price' => round((float) $product->getTaxedPrice($country, $productPrice->getPrice()), 2),
            'metaInfo' => $this->getMappedValues(
                $this->metaDataMapping,
                'product_query',
                'product',
                'product.id',
                $product->getId(),
            ),
        ];
    }

    public function getItemsByOrder(Order $order, $locale)
    {
        $data = [];

        /** @var OrderProduct $orderProduct */
        foreach ($order->getOrderProducts() as $orderProduct) {
            $pse = ProductSaleElementsQuery::create()->findPk($orderProduct->getProductSaleElementsId());

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
                $productData['image'] = $imagePath ? URL::getInstance()?->absoluteUrl('/cache/images/product/'.$imagePath) : null;
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
                'image' => $imagePath ? URL::getInstance()?->absoluteUrl('/cache/images/product/'.$imagePath) : null,
            ];
        }

        return $data;
    }
}
