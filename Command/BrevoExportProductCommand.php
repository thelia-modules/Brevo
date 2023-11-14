<?php

namespace Brevo\Command;

use Brevo\Brevo;
use Brevo\Services\BrevoProductService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Command\ContainerAwareCommand;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\ProductQuery;

class BrevoExportProductCommand extends ContainerAwareCommand
{

    public function __construct(
        private BrevoProductService $brevoProductService,
    )
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName("brevo:export:products")
            ->setDescription("Export products to brevo");

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initRequest();

        if (null === ConfigQuery::read(Brevo::CONFIG_API_SECRET)) {
            $output->writeln('Please configure this module in the back office');
            return Command::FAILURE;
        }

        $lang = LangQuery::create()->filterByByDefault(1)->findOne();
        $currency = CurrencyQuery::create()->filterByByDefault(1)->findOne();
        $country = CountryQuery::create()->filterByByDefault(1)->findOne();

        $productCount = ProductQuery::create()->count();

        $batchSize = 100;

        $progressBar = ProgressBarHelper::createProgressBar($output, $productCount);

        try {
            for ($i = 0; $i < $productCount; $i+=$batchSize) {
                $progressBar->setMessage("<info>Exporting $batchSize products</info>");
                $this->brevoProductService->exportProductInBatch($batchSize, $i, $lang->getLocale(), $currency, $country);
                $progressBar->advance($batchSize);
            }

            $progressBar->finish();
        } catch (\Exception $exception) {
            $output->writeln('error during import : '.$exception->getMessage());
            $progressBar->setMessage("<error>".$exception->getMessage()."</error>");
        }

        return Command::SUCCESS;
    }
}
