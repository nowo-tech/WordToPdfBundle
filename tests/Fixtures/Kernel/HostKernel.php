<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Fixtures\Kernel;

use Nowo\WordToPdfBundle\Export\ExporterInterface;
use Nowo\WordToPdfBundle\Export\PdfWriterInterface;
use Nowo\WordToPdfBundle\Runtime\RuntimeRequirementsChecker;
use Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\DddHost\Domain\ContractPdfRenderer;
use Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\DddHost\Infrastructure\LibreOfficeContractPdfRenderer;
use Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\Ports\DecoratingPdfWriter;
use Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\Ports\DualHostExporter;
use Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\Ports\HostPdfWriter;
use Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\Ports\LegacyHostExporter;
use Nowo\WordToPdfBundle\WordToPdfBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\HttpKernel\Kernel;

use function dirname;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Host kernel for the port tests. `$variant` re-points the bundle's output ports the way a host would in its own
 * services.yaml: `new` (the 1.7 port only), `legacy` (the deprecated port only), `both`, `decorator` (a decorator of
 * the 1.7 port), `ddd` (a DDD host adapter on the framework-free ports), `worker` (two tenant profiles, bundle
 * defaults otherwise) or `none` (bundle defaults). LibreOffice is the
 * fake `soffice` script of the unit tests.
 */
final class HostKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(
        private readonly string $baseDir,
        private readonly string $variant,
    ) {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new WordToPdfBundle();
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return $this->baseDir . '/cache/' . $this->variant;
    }

    public function getLogDir(): string
    {
        return $this->baseDir . '/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'               => 'test-only-not-a-credential',
            'test'                 => true,
            'http_method_override' => false,
            'php_errors'           => ['log' => true],
        ]);
        $container->extension('nowo_word_to_pdf', $this->variant === 'worker' ? [
            'default_profile' => 'alpha',
            'profiles'        => $this->workerProfiles(),
        ] : [
            'profiles' => [
                'default' => [
                    'binary_path' => __DIR__ . '/../fake-soffice.sh',
                    'temp_dir'    => $this->baseDir,
                ],
            ],
        ]);

        $services = $container->services();
        match ($this->variant) {
            'new'       => $this->newPortOnly($services),
            'legacy'    => $this->legacyPortOnly($services),
            'both'      => $this->bothPorts($services),
            'decorator' => $this->decorator($services),
            'ddd'       => $this->dddHost($services),
            default     => null,
        };
    }

    /**
     * Two tenants with their own temp dir and download name; "alpha" also sets `min_version`, so every conversion goes
     * through the per-process binary-version memo of {@see RuntimeRequirementsChecker}.
     *
     * @return array<string, array<string, mixed>>
     */
    private function workerProfiles(): array
    {
        return [
            'alpha' => [
                'binary_path' => __DIR__ . '/../fake-soffice.sh',
                'temp_dir'    => $this->baseDir . '/alpha',
                'min_version' => '24.2',
                'export'      => ['filename' => 'alpha-invoice.pdf'],
            ],
            'beta' => [
                'binary_path' => __DIR__ . '/../fake-soffice.sh',
                'temp_dir'    => $this->baseDir . '/beta',
                'export'      => ['filename' => 'beta-contract.pdf'],
            ],
        ];
    }

    private function newPortOnly(ServicesConfigurator $services): void
    {
        $services->set(HostPdfWriter::class);
        $services->alias(PdfWriterInterface::class, HostPdfWriter::class)->public();
    }

    private function legacyPortOnly(ServicesConfigurator $services): void
    {
        $services->set(LegacyHostExporter::class);
        $services->alias(ExporterInterface::class, LegacyHostExporter::class)->public();
    }

    private function bothPorts(ServicesConfigurator $services): void
    {
        $services->set(DualHostExporter::class);
        $services->alias(PdfWriterInterface::class, DualHostExporter::class)->public();
        $services->alias(ExporterInterface::class, DualHostExporter::class)->public();
    }

    private function decorator(ServicesConfigurator $services): void
    {
        $services->set(DecoratingPdfWriter::class)
            ->decorate(PdfWriterInterface::class)
            ->args([service('.inner')]);
    }

    private function dddHost(ServicesConfigurator $services): void
    {
        $services->set(LibreOfficeContractPdfRenderer::class)->autowire();
        $services->alias(ContractPdfRenderer::class, LibreOfficeContractPdfRenderer::class)->public();
    }
}
