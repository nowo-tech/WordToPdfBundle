<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit;

use Nowo\WordToPdfBundle\DependencyInjection\WordToPdfExtension;
use Nowo\WordToPdfBundle\WordToPdfBundle;
use PHPUnit\Framework\TestCase;

final class WordToPdfBundleTest extends TestCase
{
    public function testExtensionAlias(): void
    {
        $bundle = new WordToPdfBundle();
        $ext    = $bundle->getContainerExtension();
        self::assertInstanceOf(WordToPdfExtension::class, $ext);
        self::assertSame('nowo_word_to_pdf', $ext->getAlias());
        self::assertSame($ext, $bundle->getContainerExtension());
    }
}
