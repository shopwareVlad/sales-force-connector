<?php declare(strict_types=1);

namespace ItDe\SalesforceConnector\Command;

use ItDe\SalesforceConnector\MessageQueue\Message\SyncOrderMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'itde:salesforce:sync-orders',
    description: 'Dispatch async Salesforce sync messages for all Shopware orders.'
)]
class SyncAllOrdersCommand extends Command
{
    private const BATCH_SIZE = 250;

    public function __construct(
        private readonly EntityRepository   $orderRepository,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createDefaultContext();
        $offset  = 0;
        $total   = 0;

        $output->writeln(sprintf(
            'Dispatching sync messages for orders in batches of %d…',
            self::BATCH_SIZE
        ));

        $progress = new ProgressBar($output);
        $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%%  — Elapsed: %elapsed:6s%, ETA: %estimated:-6s%   ');

        do {
            $criteria = (new Criteria())
                ->addSorting(new FieldSorting('orderDateTime', FieldSorting::ASCENDING))
                ->setOffset($offset)
                ->setLimit(self::BATCH_SIZE);

            $orders = $this->orderRepository->search($criteria, $context);

            if ($total === 0) {
                $progress->start((int) $orders->getTotal());
            }

            foreach ($orders as $order) {
                $this->bus->dispatch(new SyncOrderMessage($order->getId(), $context));
                ++$total;
                $progress->advance();
            }

            $offset += $orders->count();
        } while ($orders->count() === self::BATCH_SIZE);

        $progress->finish();
        $output->writeln('');
        $output->writeln(sprintf('Done. %d sync message(s) dispatched to the queue.', $total));

        return Command::SUCCESS;
    }
}
