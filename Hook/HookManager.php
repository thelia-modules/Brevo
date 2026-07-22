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

declare(strict_types=1);

namespace Brevo\Hook;

use Brevo\Brevo;
use Brevo\Form\BrevoConfigurationForm;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;

class HookManager extends BaseHook
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TheliaFormFactory $formFactory,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($eventDispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'module.configuration' => [
                ['type' => 'back', 'method' => 'onModuleConfiguration'],
            ],
            'main.head-bottom' => [
                ['type' => 'front', 'method' => 'onMainHeadTop'],
            ],
        ];
    }

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        // Render the template matching the active back-office parser: the Twig
        // back-office (default-twig) gets the Twig template, a legacy Smarty
        // back-office keeps the original .html template (backward compatibility).
        // Success/error feedback is handled by the flash bag (set in the controller).
        if (str_contains($this->getParser()->getFileExtension(), 'twig')) {
            $form = $this->formFactory->createForm(BrevoConfigurationForm::getName());

            $event->add(
                $this->render('Brevo/module-configuration.html.twig', [
                    'form' => $form->createView()->getView(),
                ])
            );

            return;
        }

        $event->add(
            $this->render('brevo-configuration.html')
        );
    }

    public function onMainHeadTop(HookRenderEvent $event): void
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        /** @var Customer|null $customer */
        $customer = $session instanceof Session ? $session->getCustomerUser() : null;

        $event->add(
            $this->render('tracking_script.html', [
                'marketingAutomationKey' => ConfigQuery::read(Brevo::CONFIG_AUTOMATION_KEY),
                'email' => $customer?->getEmail(),
            ])
        );
    }
}
