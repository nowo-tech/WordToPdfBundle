<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Runtime;

use FilesystemIterator;
use Nowo\WordToPdfBundle\Config\ResolvedConfig;
use Nowo\WordToPdfBundle\Exception\MissingDependencyException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Service\ResetInterface;

use function is_dir;
use function is_writable;
use function mkdir;
use function preg_match;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function trim;
use function uniqid;
use function unlink;
use function version_compare;

use const DIRECTORY_SEPARATOR;

/**
 * Validates that LibreOffice Writer (system package libreoffice-writer) is installed and usable.
 *
 * {@code soffice --version} runs with a throw-away {@code -env:UserInstallation} profile (never the shared
 * ~/.config/libreoffice one) and its result is memoized per binary path until {@see reset()} (kernel.reset,
 * i.e. once per request under FrankenPHP worker mode), so batches and repeated checks spawn it only once.
 */
class RuntimeRequirementsChecker implements ResetInterface
{
    /** @var array<string, string> binary path => version */
    private array $versions = [];

    /**
     * @param LibreOfficeBinaryLocator $locator Binary locator
     * @param float $versionTimeout Seconds allowed for {@code soffice --version}
     */
    public function __construct(
        private readonly LibreOfficeBinaryLocator $locator,
        private readonly float $versionTimeout = 15.0,
    ) {
    }

    /**
     * Assert Writer is ready and return the resolved binary path.
     *
     * @param ResolvedConfig $config Resolved conversion profile
     *
     * @throws MissingDependencyException
     *
     * @return string Absolute path to the LibreOffice binary
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

    /**
     * Forget memoized LibreOffice versions (kernel.reset).
     */
    public function reset(): void
    {
        $this->versions = [];
    }

    /**
     * Whether LibreOffice Writer is ready for the given config.
     *
     * @param ResolvedConfig $config Resolved conversion profile
     *
     * @return bool
     */
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
     * @param ResolvedConfig $config Resolved conversion profile
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
        if (isset($this->versions[$binary])) {
            return $this->versions[$binary];
        }

        $profileDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'word_to_pdf_version_' . uniqid('', true);
        // @codeCoverageIgnoreStart
        if (!@mkdir($profileDir, 0700, true) && !is_dir($profileDir)) {
            return null;
        }
        // @codeCoverageIgnoreEnd

        $process = new Process([$binary, '-env:UserInstallation=file://' . $profileDir, '--version']);
        $process->setTimeout($this->versionTimeout);
        $process->setIdleTimeout($this->versionTimeout);

        try {
            $process->mustRun();
        } catch (ProcessTimedOutException|ProcessFailedException) {
            // Failures are not memoized: a transient error must not disable the check for the worker's lifetime.
            return null;
        } finally {
            $this->removeDir($profileDir);
        }

        $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());
        if ($output === '') {
            return null;
        }

        if (preg_match('/(\d+\.\d+(?:\.\d+)?)/', $output, $m) === 1) {
            return $this->versions[$binary] = $m[1];
        }

        return null;
    }

    private function removeDir(string $dir): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }
}
