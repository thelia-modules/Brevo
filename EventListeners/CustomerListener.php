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

use Brevo\Brevo;
use Brevo\Event\BrevoCustomerUpdateEvent;
use Brevo\Event\BrevoEvents;
use Brevo\Services\BrevoCustomerService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Customer\CustomerCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;

class CustomerListener implements EventSubscriberInterface
{
    public function __construct(
        private BrevoCustomerService $brevoCustomerService
    ) {
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::CUSTOMER_CREATEACCOUNT => ['createOrUpdateCustomer', 100],
            TheliaEvents::CUSTOMER_UPDATEACCOUNT => ['createOrUpdateCustomer', 100],
            TheliaEvents::CUSTOMER_UPDATEPROFILE => ['createOrUpdateCustomer', 100],
            BrevoEvents::UPDATE_CUSTOMER => ['createOrUpdateCustomer', 128],
        ];
    }

    public function createOrUpdateCustomer(CustomerCreateOrUpdateEvent|BrevoCustomerUpdateEvent $event): void
    {
        try {
            $this->brevoCustomerService->createUpdateContact($event->getCustomer()?->getId());
        } catch (\Exception $ex) {
            Tlog::getInstance()->error('Failed to create or update Brevo contact : '.$ex->getMessage());

            if (ConfigQuery::read(Brevo::CONFIG_THROW_EXCEPTION_ON_ERROR, false)) {
                throw new TheliaProcessException(
                    Translator::getInstance()?->trans(
                        'An error occurred during the newsletter registration process',
                        [],
                        Brevo::MESSAGE_DOMAIN
                    )
                );
            }
        }
    }
}
