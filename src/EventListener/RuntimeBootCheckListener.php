<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\EventListener;

use Nowo\WordToPdfBundle\Config\ProfileResolver;
use Nowo\WordToPdfBundle\Exception\MissingDependencyException;
use Nowo\WordToPdfBundle\Runtime\RuntimeRequirementsChecker;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Optional boot-time check when profile check_on_boot is true.
 */
final class RuntimeBootCheckListener implements EventSubscriberInterface
{
    private bool $checked = false;

    private readonly LoggerInterface $logger;

    /**
     * @param bool $enabled Whether boot check is enabled
     * @param string $bootFailure Failure mode (exception|warning)
     * @param RuntimeRequirementsChecker $requirementsChecker Runtime checker
     * @param ProfileResolver $profileResolver Profile resolver
     * @param LoggerInterface|null $logger Optional logger
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly string $bootFailure,
        private readonly RuntimeRequirementsChecker $requirementsChecker,
        private readonly ProfileResolver $profileResolver,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Return kernel events this subscriber listens to.
     *
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024],
        ];
    }

    /**
     * Optionally assert LibreOffice readiness on the first request.
     *
     * @param RequestEvent $event Kernel request event
     *
     * @return void
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->enabled || $this->checked || !$event->isMainRequest()) {
            return;
        }

        $this->checked = true;

        try {
            $this->requirementsChecker->assertReady($this->profileResolver->resolveDefault());
        } catch (MissingDependencyException $e) {
            if ($this->bootFailure === 'warning') {
                $this->logger->warning($e->getMessage());

                return;
            }

            throw $e;
        }
    }
}
