<?php

namespace Brevo\EventListeners;

use Brevo\Services\BrevoApiService;
use Brevo\Services\BrevoOrderService;
use Brevo\Services\BrevoProductService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Model\Cart;
use Thelia\Model\CountryQuery;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Lang;
use Thelia\Model\Order;

class CartListener implements EventSubscriberInterface
{

    public function __construct(
        private RequestStack $requestStack,
        private BrevoApiService $brevoApiService,
        private BrevoProductService $brevoProductService,
        private BrevoOrderService $brevoOrderService,
    ){
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::CART_ADDITEM => ['trackUpdateCartEvent', 128],
            TheliaEvents::CART_DELETEITEM => ['trackUpdateCartEvent', 128],
            TheliaEvents::CART_CLEAR => ['trackDeleteCartEvent', 128],
            TheliaEvents::ORDER_PAY => ['trackNewOrderEvent', 110],
            TheliaEvents::ORDER_UPDATE_STATUS => ['updateStatus', 110]
        ];
    }

    public function updateStatus(OrderEvent $orderEvent)
    {
        /** @var Lang $lang */
        $lang = $this->requestStack->getCurrentRequest()?->getSession()->get('thelia.current.lang');

        $order = $orderEvent->getOrder();

        $this->brevoOrderService->exportOrder($order, $lang->getLocale());
    }

    public function trackUpdateCartEvent (CartEvent $event)
    {
        $this->trackCart($event, 'cart_updated');
    }

    public function trackDeleteCartEvent (CartEvent $event)
    {
        $this->trackCart($event, 'cart_deleted');
    }

    public function trackNewOrderEvent (OrderEvent $event)
    {
        $order = $event->getPlacedOrder();

        $customer = $order->getCustomer();

        $properties = [];

        $email = null;

        if ($customer !== null) {
            $email = $customer->getEmail();
            $properties = [
                'email' => $customer->getEmail(),
                'firstname' => $customer->getFirstname(),
                'lastname' => $customer->getLastname(),
            ];
        }

        /** @var Currency $currency */
        $currency = $order->getCurrency();

        /** @var Lang $lang */
        $lang = $this->requestStack->getCurrentRequest()?->getSession()->get('thelia.current.lang');

        $data = [
            'email' => $email,
            'properties' => $properties,
            'eventdata' => [
                'id' => sprintf('order:%s', $order->getId()),
                'data' => [
                    'total' => $order->getTotalAmount($tax, true, true),
                    'currency' => $currency->getCode(),
                    'items' => $this->brevoProductService->getItemsByOrder($order, $lang->getLocale()),
                ]
            ]
        ];

        $this->brevoOrderService->exportOrder($order, $lang->getLocale());

        try {
            $this->brevoApiService->sendTrackEvent('order_completed', $data);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('Brevo track order error:'.$exception->getMessage());
        }
    }

    protected function trackCart(CartEvent $event, $eventName)
    {
        /** @var Customer $customer */
        $customer = $this->requestStack->getCurrentRequest()?->getSession()?->get('thelia.customer_user');

        $cart = $event->getCart();

        $properties = [];

        $email = null;

        if ($customer !== null) {
            $email = $customer->getEmail();
            $properties = [
                'email' => $customer->getEmail(),
                'firstname' => $customer->getFirstname(),
                'lastname' => $customer->getLastname(),
            ];
        }

        /** @var Currency $currency */
        $currency = $cart->getCurrency();

        $country = CountryQuery::create()->filterByByDefault(1)->findOne();

        /** @var Lang $lang */
        $lang = $this->requestStack->getCurrentRequest()?->getSession()->get('thelia.current.lang');

        $data = [
            'email' => $email,
            'properties' => $properties,
            'eventdata' => [
                'id' => sprintf('cart:%s', $cart->getId()),
                'data' => [
                    'total' => $cart->getTaxedAmount($country),
                    'currency' => $currency->getCode(),
                    'items' => $this->brevoProductService->getItemsByCart($cart, $lang->getLocale(), $country),
                ]
            ]
        ];

        try {
            $this->brevoApiService->sendTrackEvent($eventName, $data);
        } catch (\Exception $exception) {
            Tlog::getInstance()->error('Brevo track cart error:'.$exception->getMessage());
        }
    }

}