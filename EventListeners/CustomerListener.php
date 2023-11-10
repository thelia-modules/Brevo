<?php

namespace Brevo\EventListeners;

use Brevo\Api\BrevoClient;
use Brevo\Services\BrevoCustomerService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;

class CustomerListener implements EventSubscriberInterface
{

    public function __construct(
        private BrevoCustomerService $brevoCustomerService
    )
    {

    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::CUSTOMER_CREATEACCOUNT => ['createOrUpdateCustomer', 100],
            TheliaEvents::CUSTOMER_UPDATEACCOUNT => ['createOrUpdateCustomer', 100],
            TheliaEvents::CUSTOMER_UPDATEPROFILE => ['createOrUpdateCustomer', 100],
        ];
    }

    public function createOrUpdateCustomer(CustomerCreateOrUpdateEvent $event)
    {
        $this->brevoCustomerService->createUpdateContact($event->getCustomer()?->getId());
    }

}