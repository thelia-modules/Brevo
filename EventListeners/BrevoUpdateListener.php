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

namespace Brevo\EventListeners;

use Brevo\Event\BrevoCategoryUpdateEvent;
use Brevo\Event\BrevoEvents;
use Brevo\Event\BrevoProductUpdateEvent;
use Brevo\Services\BrevoCategoryService;
use Brevo\Services\BrevoProductService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Category\CategoryCreateEvent;
use Thelia\Core\Event\Category\CategoryUpdateEvent;
use Thelia\Core\Event\Product\ProductCreateEvent;
use Thelia\Core\Event\Product\ProductUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Category;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Lang;

class BrevoUpdateListener implements EventSubscriberInterface
{
    public function __construct(
        private BrevoCategoryService $brevoCategoryService,
        private BrevoProductService $brevoProductService
    ) {
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::PRODUCT_CREATE => ['createProduct', 100],
            TheliaEvents::PRODUCT_UPDATE => ['updateProduct', 100],
            TheliaEvents::CATEGORY_CREATE => ['createCategory', 100],
            TheliaEvents::CATEGORY_UPDATE => ['updateCategory', 100],

            BrevoEvents::UPDATE_CATEGORY => ['updateCategory', 128],
            BrevoEvents::UPDATE_PRODUCT => ['updateProduct', 128],
        ];
    }


    public function updateProduct(ProductUpdateEvent|BrevoProductUpdateEvent $event): void
    {
        $lang = Lang::getDefaultLanguage();
        $currency = Currency::getDefaultCurrency();
        $country = Country::getDefaultCountry();
        $this->brevoProductService->export($event->getProduct(), $lang->getLocale(), $currency, $country);
    }

    public function createProduct(ProductCreateEvent $event): void
    {
        $lang = Lang::getDefaultLanguage();
        $currency = Currency::getDefaultCurrency();
        $country = Country::getDefaultCountry();
        $this->brevoProductService->export($event->getProduct(), $lang->getLocale(), $currency, $country);
    }

    public function updateCategory(CategoryUpdateEvent|BrevoCategoryUpdateEvent $event): void
    {
        $lang = Lang::getDefaultLanguage();
        $this->brevoCategoryService->export($event->getCategory(), $lang->getLocale());
    }

    public function createCategory(CategoryCreateEvent $event): void
    {
        $lang = Lang::getDefaultLanguage();
        $this->brevoCategoryService->export($event->getCategory(), $lang->getLocale());
    }
}
