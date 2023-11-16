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
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('brevo:export:customers')
            ->setDescription('Export customers to brevo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initRequest();

        if (null === ConfigQuery::read(Brevo::CONFIG_API_SECRET)) {
            $output->writeln('Please configure this module in the back office');

            return Command::FAILURE;
        }

        $customers = CustomerQuery::create()->find();

        $progressBar = ProgressBarHelper::createProgressBar($output, $customers->count());

        foreach ($customers as $customer) {
            $progressBar->setMessage('<info>'.$customer->getEmail().'</info>');

            try {
                $this->brevoCustomerService->createUpdateContact($customer->getId());
            } catch (\Exception $exception) {
                $progressBar->setMessage('<error>'.$exception->getMessage().'</error>');
            }

            $progressBar->advance();
        }

        $progressBar->finish();

        $output->writeln(' customer export done.');

        return Command::SUCCESS;
    }
}
