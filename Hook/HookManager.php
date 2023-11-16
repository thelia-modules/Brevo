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

namespace Brevo\Hook;

use Brevo\Brevo;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;

class HookManager extends BaseHook
{
    /** @var Request */
    protected $request;

    public function __construct(RequestStack $requestStack, EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct(null, null, $eventDispatcher);

        $this->request = $requestStack->getCurrentRequest();
    }

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $event->add(
            $this->render('brevo-configuration.html')
        );
    }

    public function onMainHeadTop(HookRenderEvent $event): void
    {
        /** @var Customer $customer */
        $customer = $this->request->getSession()?->getCustomerUser();

        $event->add(
            $this->render('tracking_script.html', [
                'marketingAutomationKey' => ConfigQuery::read(Brevo::CONFIG_AUTOMATION_KEY),
                'email' => $customer?->getEmail(),
            ])
        );
    }
}
