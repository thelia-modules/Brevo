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

namespace Brevo\Controller;

use Brevo\Api\BrevoClient;
use Brevo\Form\BrevoConfigurationForm;
use Brevo\Model\BrevoNewsletterQuery;
use Brevo\Brevo;
use Brevo\Services\BrevoApiService;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\ParserContext;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\CustomerTitle;
use Thelia\Model\NewsletterQuery;
use Thelia\Tools\URL;

/**
 * Class BrevoConfigController
 * @package Brevo\Controller
 * @author Chabreuil Antoine <achabreuil@openstudio.com>
 */
class BrevoConfigController extends BaseAdminController
{
    public function saveAction(Request $request, ParserContext $parserContext, BrevoApiService $brevoApiService)
    {
        $baseForm = $this->createForm(BrevoConfigurationForm::getName());

        try {
            $form = $this->validateForm($baseForm);
            $data = $form->getData();

            ConfigQuery::write(Brevo::CONFIG_API_SECRET, $data["api_key"]);
            ConfigQuery::write(Brevo::CONFIG_AUTOMATION_KEY, $data["automation_key"]);
            ConfigQuery::write(Brevo::CONFIG_NEWSLETTER_ID, $data["newsletter_list"]);
            ConfigQuery::write(Brevo::CONFIG_THROW_EXCEPTION_ON_ERROR, (bool)$data["exception_on_errors"]);

            if (isset($data["correspondence_file"])) {
                $fs = new Filesystem();
                /** @var UploadedFile $file */
                $file = $data["correspondence_file"];
                if (!$fs->exists(BrevoClient::CONTACT_FIELD_CORRESPONDENCE_DIR)){
                    $fs->mkdir(BrevoClient::CONTACT_FIELD_CORRESPONDENCE_DIR);
                }
                $fs->dumpFile(BrevoClient::CONTACT_FIELD_CORRESPONDENCE_FILE, $file->getContent());
            }

            $brevoApiService->enableEcommerce();

            $parserContext->set("success", true);

            if ("close" === $request->request->get("save_mode")) {
                return new RedirectResponse(URL::getInstance()->absoluteUrl("/admin/modules"));
            }
        } catch (\Exception $e) {
            $parserContext
                ->setGeneralError($e->getMessage())
                ->addForm($baseForm)
            ;
        }

        return $this->render('module-configure', [ 'module_code' => 'Brevo' ]);
    }
}
