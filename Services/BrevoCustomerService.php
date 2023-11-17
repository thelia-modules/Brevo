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

use Brevo\Api\BrevoClient;
use Brevo\Brevo;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;

class BrevoCustomerService
{
    public function __construct(private BrevoClient $brevoClient)
    {
    }

    public function createUpdateContact($customerId)
    {
        $customer = CustomerQuery::create()->findPk($customerId);

        try {
            $contact = $this->brevoClient->checkIfContactExist($customer->getEmail());

            return $this->brevoClient->updateContact($contact[0]->getId(), $customer);
        } catch (\Exception $exception) {
            if ($exception->getCode() !== 404) {
                throw $exception;
            }

            return $this->brevoClient->createContact($customer->getEmail());
        }
    }
}
