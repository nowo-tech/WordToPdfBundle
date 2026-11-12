<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Integration\Kernel;

use Nowo\WordToPdfBundle\Converter\WordToPdfConverterInterface;
use Nowo\WordToPdfBundle\Export\PdfWriterInterface;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;
use Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\DddHost\Domain\ContractPdfRenderer;
use Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\DddHost\Infrastructure\LibreOfficeContractPdfRenderer;
use Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\HostKernel;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\Filesystem\Filesystem;

use function dirname;
use function file_get_contents;
use function glob;
use function preg_match;
use function sys_get_temp_dir;
use function uniqid;

/**
 * REQ-ARCH-001, DDD host: a host domain port implemented with the bundle's framework-free ports (converter +
 * {@see PdfWriterInterface}) boots, converts (fake `soffice`) and returns PDF bytes; the converted PDF is disposed; none
 * of the files involved imports a Symfony component or Doctrine type.
 */
final class DddHostKernelTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/wtp_ddd_host_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->baseDir);
    }

    public function testHostDomainPortRendersThroughFrameworkFreePorts(): void
    {
        $kernel = new HostKernel($this->baseDir, 'ddd');
        $kernel->boot();

        $renderer = $kernel->getContainer()->get(ContractPdfRenderer::class);
        self::assertInstanceOf(LibreOfficeContractPdfRenderer::class, $renderer);

        self::assertStringStartsWith('%PDF-1.4', $renderer->render('contract-42', dirname(__DIR__, 2) . '/Fixtures/minimal.docx'));
        self::assertSame([], glob($this->baseDir . '/*.pdf') ?: []);

        $kernel->shutdown();
    }

    public function testFrameworkFreeSurfaceHasNoFrameworkTypes(): void
    {
        foreach ([PdfWriterInterface::class, WordToPdfConverterInterface::class, ConvertedPdf::class, LibreOfficeContractPdfRenderer::class, ContractPdfRenderer::class] as $class) {
            $file = (string) (new ReflectionClass($class))->getFileName();
            self::assertSame(0, preg_match('/^use (Doctrine\\\\|Symfony\\\\Component\\\\)/m', (string) file_get_contents($file)), $file);
        }

        foreach ((new ReflectionClass(PdfWriterInterface::class))->getMethods() as $method) {
            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();
                self::assertInstanceOf(ReflectionNamedType::class, $type);
                self::assertContains($type->getName(), [ConvertedPdf::class, 'string', 'mixed'], $method->getName());
            }
            $return = $method->getReturnType();
            self::assertInstanceOf(ReflectionNamedType::class, $return);
            self::assertContains($return->getName(), ['string', 'void'], $method->getName());
        }
    }
}
