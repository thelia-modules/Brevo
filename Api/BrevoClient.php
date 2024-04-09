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

/*      Copyright (c) OpenStudio */
/*      email : dev@thelia.net */
/*      web : http://www.thelia.net */

/*      For the full copyright and license information, please view the LICENSE.txt */
/*      file that was distributed with this source code. */

namespace Brevo\Api;

use Brevo\Brevo;
use Brevo\Client\Api\ContactsApi;
use Brevo\Client\ApiException;
use Brevo\Client\Configuration;
use Brevo\Client\Model\CreateContact;
use Brevo\Client\Model\RemoveContactFromList;
use Brevo\Client\Model\UpdateContact;
use Brevo\Model\BrevoNewsletterQuery;
use Brevo\Trait\DataExtractorTrait;
use GuzzleHttp\Client;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\NewsletterQuery;

/**
 * Class BrevoClient.
 *
 * @author Chabreuil Antoine <achabreuil@openstudio.com>
 */
class BrevoClient
{
    use DataExtractorTrait;

    protected ContactsApi $contactApi;
    private mixed $newsletterId;

    public function __construct($apiKey = null, $newsletterId = null)
    {
        $apiKey = $apiKey ?: ConfigQuery::read(Brevo::CONFIG_API_SECRET);
        $this->newsletterId = (int) ($newsletterId ?? ConfigQuery::read(Brevo::CONFIG_NEWSLETTER_ID));
        $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', $apiKey);
        $this->contactApi = new ContactsApi(new Client(), $config);
    }

    /**
     * @throws ApiException
     */
    public function subscribe(NewsletterEvent $event)
    {
        try {
            $contact = $this->contactApi->getContactInfoWithHttpInfo($event->getEmail());
        } catch (ApiException $apiException) {
            if ($apiException->getCode() !== 404) {
                throw $apiException;
            }

            return $this->createContact($event->getEmail());
        }

        $this->update($event, $contact);

        return $contact;
    }

    public function checkIfContactExist($email)
    {
        return $this->contactApi->getContactInfoWithHttpInfo($email);
    }

    public function buildCreateOrUpdateData(string $email): array
    {
        $contactAttribute = [];

        if (null !== $customer = CustomerQuery::create()->findOneByEmail($email)) {
            $contactAttribute = $this->getCustomerAttribute($customer->getId());
        }

        return $this->buildCreateOrUpdateGenericData($email, $contactAttribute);
    }

    public function buildCreateOrUpdateGenericData(string $email, array $contactAttribute): array
    {
        $data['email'] = $email;
        if (! empty($contactAttribute)) {
            $data['attributes'] = $contactAttribute;
        }
        $data['listIds'] = [$this->newsletterId];

        return $data;
    }

    public function createContact(string $email)
    {
        return $this->createGenericContact($email, $this->buildCreateOrUpdateData($email));
    }

    public function createGenericContact(string $email, array $contactAttributes)
    {
        $createContact = new CreateContact($contactAttributes);

        $this->contactApi->createContactWithHttpInfo($createContact);

        return $this->contactApi->getContactInfoWithHttpInfo($email);
    }

    public function updateContact($identifier, Customer $customer)
    {
        return $this->updateGenericContact(
            $identifier,
            $customer->getEmail(),
            $this->buildCreateOrUpdateData($customer->getEmail())
        );
    }

    public function updateGenericContact($identifier, string $email, array $contactAttributes)
    {
        $updateContact = new UpdateContact($contactAttributes);

        $this->contactApi->updateContactWithHttpInfo($identifier, $updateContact);

        return $this->contactApi->getContactInfoWithHttpInfo($email);
    }

    public function update(NewsletterEvent $event, $contact = null)
    {
        $updateContact = new UpdateContact();
        $previousEmail = $contact ? $contact[0]['email'] : $event->getEmail();

        if (!$contact) {
            $sibObject = BrevoNewsletterQuery::create()->findPk($event->getId());
            if (null === $sibObject) {
                $sibObject = NewsletterQuery::create()->findPk($event->getId());
            }

            if (null !== $sibObject) {
                $previousEmail = $sibObject->getEmail();
                $contact = $this->contactApi->getContactInfoWithHttpInfo($previousEmail);

                $updateContact['email'] = $event->getEmail();
                $updateContact['attributes'] = ['PRENOM' => $event->getFirstname(), 'NOM' => $event->getLastname()];
            }
        }

        $updateContact['listIds'] = [$this->newsletterId];
        $this->contactApi->updateContactWithHttpInfo($contact[0]['id'], $updateContact);

        return $this->contactApi->getContactInfoWithHttpInfo($previousEmail);
    }

    public function unsubscribe(string $email)
    {
        $contact = $this->contactApi->getContactInfoWithHttpInfo($email);
        $change = false;

        if (\in_array($this->newsletterId, $contact[0]['listIds'], true)) {
            $contactIdentifier = new RemoveContactFromList();
            $contactIdentifier['emails'] = [$email];
            $this->contactApi->removeContactFromList($this->newsletterId, $contactIdentifier);
            $change = true;
        }

        return $change ? $this->contactApi->getContactInfoWithHttpInfo($email) : $contact;
    }
}
