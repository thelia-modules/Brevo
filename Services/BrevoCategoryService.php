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

class BrevoCategoryService
{
    public function __construct(private BrevoApiService $brevoApiService)
    {
    }

    public function exportCategory(Category $category, $locale)
    {
        $data = $this->getCategoryData($category, $locale);
        $data['updateEnabled'] = true;

        try {
            $this->brevoApiService->sendPostEvent('https://api.brevo.com/v3/categories', $data);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('Brevo category export error:'.$exception->getMessage());
        }
    }

    public function exportCategoryInBatch($limit, $offset, $locale)
    {
        $categories = CategoryQuery::create()
            ->setLimit($limit)
            ->setOffset($offset)
        ;

        $data = [];

        /** @var Product $product */
        foreach ($categories as $category) {
            $data[] = $this->getCategoryData($category, $locale);
        }

        $batchData['categories'] = $data;
        $batchData['updateEnabled'] = true;

        try {
            $this->brevoApiService->sendPostEvent('https://api.brevo.com/v3/categories/batch', $batchData);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('Brevo category export error:'.$exception->getMessage());
        }
    }

    public function getCategoryData(Category $category, $locale)
    {
        $category->setLocale($locale);

         return [
            'id' => (string)$category->getId(),
            'name' => $category->getTitle(),
            'url' => URL::getInstance()?->absoluteUrl($category->getUrl()),
        ];
    }
}