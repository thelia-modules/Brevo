<?php

namespace Brevo\Command;

use Brevo\Brevo;
use Brevo\Services\BrevoCategoryService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Command\ContainerAwareCommand;
use Thelia\Model\Base\CategoryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;

class BrevoExportCategoryCommand extends ContainerAwareCommand
{

    public function __construct(
        private BrevoCategoryService $brevoCategoryService
    )
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName("brevo:export:categories")
            ->setDescription("Export categories to brevo");

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initRequest();

        if (null === ConfigQuery::read(Brevo::CONFIG_API_SECRET)) {
            $output->writeln('Please configure this module in the back office');
            return Command::FAILURE;
        }

        $lang = LangQuery::create()->filterByByDefault(1)->findOne();

        $categoryCount = CategoryQuery::create()->count();

        $progressBar = ProgressBarHelper::createProgressBar($output, $categoryCount);

        $batchSize = 100;

        try {
            for ($i = 0; $i < $categoryCount; $i+=$batchSize) {
                $progressBar->setMessage("<info>Exporting $batchSize categories</info>");
                $this->brevoCategoryService->exportCategoryInBatch($batchSize, $i, $lang->getLocale());
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
