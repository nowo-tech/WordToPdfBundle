<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Runtime;

use FilesystemIterator;
use Nowo\WordToPdfBundle\Config\ResolvedConfig;
use Nowo\WordToPdfBundle\Exception\ConversionFailedException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

use function basename;
use function copy;
use function escapeshellarg;
use function file_exists;
use function function_exists;
use function is_dir;
use function is_file;
use function mkdir;
use function pathinfo;
use function preg_match;
use function preg_replace;
use function sprintf;
use function str_contains;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const PATHINFO_FILENAME;

/**
 * Runs LibreOffice headless conversion: Word → PDF.
 *
 * Compatible with PHP-FPM and FrankenPHP (Symfony Process / proc_open).
 * Always applies {@see ResolvedConfig::$timeout} and force-stops the process tree on
 * timeout/failure so FrankenPHP workers are not left with orphaned `soffice` children.
 */
class LibreOfficeProcessRunner
{
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
        $baseTemp = $config->tempDir ?? sys_get_temp_dir();
        $workDir  = $baseTemp . DIRECTORY_SEPARATOR . 'word_to_pdf_' . uniqid('', true);
        // @codeCoverageIgnoreStart
        if (!@mkdir($workDir, 0700, true) && !is_dir($workDir)) {
            throw new ConversionFailedException(sprintf('Could not create conversion workspace "%s".', $workDir));
        }
        // @codeCoverageIgnoreEnd

        $userProfile = $workDir . DIRECTORY_SEPARATOR . 'lo_profile';
        // @codeCoverageIgnoreStart
        if (!@mkdir($userProfile, 0700, true) && !is_dir($userProfile)) {
            $this->cleanupDir($workDir);
            throw new ConversionFailedException(sprintf('Could not create LibreOffice user profile "%s".', $userProfile));
        }
        // @codeCoverageIgnoreEnd

        $inputName = $this->safeBasename($sourcePath);
        $inputPath = $workDir . DIRECTORY_SEPARATOR . $inputName;
        // @codeCoverageIgnoreStart
        if (!@copy($sourcePath, $inputPath)) {
            $this->cleanupDir($workDir);
            throw new ConversionFailedException(sprintf('Could not copy source file into workspace from "%s".', $sourcePath));
        }
        // @codeCoverageIgnoreEnd

        $outDir = $workDir . DIRECTORY_SEPARATOR . 'out';
        // @codeCoverageIgnoreStart
        if (!@mkdir($outDir, 0700, true) && !is_dir($outDir)) {
            $this->cleanupDir($workDir);
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
            throw new ConversionFailedException(sprintf('LibreOffice conversion timed out after %d seconds.', $config->timeout), 0, $e);
        } catch (ProcessFailedException $e) {
            $this->forceStopProcess($process, $userProfile);
            $this->cleanupDir($workDir);
            throw new ConversionFailedException(sprintf('LibreOffice conversion failed: %s', trim($process->getErrorOutput() !== '' ? $process->getErrorOutput() : $process->getOutput())), 0, $e);
        }

        $expectedPdf = $outDir . DIRECTORY_SEPARATOR . pathinfo($inputName, PATHINFO_FILENAME) . '.pdf';
        if (!is_file($expectedPdf)) {
            $this->forceStopProcess($process, $userProfile);
            $this->cleanupDir($workDir);
            throw new ConversionFailedException(sprintf('LibreOffice did not produce the expected PDF at "%s". Output: %s', $expectedPdf, trim($process->getOutput() . "\n" . $process->getErrorOutput())));
        }

        $finalPath = $baseTemp . DIRECTORY_SEPARATOR . 'word_to_pdf_result_' . uniqid('', true) . '.pdf';
        // @codeCoverageIgnoreStart
        if (!@rename($expectedPdf, $finalPath) && !@copy($expectedPdf, $finalPath)) {
            $this->cleanupDir($workDir);
            throw new ConversionFailedException('Could not move generated PDF to a stable temporary path.');
        }
        // @codeCoverageIgnoreEnd

        $this->cleanupDir($workDir);

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
            // @codeCoverageIgnoreStart
            return;
            // @codeCoverageIgnoreEnd
        }

        if (!function_exists('exec')) {
            // @codeCoverageIgnoreStart
            return;
            // @codeCoverageIgnoreEnd
        }

        $pattern = 'UserInstallation=file://' . $userProfile;
        // pkill may be absent on minimal images; ignore failures.
        @exec('pkill -9 -f ' . escapeshellarg($pattern) . ' 2>/dev/null');
    }

    private function safeBasename(string $path): string
    {
        $base = basename($path);
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $base) ?? 'document.docx';

        // @codeCoverageIgnoreStart
        return $safe !== '' ? $safe : 'document.docx';
        // @codeCoverageIgnoreEnd
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
            } else {
                // @codeCoverageIgnoreStart
                if (file_exists($item->getPathname())) {
                    @unlink($item->getPathname());
                }
                // @codeCoverageIgnoreEnd
            }
        }

        @rmdir($dir);
    }
}
