<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Runtime;

use Nowo\WordToPdfBundle\Config\ResolvedConfig;
use Nowo\WordToPdfBundle\Exception\MissingDependencyException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

use function is_dir;
use function is_writable;
use function preg_match;
use function sprintf;
use function trim;
use function version_compare;

/**
 * Validates that LibreOffice Writer (system package libreoffice-writer) is installed and usable.
 */
class RuntimeRequirementsChecker
{
    public function __construct(
        private readonly LibreOfficeBinaryLocator $locator,
    ) {
    }

    /**
     * @throws MissingDependencyException
     */
    public function assertReady(ResolvedConfig $config): string
    {
        $binary = $this->locator->locate($config->binaryPath);
        if ($binary === null) {
            throw MissingDependencyException::libreOfficeWriterNotFound($config->binaryPath);
        }

        $tempDir = $config->tempDir ?? sys_get_temp_dir();
        if (!is_dir($tempDir) || !is_writable($tempDir)) {
            throw MissingDependencyException::tempDirNotWritable($tempDir);
        }

        if ($config->minVersion !== null && $config->minVersion !== '') {
            $version = $this->readVersion($binary);
            if ($version === null) {
                throw MissingDependencyException::versionUnreadable($binary);
            }
            if (version_compare($version, $config->minVersion, '<')) {
                throw MissingDependencyException::versionTooOld($version, $config->minVersion);
            }
        }

        return $binary;
    }

    public function isReady(ResolvedConfig $config): bool
    {
        try {
            $this->assertReady($config);

            return true;
        } catch (MissingDependencyException) {
            return false;
        }
    }

    /**
     * Human-readable status for CLI / demo UI.
     *
     * @return array{ok: bool, binary: ?string, version: ?string, message: string}
     */
    public function diagnose(ResolvedConfig $config): array
    {
        try {
            $binary  = $this->assertReady($config);
            $version = $this->readVersion($binary);

            return [
                'ok'      => true,
                'binary'  => $binary,
                'version' => $version,
                'message' => sprintf(
                    'LibreOffice Writer is ready (%s%s).',
                    $binary,
                    $version !== null ? ', version ' . $version : '',
                ),
            ];
        } catch (MissingDependencyException $e) {
            return [
                'ok'      => false,
                'binary'  => null,
                'version' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function readVersion(string $binary): ?string
    {
        $process = new Process([$binary, '--version']);
        $process->setTimeout(15);

        try {
            $process->mustRun();
        } catch (ProcessFailedException) {
            return null;
        }

        $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());
        // @codeCoverageIgnoreStart
        if ($output === '') {
            return null;
        }
        // @codeCoverageIgnoreEnd

        if (preg_match('/(\d+\.\d+(?:\.\d+)?)/', $output, $m) === 1) {
            return $m[1];
        }

        // @codeCoverageIgnoreStart
        return null;
        // @codeCoverageIgnoreEnd
    }
}
