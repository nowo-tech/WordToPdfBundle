<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Runtime;

use FilesystemIterator;
use Nowo\WordToPdfBundle\Config\ResolvedConfig;
use Nowo\WordToPdfBundle\Exception\ConversionFailedException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

use function basename;
use function bin2hex;
use function chmod;
use function copy;
use function escapeshellarg;
use function file_exists;
use function filesize;
use function function_exists;
use function hrtime;
use function is_dir;
use function is_file;
use function mkdir;
use function pathinfo;
use function preg_match;
use function preg_replace;
use function random_bytes;
use function sprintf;
use function str_contains;
use function strtolower;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

/**
 * Runs LibreOffice headless conversion: Word → PDF.
 *
 * Compatible with PHP-FPM and FrankenPHP (Symfony Process / proc_open).
 * Always applies {@see ResolvedConfig::$timeout} and force-stops the process tree on
 * timeout/failure so FrankenPHP workers are not left with orphaned `soffice` children.
 *
 * Logs start/success/timeout/failure/orphan reaping through PSR-3 (REQ-OBS-001). Log context
 * never contains document contents, file names or paths (source, workspace, PDF), nor the
 * LibreOffice stdout/stderr (which can echo paths): only sizes, durations, exit codes,
 * the export filter and the configured timeout.
 */
class LibreOfficeProcessRunner
{
    private readonly LoggerInterface $logger;

    /**
     * @param LoggerInterface|null $logger Optional PSR-3 logger (NullLogger when absent)
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Run LibreOffice headless conversion and return the absolute PDF path.
     *
     * Applies profile timeout as wall-clock and idle timeout; on expiry or failure
     * force-stops the process and reaps orphaned LibreOffice children (REQ-RUNTIME-001).
     *
     * @param string $sourcePath Absolute path to the Word document
     * @param string $binary Absolute path to soffice/libreoffice
     * @param ResolvedConfig $config Resolved conversion profile
     *
     * @throws ConversionFailedException
     *
     * @return non-empty-string Absolute path to the generated PDF
     */
    public function convert(string $sourcePath, string $binary, ResolvedConfig $config): string
    {
        $startedAt = hrtime(true);
        $context   = [
            'bundle'           => 'word_to_pdf',
            'action'           => 'convert',
            'filter'           => $config->filter,
            'timeout_seconds'  => $config->timeout,
            'source_extension' => strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)),
            'source_bytes'     => @filesize($sourcePath) ?: 0,
        ];
        $this->logger->info('Word to PDF conversion started.', $context);

        $baseTemp = $config->tempDir ?? sys_get_temp_dir();
        $workDir  = $baseTemp . DIRECTORY_SEPARATOR . 'word_to_pdf_' . uniqid('', true);
        // @codeCoverageIgnoreStart
        if (!@mkdir($workDir, 0700, true) && !is_dir($workDir)) {
            $this->logger->error('Word to PDF conversion failed: could not create the conversion workspace.', $context + ['reason' => 'workspace']);
            throw new ConversionFailedException(sprintf('Could not create conversion workspace "%s".', $workDir));
        }
        // @codeCoverageIgnoreEnd

        $userProfile = $workDir . DIRECTORY_SEPARATOR . 'lo_profile';
        // @codeCoverageIgnoreStart
        if (!@mkdir($userProfile, 0700, true) && !is_dir($userProfile)) {
            $this->cleanupDir($workDir);
            $this->logger->error('Word to PDF conversion failed: could not create the LibreOffice user profile.', $context + ['reason' => 'user_profile']);
            throw new ConversionFailedException(sprintf('Could not create LibreOffice user profile "%s".', $userProfile));
        }
        // @codeCoverageIgnoreEnd

        $inputName = $this->safeBasename($sourcePath);
        $inputPath = $workDir . DIRECTORY_SEPARATOR . $inputName;
        // @codeCoverageIgnoreStart
        if (!@copy($sourcePath, $inputPath)) {
            $this->cleanupDir($workDir);
            $this->logger->error('Word to PDF conversion failed: could not copy the source into the workspace.', $context + ['reason' => 'copy_source']);
            throw new ConversionFailedException(sprintf('Could not copy source file into workspace from "%s".', $sourcePath));
        }
        // @codeCoverageIgnoreEnd

        $outDir = $workDir . DIRECTORY_SEPARATOR . 'out';
        // @codeCoverageIgnoreStart
        if (!@mkdir($outDir, 0700, true) && !is_dir($outDir)) {
            $this->cleanupDir($workDir);
            $this->logger->error('Word to PDF conversion failed: could not create the output directory.', $context + ['reason' => 'output_dir']);
            throw new ConversionFailedException(sprintf('Could not create output directory "%s".', $outDir));
        }
        // @codeCoverageIgnoreEnd

        $filter  = $config->filter;
        $process = new Process([
            $binary,
            '--headless',
            '--nologo',
            '--nolockcheck',
            '--nodefault',
            '--nofirststartwizard',
            '-env:UserInstallation=file://' . $userProfile,
            '--convert-to',
            $filter,
            '--outdir',
            $outDir,
            $inputPath,
        ]);

        // Hard wall-clock + idle caps: under FrankenPHP a blocked worker must not keep soffice forever.
        $timeoutSeconds = (float) $config->timeout;
        $process->setTimeout($timeoutSeconds);
        $process->setIdleTimeout($timeoutSeconds);

        try {
            $process->mustRun();
        } catch (ProcessTimedOutException $e) {
            $this->forceStopProcess($process, $userProfile);
            $this->cleanupDir($workDir);
            $this->logger->warning('Word to PDF conversion timed out; LibreOffice was force-stopped.', $context + [
                'reason'      => 'timeout',
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);
            throw new ConversionFailedException(sprintf('LibreOffice conversion timed out after %d seconds.', $config->timeout), 0, $e);
        } catch (ProcessFailedException $e) {
            $this->forceStopProcess($process, $userProfile);
            $this->cleanupDir($workDir);
            $this->logger->error('Word to PDF conversion failed: LibreOffice exited with an error.', $context + [
                'reason'      => 'process_failed',
                'exit_code'   => $process->getExitCode(),
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);
            throw new ConversionFailedException(sprintf('LibreOffice conversion failed: %s', trim($process->getErrorOutput() !== '' ? $process->getErrorOutput() : $process->getOutput())), 0, $e);
        }

        $expectedPdf = $outDir . DIRECTORY_SEPARATOR . pathinfo($inputName, PATHINFO_FILENAME) . '.pdf';
        if (!is_file($expectedPdf)) {
            $this->forceStopProcess($process, $userProfile);
            $this->cleanupDir($workDir);
            $this->logger->error('Word to PDF conversion failed: LibreOffice did not produce a PDF.', $context + [
                'reason'      => 'no_output',
                'exit_code'   => $process->getExitCode(),
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);
            throw new ConversionFailedException(sprintf('LibreOffice did not produce the expected PDF at "%s". Output: %s', $expectedPdf, trim($process->getOutput() . "\n" . $process->getErrorOutput())));
        }

        // Random (not time-based) name in the shared temp dir, owner-only below: converted documents may hold personal data.
        $finalPath = $baseTemp . DIRECTORY_SEPARATOR . 'word_to_pdf_result_' . bin2hex(random_bytes(16)) . '.pdf';
        // @codeCoverageIgnoreStart
        if (!@rename($expectedPdf, $finalPath) && !@copy($expectedPdf, $finalPath)) {
            $this->cleanupDir($workDir);
            $this->logger->error('Word to PDF conversion failed: could not move the generated PDF.', $context + ['reason' => 'move_output']);
            throw new ConversionFailedException('Could not move generated PDF to a stable temporary path.');
        }
        // @codeCoverageIgnoreEnd
        chmod($finalPath, 0o600);

        $this->cleanupDir($workDir);
        $this->logger->info('Word to PDF conversion succeeded.', $context + [
            'pdf_bytes'   => @filesize($finalPath) ?: 0,
            'duration_ms' => $this->elapsedMs($startedAt),
        ]);

        return $finalPath;
    }

    /**
     * Ensure the Symfony Process is dead and try to reap LibreOffice child processes
     * bound to this conversion's UserInstallation profile (common orphan under FrankenPHP).
     */
    private function forceStopProcess(Process $process, string $userProfile): void
    {
        try {
            if ($process->isRunning()) {
                // 0 = do not wait for graceful SIGTERM; escalate quickly to SIGKILL.
                $process->stop(0);
            }
            // @codeCoverageIgnoreStart
        } catch (Throwable) {
            // Best-effort cleanup only.
        }
        // @codeCoverageIgnoreEnd

        $this->killOrphanedLibreOfficeForProfile($userProfile);
    }

    /**
     * LibreOffice often spawns `soffice.bin` children that outlive the wrapper when a worker is aborted.
     * Only matches our generated workspaces (`word_to_pdf_*`).
     */
    private function killOrphanedLibreOfficeForProfile(string $userProfile): void
    {
        if ($userProfile === '' || !str_contains($userProfile, 'word_to_pdf_')) {
            // @codeCoverageIgnoreStart
            return;
            // @codeCoverageIgnoreEnd
        }

        // Reject unexpected characters before interpolating into a shell pattern.
        if (preg_match('#^[A-Za-z0-9_./:-]+$#', $userProfile) !== 1) {
            return;
        }

        if (!function_exists('exec')) {
            // @codeCoverageIgnoreStart
            return;
            // @codeCoverageIgnoreEnd
        }

        $pattern = 'UserInstallation=file://' . $userProfile;
        // pkill may be absent on minimal images; ignore failures.
        $output   = [];
        $exitCode = -1;
        @exec('pkill -9 -f ' . escapeshellarg($pattern) . ' 2>/dev/null', $output, $exitCode);
        // pkill: 0 = at least one orphan killed, 1 = none matched, other = pkill missing/failed.
        $this->logger->info('Word to PDF orphan reaping finished.', [
            'bundle'        => 'word_to_pdf',
            'action'        => 'reap_orphans',
            'orphans_found' => $exitCode === 0,
            'pkill_status'  => $exitCode,
        ]);
    }

    private function elapsedMs(int|float $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }

    private function safeBasename(string $path): string
    {
        $base = basename($path);
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $base) ?? 'document.docx';

        return $safe !== '' ? $safe : 'document.docx';
    }

    private function cleanupDir(string $dir): void
    {
        // @codeCoverageIgnoreStart
        if (!is_dir($dir)) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } elseif (file_exists($item->getPathname())) {
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }
}
