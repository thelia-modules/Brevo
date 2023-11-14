<?php

namespace Brevo\Services;

use Brevo\Api\BrevoClient;
use Brevo\Brevo;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Install\Database;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;

class BrevoCustomerService
{
    public function __construct(private BrevoClient $brevoClient)
    {
    }

    public function createUpdateContact($customerId)
    {
        if (empty(ConfigQuery::read(Brevo::BREVO_ATTRIBUTES_MAPPING, ''))) {
            return null;
        }

        $customer = CustomerQuery::create()->findPk($customerId);

        try {
            $contact = $this->brevoClient->checkIfContactExist($customer->getEmail());

            return $this->brevoClient->updateContact($contact[0]->getId(), $customer);
        }catch (\Exception $exception) {
            if ($exception->getCode() !== 404) {
                throw $exception;
            }
            return $this->brevoClient->createContact($customer);
        }
    }
}
