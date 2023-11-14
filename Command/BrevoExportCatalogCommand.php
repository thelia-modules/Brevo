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

namespace Brevo\Command;

use Brevo\Brevo;
use Brevo\Services\BrevoCategoryService;
use Brevo\Services\BrevoProductService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Command\ContainerAwareCommand;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Lang;

class BrevoExportCatalogCommand extends ContainerAwareCommand
{
    public function __construct(
        private BrevoProductService $brevoProductService,
        private BrevoCategoryService $brevoCategoryService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('brevo:export:catalog')
            ->setDescription('Export the Thelias catalog (categories and products) to Brevo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initRequest();

        if (null === ConfigQuery::read(Brevo::CONFIG_API_SECRET)) {
            $output->writeln('Please configure this module in the back office');

            return Command::FAILURE;
        }

        $lang = Lang::getDefaultLanguage();
        $currency = Currency::getDefaultCurrency();
        $country = Country::getDefaultCountry();

        $elements = [
            $this->brevoCategoryService,
            $this->brevoProductService,
        ];

        $batchSize = 100;

        /** @var BrevoProductService|BrevoCategoryService $element */
        foreach ($elements as $element) {
            $objCount = $element->getCount();
            $objName = $element->getObjName();

            $progressBar = ProgressBarHelper::createProgressBar($output, $objCount);

            try {
                for ($idx = 0; $idx < $objCount; $idx += $batchSize) {
                    $progressBar->setMessage('<info>Exporting '.$batchSize.' '.$objName.'s</info>');
                    $element->exportInBatch($batchSize, $idx, $lang?->getLocale(), $currency, $country);
                    $progressBar->advance($batchSize);
                }

                $progressBar->finish();

                $output->writeln(' ' . $objName. ' export terminated.');
            } catch (\Exception $exception) {
                $output->writeln('error during '.$objName.' import : '.$exception->getMessage());
                $progressBar->setMessage('<error>'.$exception->getMessage().'</error>');
            }
        }

        return Command::SUCCESS;
    }
}
