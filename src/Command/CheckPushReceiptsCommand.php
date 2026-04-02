<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\CheckPushReceipts;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:push:check-receipts',
    description: 'Dispatch a message to check Expo push notification receipts',
)]
class CheckPushReceiptsCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->messageBus->dispatch(new CheckPushReceipts(
            olderThan: new DateTimeImmutable('-15 minutes'),
        ));

        $io->success('Dispatched CheckPushReceipts message.');

        return Command::SUCCESS;
    }
}
