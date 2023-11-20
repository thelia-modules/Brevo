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

use Brevo\Services\BrevoApiService;
use Brevo\Services\BrevoOrderService;
use Brevo\Services\BrevoProductService;
use Brevo\Trait\DataExtractorTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Model\CountryQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Lang;

class CartListener implements EventSubscriberInterface
{
    use DataExtractorTrait;

    public function __construct(
        private RequestStack $requestStack,
        private BrevoApiService $brevoApiService,
        private BrevoProductService $brevoProductService,
        private BrevoOrderService $brevoOrderService,
    ) {
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::CART_ADDITEM => ['trackUpdateCartEvent', 128],
            TheliaEvents::CART_UPDATEITEM => ['trackUpdateCartEvent', 128],
            TheliaEvents::CART_DELETEITEM => ['trackUpdateCartEvent', 128],

            TheliaEvents::CART_CLEAR => ['trackDeleteCartEvent', 128],

            TheliaEvents::ORDER_PAY => ['trackNewOrderEvent', 110],

            TheliaEvents::ORDER_UPDATE_STATUS => ['updateStatus', 110],
        ];
    }

    public function updateStatus(OrderEvent $orderEvent): void
    {
        /** @var Lang $lang */
        $lang = $this->requestStack->getCurrentRequest()?->getSession()->getLang();

        $order = $orderEvent->getOrder();

        $this->brevoOrderService->exportOrder($order, $lang->getLocale());
    }

    public function trackUpdateCartEvent(CartEvent $event): void
    {
        $this->trackCart($event, 'cart_updated');
    }

    public function trackDeleteCartEvent(CartEvent $event): void
    {
        $this->trackCart($event, 'cart_deleted');
    }

    public function trackNewOrderEvent(OrderEvent $event): void
    {
        $order = $event->getPlacedOrder();

        $customer = $order->getCustomer();

        $properties = $this->getCustomerAttribute($customer->getId());

        $currency = $order->getCurrency();

        /** @var Lang $lang */
        $lang = $this->requestStack->getCurrentRequest()?->getSession()->getLang();

        $data = [
            'email' => $customer->getEmail(),
            'properties' => $properties,
            'eventdata' => [
                'id' => $order->getRef(),
                'data' => [
                    'total' => $order->getTotalAmount($tax, true, true),
                    'currency' => $currency->getCode(),
                    'items' => $this->brevoProductService->getOrderItems($order),
                ],
            ],
        ];

        $this->brevoOrderService->exportOrder($order, $lang->getLocale());

        try {
            $this->brevoApiService->sendTrackEvent('order_completed', $data);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('Brevo track order error:'.$exception->getMessage());
        }
    }

    protected function trackCart(CartEvent $event, $eventName): void
    {
        /** @var Customer $customer */
        if (null === $customer = $this->requestStack->getCurrentRequest()?->getSession()?->getCustomerUser()) {
            // No tracking if customer is not logged in, as Brevo requires an email in e-commerce tracking events.
            return;
        }

        $cart = $event->getCart();

        $properties = [];

        if ($customer !== null) {
            $properties = $this->getCustomerAttribute($customer->getId());
        }

        /** @var Currency $currency */
        $currency = $cart->getCurrency();

        $country = CountryQuery::create()->filterByByDefault(1)->findOne();

        /** @var Lang $lang */
        $lang = $this->requestStack->getCurrentRequest()?->getSession()->get('thelia.current.lang');

        $data = [
            'email' => $customer->getEmail(),
            'properties' => $properties,
            'eventdata' => [
                'id' => sprintf('cart:%s', $cart->getId()),
                'data' => [
                    'total' => $cart->getTaxedAmount($country),
                    'currency' => $currency->getCode(),
                    'items' => $this->brevoProductService->getCartItems($cart, $lang->getLocale(), $country),
                ],
            ],
        ];

        try {
            $this->brevoApiService->sendTrackEvent($eventName, $data);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('Brevo track cart error:'.$exception->getMessage());
        }
    }
}
