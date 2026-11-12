<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Integration\Kernel;

use Nowo\WordToPdfBundle\Export\ExporterInterface;
use Nowo\WordToPdfBundle\Export\PdfExporter;
use Nowo\WordToPdfBundle\Export\PdfWriterInterface;
use Nowo\WordToPdfBundle\Http\PdfResponseFactory;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;
use Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\HostKernel;
use Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\Ports\DecoratingPdfWriter;
use Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\Ports\DualHostExporter;
use Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\Ports\HostPdfWriter;
use Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\Ports\LegacyHostExporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

use function ob_get_clean;
use function ob_start;
use function sys_get_temp_dir;
use function uniqid;

/**
 * 1.7.0: `lint:container` (which adds Symfony's `CheckAliasValidityPass`) passes for a host on the new
 * {@see PdfWriterInterface} only, on the deprecated {@see ExporterInterface} only, on both, and with a decorator of the
 * new port; each alias resolves to a service that implements it, and the bundle's own consumer
 * ({@see PdfResponseFactory}) gets the host service of the new port.
 */
final class PortAliasValidityKernelTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/wtp_port_alias_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->baseDir);
    }

    /**
     * @return iterable<string, array{string, class-string, class-string, class-string}>
     */
    public static function hostPorts(): iterable
    {
        // variant, PdfWriterInterface service, ExporterInterface service, writer of PdfResponseFactory
        yield 'bundle defaults' => ['none', PdfExporter::class, PdfExporter::class, PdfExporter::class];
        yield 'new port only' => ['new', HostPdfWriter::class, PdfExporter::class, HostPdfWriter::class];
        yield 'deprecated port only' => ['legacy', PdfExporter::class, LegacyHostExporter::class, PdfExporter::class];
        yield 'both ports' => ['both', DualHostExporter::class, DualHostExporter::class, DualHostExporter::class];
        yield 'decorator of the new port' => ['decorator', DecoratingPdfWriter::class, PdfExporter::class, DecoratingPdfWriter::class];
    }

    /**
     * @param class-string $writer
     * @param class-string $exporter
     * @param class-string $factoryWriter
     */
    #[DataProvider('hostPorts')]
    #[IgnoreDeprecations]
    public function testLintContainerPassesAndConsumersUseTheHostService(string $variant, string $writer, string $exporter, string $factoryWriter): void
    {
        $kernel = new HostKernel($this->baseDir, $variant);
        $kernel->boot();

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $tester = new CommandTester($application->find('lint:container'));

        self::assertSame(0, $tester->execute([]), $tester->getDisplay());
        self::assertStringContainsString('linted successfully', $tester->getDisplay());

        $container = $kernel->getContainer();
        self::assertInstanceOf($writer, $container->get(PdfWriterInterface::class));
        self::assertInstanceOf($exporter, $container->get(ExporterInterface::class));

        $factory = $container->get(PdfResponseFactory::class);
        self::assertInstanceOf(PdfResponseFactory::class, $factory);
        self::assertInstanceOf($factoryWriter, (new ReflectionProperty($factory, 'writer'))->getValue($factory));

        if ($variant === 'decorator') {
            $decorator = $container->get(PdfWriterInterface::class);
            self::assertInstanceOf(DecoratingPdfWriter::class, $decorator);
            self::assertInstanceOf(PdfExporter::class, $decorator->inner);
        }

        if ($variant === 'new') {
            self::assertSame(HostPdfWriter::BYTES, $this->send($factory));
        }

        $kernel->shutdown();
    }

    private function send(PdfResponseFactory $factory): string
    {
        $response = $factory->streamed(new ConvertedPdf(__FILE__, false, 'x.pdf'));
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }
}
