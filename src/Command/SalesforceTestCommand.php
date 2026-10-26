<?php

declare(strict_types=1);

namespace ItDe\SalesforceConnector\Command;

use ItDe\SalesforceConnector\Service\SalesforceConnectionTester;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'itde:salesforce:test',
    description: 'Tests Salesforce JWT auth and verifies that all required Salesforce fields exist.'
)]
#[Package('framework')]
final class SalesforceTestCommand extends Command
{
    public function __construct(private readonly SalesforceConnectionTester $tester)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->tester->run();

        foreach ($result['steps'] as $i => $step) {
            $step['passed']
                ? $output->writeln(sprintf('<info>  ✓ %s</info>', $step['name']))
                : $output->writeln(sprintf('<error>  ✗ %s</error>', $step['name']));
            $output->writeln(sprintf('    %s', $step['message']));

            if ($i < \count($result['steps']) - 1) {
                $output->writeln('');
            }
        }

        $output->writeln('');

        if ($result['success']) {
            $output->writeln('<info>All checks passed — connector is ready to sync customers.</info>');

            return Command::SUCCESS;
        }

        $output->writeln('<error>Setup incomplete — fix the issues above before running a sync.</error>');

        return Command::FAILURE;
    }
}
