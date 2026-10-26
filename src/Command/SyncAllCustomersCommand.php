<?php

declare(strict_types=1);

namespace ItDe\SalesforceConnector\Command;

use ItDe\SalesforceConnector\MessageQueue\Message\SyncCustomerMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'itde:salesforce:sync-customers',
    description: 'Dispatches async sync messages for all (or filtered) Shopware customers to Salesforce.'
)]
#[Package('framework')]
final class SyncAllCustomersCommand extends Command
{
    private const DEFAULT_BATCH_SIZE = 100;

    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'batch-size',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Number of customers to process per batch',
                self::DEFAULT_BATCH_SIZE
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Maximum total number of customers to process (default: all)',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createDefaultContext();
        $batchSize = max(1, (int) $input->getOption('batch-size'));
        $maxTotal  = $input->getOption('limit') !== null ? max(1, (int) $input->getOption('limit')) : null;

        $totalCriteria = new Criteria();
        $total = $this->customerRepository->search($totalCriteria, $context)->getTotal();

        if ($maxTotal !== null) {
            $total = min($total, $maxTotal);
        }

        if ($total === 0) {
            $output->writeln('<comment>No customers found.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Dispatching sync messages for %d customer(s) in batches of %d…</info>', $total, $batchSize));

        $progressBar = new ProgressBar($output, $total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — Elapsed: %elapsed:6s%, ETA: %estimated:-6s%');
        $progressBar->start();

        $offset    = 0;
        $dispatched = 0;

        while ($dispatched < $total) {
            $batchLimit = $maxTotal !== null
                ? min($batchSize, $total - $dispatched)
                : $batchSize;

            $criteria = (new Criteria())
                ->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING))
                ->setOffset($offset)
                ->setLimit($batchLimit);

            $customers = $this->customerRepository->search($criteria, $context);

            if ($customers->count() === 0) {
                break;
            }

            foreach ($customers as $customer) {
                $this->bus->dispatch(new SyncCustomerMessage(
                    $customer->getId(),
                    $context
                ));
                ++$dispatched;
                $progressBar->advance();
            }

            $offset += $customers->count();
        }

        $progressBar->finish();
        $output->writeln('');
        $output->writeln(sprintf('<info>Done. %d sync message(s) dispatched to the queue.</info>', $dispatched));

        return Command::SUCCESS;
    }
}

