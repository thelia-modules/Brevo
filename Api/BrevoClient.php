<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Brevo\Api;

use Brevo\Brevo;
use Brevo\Client\Api\ContactsApi;
use Brevo\Client\ApiException;
use Brevo\Client\Configuration;
use Brevo\Client\Model\CreateContact;
use Brevo\Client\Model\RemoveContactFromList;
use Brevo\Client\Model\UpdateContact;
use GuzzleHttp\Client;
use Brevo\Model\BrevoNewsletterQuery;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;

/**
 * Class BrevoClient
 * @package Brevo\Api
 * @author Chabreuil Antoine <achabreuil@openstudio.com>
 */
class BrevoClient
{
    protected ContactsApi $contactApi;
    private mixed $newsletterId;
    const CONTACT_FIELD_CORRESPONDENCE_DIR = THELIA_LOCAL_DIR."media/brevo/";
    const CONTACT_FIELD_CORRESPONDENCE_FILE = self::CONTACT_FIELD_CORRESPONDENCE_DIR."thelia_brevo_contact_field_correspondence.json";


    public function __construct($apiKey = null, $newsletterId = null)
    {
        $apiKey = $apiKey ? : ConfigQuery::read(Brevo::CONFIG_API_SECRET);
        $this->newsletterId = $newsletterId ? (int)$newsletterId : ConfigQuery::read(Brevo::CONFIG_NEWSLETTER_ID);
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
            $contact = $this->createContact($event->getId());
        }

        $this->update($event, $contact);

        return $contact;
    }

    public function checkIfContactExist($email)
    {
        return $this->contactApi->getContactInfoWithHttpInfo($email);
    }

    public function createContact(Customer $customer)
    {
        $contactAttribute = $this->getCustomerAttribute($customer->getId());
        $createContact = new CreateContact();
        $createContact['email'] = $customer->getEmail();
        $createContact['attributes'] = $contactAttribute;
        $this->contactApi->createContactWithHttpInfo($createContact);
        return $this->contactApi->getContactInfoWithHttpInfo($customer->getEmail());
    }

    public function updateContact($identifier, Customer $customer)
    {
        $contactAttribute = $this->getCustomerAttribute($customer->getId());
        $createContact = new UpdateContact();
        $createContact['email'] = $customer->getEmail();
        $createContact['attributes'] = $contactAttribute;
        $this->contactApi->updateContactWithHttpInfo($identifier, $createContact);
        return $this->contactApi->getContactInfoWithHttpInfo($customer->getEmail());
    }

    public function update(NewsletterEvent $event, $contact = null)
    {
        $updateContact = new UpdateContact();
        $previousEmail = $contact ? $contact[0]['email'] : $event->getEmail();

        if (!$contact){
            $sibObject = BrevoNewsletterQuery::create()->findPk($event->getId());
            if (null === $sibObject) {
                $sibObject = BrevoNewsletterQuery::create()->findOneByEmail($previousEmail);
            }
            $previousEmail = $sibObject->getEmail();
            $contact = $this->contactApi->getContactInfoWithHttpInfo($previousEmail);

            $updateContact['email'] = $event->getEmail();
            $updateContact['attributes'] = ['PRENOM' => $event->getFirstname(), "NOM" => $event->getLastname()];
        }

        $updateContact['listIds'] = [$this->newsletterId];
        $this->contactApi->updateContactWithHttpInfo($contact[0]['id'], $updateContact);

        return $this->contactApi->getContactInfoWithHttpInfo($previousEmail);
    }

    public function unsubscribe(NewsletterEvent $event)
    {
        $contact = $this->contactApi->getContactInfoWithHttpInfo($event->getEmail());
        $change = false;

        if (in_array($this->newsletterId, $contact[0]['listIds'], true)) {
            $contactIdentifier = new RemoveContactFromList();
            $contactIdentifier['emails'] = [$event->getEmail()];
            $this->contactApi->removeContactFromList($this->newsletterId, $contactIdentifier);
            $change = true;
        }

        return $change ? $this->contactApi->getContactInfoWithHttpInfo($event->getEmail()) : $contact;
    }
    public function getCustomerAttribute($customerId)
    {
        if (!file_exists(self::CONTACT_FIELD_CORRESPONDENCE_FILE)) {
            throw new \Exception("Customer attribute correspondence error : configuration is missing, please go to the module configuration and upload the json file to match thelia attribute with brevo attribute");
        }

        $correspondence = json_decode(file_get_contents(self::CONTACT_FIELD_CORRESPONDENCE_FILE), true, 512, JSON_THROW_ON_ERROR);

        if (!array_key_exists('customer_query', $correspondence)) {
            throw new \Exception("Customer attribute correspondence error : the configuration file is incorrect, 'customer_query' element is missing");
        }

        $attributes = [];

        /** @var ConnectionWrapper $con */
        $con = Propel::getConnection();

        foreach ($correspondence['customer_query'] as $key => $customerDataQuery) {

            if (!array_key_exists('select', $customerDataQuery)) {
                throw new \Exception("Customer attribute correspondence error : 'select' element missing in " . $key . ' query');
            }

            $sql = 'SELECT ' . $customerDataQuery['select'] . ' AS ' . $key . ' FROM customer';

            if (array_key_exists('join', $customerDataQuery)) {
                foreach ($customerDataQuery['join'] as $join) {
                    $sql .= ' LEFT JOIN ' . $join;
                }
            }

            $sql .= ' WHERE customer.id = :customerId';

            if (array_key_exists('groupBy', $customerDataQuery)) {
                $sql .= ' GROUP BY ' . $customerDataQuery['groupBy'];
            }

            $stmt = $con->prepare($sql);
            $stmt->bindValue(':customerId', $customerId, \PDO::PARAM_INT);
            $stmt->execute();

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $attributes[$key] = $row[$key];
                if (array_key_exists($key, $correspondence) && array_key_exists($row[$key], $correspondence[$key])){
                    $attributes[$key] = $correspondence[$key][$row[$key]];
                }
            }

        }

        return $attributes;
    }

}
