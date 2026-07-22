<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\Command;

use Nowo\WordToPdfBundle\Command\CheckRuntimeCommand;
use Nowo\WordToPdfBundle\Config\ProfileResolver;
use Nowo\WordToPdfBundle\Runtime\RuntimeRequirementsChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckRuntimeCommandTest extends TestCase
{
    public function testFailureExitCode(): void
    {
        $checker = $this->createMock(RuntimeRequirementsChecker::class);
        $checker->method('diagnose')->willReturn([
            'ok'      => false,
            'binary'  => null,
            'version' => null,
            'message' => "LibreOffice Writer not found.\nInstall libreoffice-writer",
        ]);

        $command = new CheckRuntimeCommand(
            $checker,
            new ProfileResolver(['default' => []], 'default'),
        );
        $tester = new CommandTester($command);
        $code   = $tester->execute([]);
        self::assertSame(1, $code);
        self::assertStringContainsString('libreoffice-writer', $tester->getDisplay());
    }

    public function testSuccessExitCode(): void
    {
        $checker = $this->createMock(RuntimeRequirementsChecker::class);
        $checker->method('diagnose')->willReturn([
            'ok'      => true,
            'binary'  => '/usr/bin/soffice',
            'version' => '24.2.0',
            'message' => 'LibreOffice Writer is ready (/usr/bin/soffice, version 24.2.0).',
        ]);

        $command = new CheckRuntimeCommand(
            $checker,
            new ProfileResolver(['default' => []], 'default'),
        );
        $tester = new CommandTester($command);
        self::assertSame(0, $tester->execute(['--profile' => 'default']));
        self::assertStringContainsString('/usr/bin/soffice', $tester->getDisplay());
    }
}
