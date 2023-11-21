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
use Propel\Runtime\Exception\PropelException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
use TheliaLibrary\Model\LibraryItemImageQuery;
use TheliaLibrary\Service\LibraryImageService;

class BrevoProductService
{
    use DataExtractorTrait;

    protected string $baseSourceFilePath;

    protected array $metaDataMapping = [];

    protected ?LibraryImageService $libraryImageService;

    public function __construct(
        protected BrevoApiService $brevoApiService,
        protected EventDispatcherInterface $dispatcher,
        ContainerInterface $container
    ) {
        if (null === $this->baseSourceFilePath = ConfigQuery::read('images_library_path')) {
            $this->baseSourceFilePath = THELIA_LOCAL_DIR.'media'.DS.'images';
        } else {
            $this->baseSourceFilePath = THELIA_ROOT.$this->baseSourceFilePath;
        }

        $mappingString = ConfigQuery::read(Brevo::BREVO_METADATA_MAPPING);

        if (!empty($mappingString) && null === $this->metaDataMapping = json_decode($mappingString, true)) {
            throw new TheliaProcessException('Product metadata mapping error: JSON data seems invalid, please check syntax.');
        }

        // Set image service manually, just in case the TheliaLibrary module is not enabled.
        $this->libraryImageService = $container->get('thelia_library_image', ContainerInterface::NULL_ON_INVALID_REFERENCE);
    }

    public function getObjName(): string
    {
        return 'product';
    }

    public function getCount(): int
    {
        return ProductQuery::create()->count();
    }

    /**
     * @param Product $product
     * @param $locale
     * @param Currency $currency
     * @param Country $country
     * @return void
     * @throws PropelException
     */
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

    /**
     * @param $limit
     * @param $offset
     * @param $locale
     * @param Currency $currency
     * @param Country $country
     * @return void
     * @throws PropelException
     */
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

    /**
     * @param Product $product
     * @param $locale
     * @param Currency $currency
     * @param Country $country
     * @return array
     * @throws PropelException
     */
    protected function getProductItem(Product $product, $locale, Currency $currency, Country $country): array
    {
        $product->setLocale($locale);
        $productPrice = $product->getDefaultSaleElements()->getPricesByCurrency($currency);

        $productData = [
            'id' => $product->getRef(),
            'name' => $product->getTitle(),
            'url' => URL::getInstance()?->absoluteUrl($product->getUrl()),
            'sku' => $product->getDefaultSaleElements()->getEanCode() ?? $product->getRef(),
            'imageUrl' => $this->getProductImageUrl($product),
            'categories' => $this->getProductCategories($product),
            'price' => $product->getTaxedPrice($country, $productPrice->getPrice()),
            'metaInfo' => $this->getMappedValues(
                $this->metaDataMapping,
                'product_metadata_query',
                'product',
                'product.id',
                $product->getId(),
            ),
        ];

        // Add (or override) standard fields
        $mappedFields = $this->getMappedValues(
            $this->metaDataMapping,
            'product_query',
            'product',
            'product.id',
            $product->getId(),
        );

        return array_merge($productData, $mappedFields);
    }

    /**
     * @param Cart $cart
     * @param $locale
     * @return array
     * @throws PropelException
     */
    public function getCartItems(Cart $cart, $locale): array
    {
        $data = [];

        $country = $cart->getCustomer()?->getDefaultAddress()->getCountry() ?? Country::getDefaultCountry();

        foreach ($cart->getCartItems() as $cartItem) {
            $product = $cartItem->getProduct()->setLocale($locale);

            $cartItemData = $this->getProductDataForEvent(
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

    /**
     * @param Order $order
     * @return array
     * @throws PropelException
     */
    public function getOrderItems(Order $order): array
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

            $productData = $this->getProductDataForEvent(
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

    /**
     * @param Product $product
     * @return string[]
     */
    protected function getProductCategories(Product $product)
    {
        $categories = $product->getCategories();

        return array_map(static function ($category) {
            return (string) $category['Id'];
        }, $categories->toArray());
    }

    /**
     * @param $title
     * @param $description
     * @param $eanCode
     * @param $ref
     * @param float $price
     * @param float $promoPrice
     * @param float $taxedPrice
     * @param float $taxedPromoPrice
     * @param bool $isPromo
     * @param Currency|null $currency
     * @param Product|null $product
     * @return array
     * @throws PropelException
     */
    protected function getProductDataForEvent(
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
        $discount = $price !== 0.0 ? round(100 * (1 - $promoPrice / $price), 2) : 0.0;

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
            $productData['categories'] = $this->getProductCategories($product);
            $productData['url'] = URL::getInstance()?->absoluteUrl($product->getUrl());
            if (null !== $imageUrl = $this->getProductImageUrl($product)) {
                $productData['imageUrl'] = $imageUrl;
            }

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

    /**
     * @throws PropelException
     */
    protected function getProductImageUrl(Product $product): ?string
    {
        // Search in classic images
        if (null === $productImage = ProductImageQuery::create()
                ->filterByProductId($product->getId())
                ->filterByVisible(1)
                ->orderBy('position')->findOne()
        ) {
            // Search in library if the service is available
            if (null !== $this->libraryImageService) {
                if (null === $itemImage = LibraryItemImageQuery::create()
                        ->filterByItemType('product')
                        ->filterByItemId($product->getId())
                        ->orderByPosition()
                        ->findOne()) {
                    return null;
                }

                return URL::getInstance()->absoluteUrl(
                    $this->libraryImageService->getImagePublicUrl($itemImage->getLibraryImage())
                );
            }
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
