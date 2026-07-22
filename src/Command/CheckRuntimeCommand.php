<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Command;

use Nowo\WordToPdfBundle\Config\ProfileResolver;
use Nowo\WordToPdfBundle\Runtime\RuntimeRequirementsChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function is_string;

#[AsCommand(
    name: 'nowo:word-to-pdf:check',
    description: 'Check that LibreOffice Writer (libreoffice-writer / soffice) is installed and ready for Word → PDF conversion.',
)]
final class CheckRuntimeCommand extends Command
{
    public function __construct(
        private readonly RuntimeRequirementsChecker $checker,
        private readonly ProfileResolver $profileResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('profile', 'p', InputOption::VALUE_REQUIRED, 'Named profile to check (default: configured default_profile)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $profile = $input->getOption('profile');
        $key     = is_string($profile) && $profile !== '' ? $profile : $this->profileResolver->getDefaultProfileKey();
        $config  = $this->profileResolver->resolve($key);
        $result  = $this->checker->diagnose($config);

        if ($result['ok']) {
            $io->success($result['message']);
            if ($result['binary'] !== null) {
                $io->writeln('Binary:  ' . $result['binary']);
            }
            if ($result['version'] !== null) {
                $io->writeln('Version: ' . $result['version']);
            }

            return Command::SUCCESS;
        }

        $io->error('LibreOffice Writer is missing or not usable.');
        $io->writeln($result['message']);
        $io->note([
            'Install the system package, for example:',
            '  Debian/Ubuntu:  sudo apt-get install -y libreoffice-writer',
            '  Alpine:         apk add libreoffice',
            '  Fedora/RHEL:    sudo dnf install -y libreoffice-writer',
        ]);

        return Command::FAILURE;
    }
}
