<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Integration\Kernel;

use Nowo\WordToPdfBundle\Converter\WordToPdfConverter;
use Nowo\WordToPdfBundle\Converter\WordToPdfConverterInterface;
use Nowo\WordToPdfBundle\Export\PdfWriterInterface;
use Nowo\WordToPdfBundle\Runtime\RuntimeRequirementsChecker;
use Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\HostKernel;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Filesystem\Filesystem;

use function array_key_first;
use function count;
use function dirname;
use function gc_collect_cycles;
use function glob;
use function memory_get_usage;
use function mkdir;
use function realpath;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;

/**
 * REQ-RUNTIME-002: one kept-alive kernel, the services resetter never runs (FrankenPHP worker mode with kernel reset
 * false and no `services_resetter`), N conversions through the fake `soffice` as two tenants in alternation.
 *
 * Each conversion must use its own tenant's profile (download name, temp dir), leave no temporary PDF, conversion
 * workspace or version-probe profile behind, and memory must stay flat. The binary-version memo of
 * {@see RuntimeRequirementsChecker} is per process (one entry per binary path, no request data) and stays bounded.
 */
final class WorkerKeptAliveKernelTest extends TestCase
{
    /** Conversions; each spawns the fake `soffice` (one child process), so N is kept moderate. */
    private const ITERATIONS = 300;

    /** Iterations run before the memory baseline is taken. */
    private const WARM_UP = 30;

    /** Allowed memory growth after the warm-up. */
    private const MEMORY_MARGIN_BYTES = 2 * 1024 * 1024;

    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/wtp_worker_' . uniqid('', true);
        foreach (['alpha', 'beta', 'src'] as $dir) {
            mkdir($this->baseDir . '/' . $dir, 0700, true);
        }
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->baseDir);
    }

    public function testConversionsInOneKernelWithoutResetterLeaveNothingBehindAndKeepMemoryFlat(): void
    {
        $fixture = dirname(__DIR__, 2) . '/Fixtures/minimal.docx';
        $sources = [
            'alpha' => $this->baseDir . '/src/alpha.docx',
            'beta'  => $this->baseDir . '/src/beta.docx',
        ];
        $filesystem = new Filesystem();
        foreach ($sources as $source) {
            $filesystem->copy($fixture, $source);
        }
        $expectedNames   = ['alpha' => 'alpha-invoice.pdf', 'beta' => 'beta-contract.pdf'];
        $versionProfiles = count(glob(sys_get_temp_dir() . '/word_to_pdf_version_*') ?: []);

        $kernel = new HostKernel($this->baseDir, 'worker');
        $kernel->boot();
        $container = $kernel->getContainer();
        // Services are fetched once and kept, as a kept-alive worker does; `services_resetter` is never called.
        $converter = $container->get(WordToPdfConverterInterface::class);
        $writer    = $container->get(PdfWriterInterface::class);
        self::assertInstanceOf(WordToPdfConverter::class, $converter);
        $checker = (new ReflectionProperty(WordToPdfConverter::class, 'requirementsChecker'))->getValue($converter);
        self::assertInstanceOf(PdfWriterInterface::class, $writer);
        self::assertInstanceOf(RuntimeRequirementsChecker::class, $checker);

        $baseline = 0;
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            if ($i === self::WARM_UP) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }

            $tenant = $i % 2 === 0 ? 'alpha' : 'beta';
            $other  = $tenant === 'alpha' ? 'beta' : 'alpha';
            $pdf    = $converter->convertWithProfile($sources[$tenant], $tenant);

            self::assertSame($expectedNames[$tenant], $pdf->suggestedFilename(), sprintf('iteration %d', $i));
            self::assertSame($this->baseDir . '/' . $tenant, dirname($pdf->path()), sprintf('iteration %d', $i));
            self::assertFileExists($pdf->path());
            self::assertStringStartsWith('%PDF-1.4', $writer->toBytes($pdf));
            self::assertFileDoesNotExist($pdf->path(), 'toBytes() disposes the temporary PDF');

            // Nothing of either tenant is left on disk: no result PDF, no conversion workspace.
            foreach ([$tenant, $other] as $dir) {
                self::assertSame([], glob($this->baseDir . '/' . $dir . '/*') ?: [], sprintf('iteration %d, %s', $i, $dir));
            }
        }

        gc_collect_cycles();
        $growth = memory_get_usage() - $baseline;
        self::assertLessThan(self::MEMORY_MARGIN_BYTES, $growth, sprintf('memory grew by %d bytes over %d conversions', $growth, self::ITERATIONS - self::WARM_UP));

        // The version memo holds one entry per binary path (process data, not request data) and its probe profiles
        // are removed; it is cleared only by kernel.reset, which this worker never runs.
        $versions = (new ReflectionProperty(RuntimeRequirementsChecker::class, 'versions'))->getValue($checker);
        self::assertIsArray($versions);
        self::assertCount(1, $versions);
        self::assertSame(realpath(dirname(__DIR__, 2) . '/Fixtures/fake-soffice.sh'), realpath((string) array_key_first($versions)));
        self::assertSame('24.2.0', $versions[array_key_first($versions)]);
        self::assertCount($versionProfiles, glob(sys_get_temp_dir() . '/word_to_pdf_version_*') ?: []);

        $kernel->shutdown();
    }
}
