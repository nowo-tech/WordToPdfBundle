<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\EventListener;

use Nowo\WordToPdfBundle\Config\ProfileResolver;
use Nowo\WordToPdfBundle\EventListener\RuntimeBootCheckListener;
use Nowo\WordToPdfBundle\Exception\MissingDependencyException;
use Nowo\WordToPdfBundle\Runtime\RuntimeRequirementsChecker;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class RuntimeBootCheckListenerTest extends TestCase
{
    public function testDisabledDoesNothing(): void
    {
        $checker = $this->createMock(RuntimeRequirementsChecker::class);
        $checker->expects(self::never())->method('assertReady');

        $listener = new RuntimeBootCheckListener(
            false,
            'exception',
            $checker,
            new ProfileResolver(['default' => []], 'default'),
        );
        $listener->onKernelRequest($this->mainRequestEvent());
    }

    public function testWarningModeLogs(): void
    {
        $checker = $this->createMock(RuntimeRequirementsChecker::class);
        $checker->method('assertReady')
            ->willThrowException(MissingDependencyException::libreOfficeWriterNotFound());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $listener = new RuntimeBootCheckListener(
            true,
            'warning',
            $checker,
            new ProfileResolver(['default' => []], 'default'),
            $logger,
        );
        $listener->onKernelRequest($this->mainRequestEvent());
    }

    public function testExceptionModeThrows(): void
    {
        $checker = $this->createMock(RuntimeRequirementsChecker::class);
        $checker->method('assertReady')
            ->willThrowException(MissingDependencyException::libreOfficeWriterNotFound());

        $listener = new RuntimeBootCheckListener(
            true,
            'exception',
            $checker,
            new ProfileResolver(['default' => []], 'default'),
        );
        $this->expectException(MissingDependencyException::class);
        $listener->onKernelRequest($this->mainRequestEvent());
    }

    public function testSubscribedEvents(): void
    {
        self::assertArrayHasKey('kernel.request', RuntimeBootCheckListener::getSubscribedEvents());
    }

    private function mainRequestEvent(): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST);
    }
}
