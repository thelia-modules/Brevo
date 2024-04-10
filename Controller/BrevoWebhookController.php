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

/*      web : https://www.openstudio.fr */

/*      For the full copyright and license information, please view the LICENSE */
/*      file that was distributed with this source code. */

/**
 * Created by Franck Allimant, OpenStudio <fallimant@openstudio.fr>
 * Projet: thelia25
 * Date: 16/11/2023.
 */

namespace Brevo\Controller;

use Brevo\Model\BrevoNewsletterQuery;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Log\Tlog;
use Thelia\Model\Newsletter;
use Thelia\Model\NewsletterQuery;

/**
 * A controller to process Brevo webhooks.
 */
class BrevoWebhookController extends BaseFrontController
{
    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function processBrevoWebhook(Request $request): Response
    {
        $brevoData = $request->toArray();

        if (!isset($brevoData['event'])) {
            return new Response(400, 'No event provided');
        }

        if (!isset($brevoData['email'])) {
            return new Response(400, 'No email provided');
        }

        $email = $brevoData['email'];

        switch ($brevoData['event']) {
            case 'unsubscribe':
                // Clear relation ID for Brevo model
                if (null !== $model = BrevoNewsletterQuery::create()->findOneByEmail($email)) {
                    $model->setRelationId(null)->save();
                }

                // Delete entry from Newsletter
                if (null !== $model = NewsletterQuery::create()->findOneByEmail($email)) {
                    $model->delete();
                }
                break;

            case 'contact_deleted':
                // Delete contact from newletter or Brevo table.
                if (null !== $model = BrevoNewsletterQuery::create()->findOneByEmail($email)) {
                    $model->delete();
                }

                if (null !== $model = NewsletterQuery::create()->findOneByEmail($email)) {
                    $model->delete();
                }
                break;

            default:
                Tlog::getInstance()->warning('Unsupported Brevo webkook event : '.$brevoData['event']);
        }

        // Say OK to Brevo !
        return new Response(200, 'OK');
    }
}
