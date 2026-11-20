<?php
// src/Command/MikrotikPollCommand.php
namespace App\Command;

use App\Entity\MikrotikDevice;
use App\Service\MikrotikService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mikrotik:poll',
    description: 'Poll MikroTik wifi stations (RouterOS v6 API) and store registration-table stats'
)]
class MikrotikPollCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private MikrotikService $mikrotik,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('device', 'd', InputOption::VALUE_REQUIRED, 'Only poll this device ID')
            ->addOption('retention', 'r', InputOption::VALUE_REQUIRED, 'Delete stats older than N days (0 = keep forever)', '90');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repo = $this->em->getRepository(MikrotikDevice::class);

        $devices = $input->getOption('device')
            ? array_filter([$repo->find((int) $input->getOption('device'))])
            : $repo->findBy(['active' => true], ['name' => 'ASC']);

        $rows = [];
        foreach ($devices as $device) {
            $stats = $this->mikrotik->poll($device);
            $this->em->flush();
            foreach ($stats as $s) {
                $rows[] = [
                    $device->getName(),
                    $s->getStatus(),
                    $s->getRemoteName() ?? $s->getRemoteMac() ?? '-',
                    $s->getSignalStrength() !== null ? $s->getSignalStrength() . ' dBm' : '-',
                    $s->getSignalToNoise() !== null ? $s->getSignalToNoise() . ' dB' : '-',
                    $s->getTxCcq() !== null ? $s->getTxCcq() . '%' : '-',
                    ($s->getTxRate() ?? '-') . ' / ' . ($s->getRxRate() ?? '-'),
                    $s->getErrorMessage() ?? '',
                ];
            }
        }

        if ($rows) {
            $io->table(['Device', 'Status', 'Remote', 'Signal', 'SNR', 'TX CCQ', 'TX / RX rate', 'Error'], $rows);
        } else {
            $io->note('No active MikroTik devices configured.');
        }

        $retention = (int) $input->getOption('retention');
        if ($retention > 0) {
            $deleted = $this->mikrotik->prune($retention);
            if ($deleted) {
                $io->text(sprintf('Pruned %d stat rows older than %d days.', $deleted, $retention));
            }
        }

        return Command::SUCCESS;
    }
}
