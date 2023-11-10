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

namespace Brevo\Hook;

use Brevo\Brevo;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Template\Assets\AssetResolverInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use TheliaSmarty\Template\SmartyParser;

class HookManager extends BaseHook
{
    /** @var Request  */
    protected $request;

    public function __construct(RequestStack $requestStack, EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct(null, null, $eventDispatcher);

        $this->request = $requestStack->getCurrentRequest();
    }

    public function onModuleConfiguration(HookRenderEvent $event)
    {
        $event->add(
            $this->render("brevo-configuration.html")
        );
    }

    public function onMainHeadTop(HookRenderEvent $event)
    {
        $apiKey = ConfigQuery::read(Brevo::CONFIG_API_SECRET);

        //$this->get('thelia.customer_user');
        /** @var Customer $customer */
        $customer = $this->request->getSession()->getCustomerUser();

        $email = null;
        if ($customer !== null) {
            $email = $customer->getEmail();
        }

        $event->add(
            $this->render("tracking_script.html",[
                'apiKey' => $apiKey,
                'email' => $email
            ])
        );
    }
}
