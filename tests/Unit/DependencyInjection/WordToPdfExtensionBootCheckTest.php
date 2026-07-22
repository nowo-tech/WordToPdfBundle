<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\DependencyInjection;

use Nowo\WordToPdfBundle\DependencyInjection\WordToPdfExtension;
use Nowo\WordToPdfBundle\EventListener\RuntimeBootCheckListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class WordToPdfExtensionBootCheckTest extends TestCase
{
    public function testBootCheckKeepsSubscriberTag(): void
    {
        $container = new ContainerBuilder();
        $extension = new WordToPdfExtension();
        $extension->load([[
            'profiles' => [
                'default' => [
                    'check_on_boot' => true,
                    'boot_failure'  => 'warning',
                ],
            ],
        ]], $container);

        $def = $container->getDefinition(RuntimeBootCheckListener::class);
        self::assertTrue($def->hasTag('kernel.event_subscriber'));
        self::assertTrue($def->getArgument('$enabled'));
        self::assertSame('warning', $def->getArgument('$bootFailure'));
    }
}
