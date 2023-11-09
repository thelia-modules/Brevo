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

        try {
            for ($i = 0; $i < $productCount; $i+=100) {
                $this->brevoProductService->exportProductInBatch(100, $i, $lang->getLocale(), $currency, $country);
            }
        }catch (\Exception $exception) {
            $output->writeln('error during import : '.$exception->getMessage());
        }

        return Command::SUCCESS;
    }
}