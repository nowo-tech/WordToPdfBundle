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

use function basename;
use function copy;
use function file_exists;
use function is_dir;
use function is_file;
use function mkdir;
use function pathinfo;
use function preg_replace;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const PATHINFO_FILENAME;

/**
 * Runs LibreOffice headless conversion: Word → PDF.
 *
 * Compatible with PHP-FPM and FrankenPHP (Symfony Process / proc_open).
 */
class LibreOfficeProcessRunner
{
    /**
     * @throws ConversionFailedException
     *
     * @return non-empty-string absolute path to the generated PDF
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
        $process->setTimeout($config->timeout);

        try {
            $process->mustRun();
        } catch (ProcessTimedOutException $e) {
            $this->cleanupDir($workDir);
            throw new ConversionFailedException(sprintf('LibreOffice conversion timed out after %d seconds.', $config->timeout), 0, $e);
        } catch (ProcessFailedException $e) {
            $this->cleanupDir($workDir);
            throw new ConversionFailedException(sprintf('LibreOffice conversion failed: %s', trim($process->getErrorOutput() !== '' ? $process->getErrorOutput() : $process->getOutput())), 0, $e);
        }

        $expectedPdf = $outDir . DIRECTORY_SEPARATOR . pathinfo($inputName, PATHINFO_FILENAME) . '.pdf';
        if (!is_file($expectedPdf)) {
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
