<?php

namespace Brevo\Command;

use Brevo\Api\BrevoClient;
use Brevo\Brevo;
use Brevo\Services\BrevoCustomerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Command\ContainerAwareCommand;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;

class BrevoExportCustomerCommand extends ContainerAwareCommand
{

    public function __construct(
        private BrevoCustomerService $brevoCustomerService
    )
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName("brevo:export:customers")
            ->setDescription("Export customers to brevo");

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initRequest();

        if (null === ConfigQuery::read(Brevo::CONFIG_API_SECRET)) {
            $output->writeln('Please configure this module in the back office');
            return Command::FAILURE;
        }

        $customers = CustomerQuery::create()->find();

        try {
            foreach ($customers as $customer) {
                $this->brevoCustomerService->createUpdateContact($customer->getId());
            }

        }catch (\Exception $exception) {
            $output->writeln('error during import : '.$exception->getMessage());
        }

        return Command::SUCCESS;
    }
}