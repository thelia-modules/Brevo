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

namespace Brevo\Controller;

use Brevo\Api\BrevoClient;
use Brevo\Brevo;
use Brevo\Form\BrevoConfigurationForm;
use Brevo\Services\BrevoApiService;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Template\ParserContext;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

/**
 * Class BrevoConfigController.
 *
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

            ConfigQuery::write(Brevo::CONFIG_API_SECRET, $data['api_key']);
            ConfigQuery::write(Brevo::CONFIG_AUTOMATION_KEY, $data['automation_key']);
            ConfigQuery::write(Brevo::CONFIG_NEWSLETTER_ID, $data['newsletter_list']);
            ConfigQuery::write(Brevo::CONFIG_THROW_EXCEPTION_ON_ERROR, (bool) $data['exception_on_errors']);
            ConfigQuery::write(Brevo::BREVO_ATTRIBUTES_MAPPING, $data['attributes_mapping']);

            $brevoApiService->enableEcommerce();

            $parserContext->set('success', true);

            if ('close' === $request->request->get('save_mode')) {
                return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/modules'));
            }
        } catch (\Exception $e) {
            $parserContext
                ->setGeneralError($e->getMessage())
                ->addForm($baseForm)
            ;
        }

        return $this->render('module-configure', ['module_code' => 'Brevo']);
    }
}
