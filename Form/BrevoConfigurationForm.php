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

namespace Brevo\Form;

use Brevo\Brevo;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\ConfigQuery;

/**
 * Class BrevoConfigurationForm.
 *
 * @author Chabreuil Antoine <achabreuil@openstudio.com>
 */
class BrevoConfigurationForm extends BaseForm
{
    /**
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->formBuilder attribute :.
     *
     * $this->formBuilder->add("name", "text")
     *   ->add("email", "email", array(
     *           "attr" => array(
     *               "class" => "field"
     *           ),
     *           "label" => "email",
     *           "constraints" => array(
     *               new \Symfony\Component\Validator\Constraints\NotBlank()
     *           )
     *       )
     *   )
     *   ->add('age', 'integer');
     */
    protected function buildForm(): void
    {
        $translator = Translator::getInstance();

        $defaultCustomerMapping = <<< END
{
  "customer_query": {
    "_comment" : "You can map here Brevo contact attributes to Thelia customer data."
    "EMAIL" : {
      "select" : "customer.email"
    }
  }
}
END;
        $defaultMetadataMapping = <<< END
{
  "product_query": {
     "_comment" : "You can add here some fields to standard Brevo data, or override some standard fields (e.g., 'price')"
  },
  "product_metadata_query": {
       "_comment" : "You can define here some metat data attributes, that will be placed in the 'metaInfo' field"
  }
}
END;
        $this->formBuilder
            ->add('api_key', TextType::class, [
                'label' => $translator->trans('Brevo API key', [], Brevo::MESSAGE_DOMAIN),
                'label_attr' => [
                    'for' => 'api_key',
                    'help' => Translator::getInstance()->trans(
                        'To get an API key, click the top right menu -> SMTP and API -> API Keys -> create a new API Key',
                        [],
                        Brevo::MESSAGE_DOMAIN
                    ),
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
                'data' => ConfigQuery::read(Brevo::CONFIG_API_SECRET),
            ])
            ->add('automation_key', TextType::class, [
                'label' => $translator->trans('Automation key', [], Brevo::MESSAGE_DOMAIN),
                'label_attr' => [
                    'for' => 'automation_key',
                    'help' => Translator::getInstance()->trans(
                        'To get a key, select Automation in the left menu -> Settings -> Tracking code -> JS tracer -> copy the client_key value in the JS code displayed (something like k43xd26m9fzeyup9r3nogjxp)',
                        [],
                        Brevo::MESSAGE_DOMAIN
                    ),
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
                'data' => ConfigQuery::read(Brevo::CONFIG_AUTOMATION_KEY),
            ])
            ->add('newsletter_list', TextType::class, [
                'label' => $translator->trans('Contact list ID', [], Brevo::MESSAGE_DOMAIN),
                'label_attr' => [
                    'help' => Translator::getInstance()->trans(
                        'Click Contacts in the left menu -> Lists and get the list ID without # (e.g. enter 22 if the list ID is #22)',
                        [],
                        Brevo::MESSAGE_DOMAIN
                    ),
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                ],
                'data' => ConfigQuery::read(Brevo::CONFIG_NEWSLETTER_ID),
            ])
            ->add('attributes_mapping', TextareaType::class, [
                'label' => $translator->trans('Customer attributes mapping', [], Brevo::MESSAGE_DOMAIN),
                'attr' => [
                    'rows' => 10
                ],
                'label_attr' => [
                    'for' => 'attributes_mapping',
                    'help' => Translator::getInstance()->trans(
                        'This is a mapping of Brevo contact attributes with Thelia customer attributes. Do not change anything here if you do not know exactly what you are doing',
                        [],
                        Brevo::MESSAGE_DOMAIN
                    )
                ],
                'required' => false,
                'constraints' => [
                    new NotBlank(),
                    new Callback([$this, 'checkCustomerJsonValidity']),
                ],
                'data' => ConfigQuery::read(Brevo::BREVO_ATTRIBUTES_MAPPING, $defaultCustomerMapping),
            ])
            ->add('metadata_mapping', TextareaType::class, [
                'label' => $translator->trans('Products attributes mapping', [], Brevo::MESSAGE_DOMAIN),
                'attr' => [
                    'rows' => 10
                ],
                'label_attr' => [
                    'for' => 'attributes_mapping',
                    'help' => Translator::getInstance()->trans(
                        'This is a mapping of Brevo products data and meta-data attributes with Thelia products attributes. Do not change anything here if you do not know exactly what you are doing',
                        [],
                        Brevo::MESSAGE_DOMAIN
                    )
                ],
                'required' => false,
                'constraints' => [
                    new NotBlank(),
                    new Callback([$this, 'checkProductJsonValidity']),
                ],
                'data' => ConfigQuery::read(Brevo::BREVO_METADATA_MAPPING, $defaultMetadataMapping),
            ])
            ->add('exception_on_errors', CheckboxType::class, [
                'label' => $translator->trans('Throw exception on Brevo error', [], Brevo::MESSAGE_DOMAIN),
                'data' => (bool) ConfigQuery::read(Brevo::CONFIG_THROW_EXCEPTION_ON_ERROR, false),
                'required' => false,
                'label_attr' => [
                    'help' => $translator->trans(
                        'The module will throw an error if something wrong happens whan talking to Brevo. Warning ! This could prevent user registration if Brevo server is down or unreachable !',
                        [],
                        Brevo::MESSAGE_DOMAIN
                    ),
                ],
            ])
        ;
    }

    public function checkCustomerJsonValidity($value, ExecutionContextInterface $context): void
    {
        $this->checkJsonValidity('customer_query', $value, $context);
    }
    public function checkProductJsonValidity($value, ExecutionContextInterface $context): void
    {
        $this->checkJsonValidity('product_query', $value, $context);
    }

    public function checkJsonValidity(string $expectedNode, $value, ExecutionContextInterface $context): void
    {
        if (empty($value)) {
            return;
        }

        if (null === $jsonData = json_decode($value, true)) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    "The customer attributes mapping JSON seems invalid, please check syntax.",
                    [],
                    Brevo::MESSAGE_DOMAIN
                )
            );
        }

        if (! isset($jsonData[$expectedNode])) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    "The customer attributes mapping JSON should contain a '$expectedNode' field.",
                    [],
                    Brevo::MESSAGE_DOMAIN
                )
            );
        }
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName()
    {
        return 'brevo_configuration';
    }
}
