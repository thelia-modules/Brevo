<?php

namespace Brevo\EventListeners;

use Brevo\Services\BrevoCategoryService;
use Brevo\Services\BrevoProductService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Category\CategoryCreateEvent;
use Thelia\Core\Event\Category\CategoryUpdateEvent;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CountryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Event\CategoryEvent;
use Thelia\Model\Event\ProductEvent;
use Thelia\Model\LangQuery;

class BrevoUpdateListener implements EventSubscriberInterface
{

    public function __construct(
        private BrevoCategoryService $brevoCategoryService,
        private BrevoProductService $brevoProductService
    )
    {

    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::PRODUCT_CREATE => ['createProduct', 100],
            TheliaEvents::PRODUCT_UPDATE => ['updateProduct', 100],
            TheliaEvents::CATEGORY_CREATE => ['createCategory', 100],
            TheliaEvents::CATEGORY_UPDATE => ['updateCategory', 100],
        ];
    }

    public function updateProduct(ProductUpdateEvent $event)
    {
        $lang = LangQuery::create()->filterByByDefault(1)->findOne();
        $currency = CurrencyQuery::create()->filterByByDefault(1)->findOne();
        $country = CountryQuery::create()->filterByByDefault(1)->findOne();
        $this->brevoProductService->exportProduct($event->getProduct(), $lang->getLocale(), $currency, $country);
    }

    public function createProduct(ProductCreateEvent $event)
    {
        $lang = LangQuery::create()->filterByByDefault(1)->findOne();
        $currency = CurrencyQuery::create()->filterByByDefault(1)->findOne();
        $country = CountryQuery::create()->filterByByDefault(1)->findOne();
        $this->brevoProductService->exportProduct($event->getProduct(), $lang->getLocale(), $currency, $country);
    }

    public function updateCategory(CategoryUpdateEvent $event)
    {
        $lang = LangQuery::create()->filterByByDefault(1)->findOne();
        $this->brevoCategoryService->exportCategory($event->getCategory(), $lang->getLocale());
    }

    public function createCategory(CategoryCreateEvent $event)
    {
        $lang = LangQuery::create()->filterByByDefault(1)->findOne();
        $this->brevoCategoryService->exportCategory($event->getCategory(), $lang->getLocale());
    }

}