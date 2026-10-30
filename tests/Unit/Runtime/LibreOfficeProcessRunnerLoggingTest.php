<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Unit\Runtime;

use Nowo\WordToPdfBundle\Config\ResolvedConfig;
use Nowo\WordToPdfBundle\Exception\ConversionFailedException;
use Nowo\WordToPdfBundle\Runtime\LibreOfficeProcessRunner;
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_filter;
use function array_values;
use function basename;
use function chmod;
use function dirname;
use function file_put_contents;
use function is_scalar;
use function mkdir;
use function rmdir;
use function str_contains;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * REQ-OBS-001: the runner logs start/success/timeout/failure/orphan reaping, never paths or contents.
 */
final class LibreOfficeProcessRunnerLoggingTest extends TestCase
{
    private const SECRET_CONTENT = 'CONFIDENTIAL-DOCUMENT-BODY';

    private RecordingLogger $logger;

    private string $source;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
        $this->source = sys_get_temp_dir() . '/wtp_log_customer_name_' . uniqid('', true) . '.docx';
        file_put_contents($this->source, "PK\x03\x04" . self::SECRET_CONTENT);
    }

    protected function tearDown(): void
    {
        @unlink($this->source);
    }

    public function testSuccessLogsStartAndSuccessWithoutPaths(): void
    {
        $fake = $this->fixture('fake-soffice.sh');

        $pdf = $this->runner()->convert($this->source, $fake, $this->config(10));
        @unlink($pdf);

        self::assertSame(['Word to PDF conversion started.', 'Word to PDF conversion succeeded.'], array_column($this->logger->records, 'message'));
        self::assertSame('info', $this->logger->records[0]['level']);
        self::assertSame('docx', $this->logger->records[0]['context']['source_extension']);
        self::assertGreaterThan(0, $this->logger->records[0]['context']['source_bytes']);
        self::assertSame('info', $this->logger->records[1]['level']);
        self::assertGreaterThan(0, $this->logger->records[1]['context']['pdf_bytes']);
        self::assertArrayHasKey('duration_ms', $this->logger->records[1]['context']);
        $this->assertNoSensitiveData($pdf);
    }

    public function testProcessFailureLogsErrorWithExitCodeAndReapsOrphans(): void
    {
        try {
            $this->runner()->convert($this->source, '/bin/false', $this->config(5));
            self::fail('Expected ConversionFailedException');
        } catch (ConversionFailedException) {
        }

        $error = $this->single('error');
        self::assertSame('process_failed', $error['context']['reason']);
        self::assertSame(1, $error['context']['exit_code']);

        $reap = $this->byAction('reap_orphans');
        self::assertCount(1, $reap);
        self::assertFalse($reap[0]['context']['orphans_found']);
        $this->assertNoSensitiveData();
    }

    public function testMissingPdfLogsError(): void
    {
        try {
            $this->runner()->convert($this->source, $this->fixture('fake-soffice-nopdf.sh'), $this->config(5));
            self::fail('Expected ConversionFailedException');
        } catch (ConversionFailedException) {
        }

        $error = $this->single('error');
        self::assertSame('no_output', $error['context']['reason']);
        self::assertSame(0, $error['context']['exit_code']);
        $this->assertNoSensitiveData();
    }

    public function testTimeoutLogsWarning(): void
    {
        try {
            $this->runner()->convert($this->source, $this->fixture('slow-soffice.sh'), $this->config(1));
            self::fail('Expected ConversionFailedException');
        } catch (ConversionFailedException) {
        }

        $warning = $this->single('warning');
        self::assertSame('timeout', $warning['context']['reason']);
        self::assertSame(1, $warning['context']['timeout_seconds']);
        self::assertCount(1, $this->byAction('reap_orphans'));
        $this->assertNoSensitiveData();
    }

    public function testOrphanReapIsSkippedForUnsafeWorkspacePath(): void
    {
        // A temp dir with a space fails the shell-pattern allow-list: no pkill, so no reap record.
        $tempDir = sys_get_temp_dir() . '/wtp log ' . uniqid('', true);
        mkdir($tempDir, 0700, true);

        try {
            $this->runner()->convert($this->source, '/bin/false', ResolvedConfig::fromArray([
                'temp_dir' => $tempDir,
                'timeout'  => 5,
            ]));
            self::fail('Expected ConversionFailedException');
        } catch (ConversionFailedException) {
        } finally {
            @rmdir($tempDir);
        }

        self::assertSame('process_failed', $this->single('error')['context']['reason']);
        self::assertSame([], $this->byAction('reap_orphans'));
    }

    private function runner(): LibreOfficeProcessRunner
    {
        return new LibreOfficeProcessRunner($this->logger);
    }

    private function config(int $timeout): ResolvedConfig
    {
        return ResolvedConfig::fromArray([
            'temp_dir' => sys_get_temp_dir(),
            'timeout'  => $timeout,
            'filter'   => 'pdf:writer_pdf_Export',
        ]);
    }

    private function fixture(string $name): string
    {
        $path = dirname(__DIR__, 2) . '/Fixtures/' . $name;
        chmod($path, 0755);

        return $path;
    }

    /**
     * @return array{level: mixed, message: string, context: array<array-key, mixed>}
     */
    private function single(string $level): array
    {
        $matches = array_values(array_filter($this->logger->records, static fn (array $r): bool => $r['level'] === $level));
        self::assertCount(1, $matches, 'Expected exactly one ' . $level . ' record');

        return $matches[0];
    }

    /**
     * @return list<array{level: mixed, message: string, context: array<array-key, mixed>}>
     */
    private function byAction(string $action): array
    {
        return array_values(array_filter($this->logger->records, static fn (array $r): bool => ($r['context']['action'] ?? null) === $action));
    }

    private function assertNoSensitiveData(?string $pdfPath = null): void
    {
        self::assertNotSame([], $this->logger->records);
        $needles = [sys_get_temp_dir() . '/', basename($this->source), 'customer_name', self::SECRET_CONTENT, 'word_to_pdf_result_'];
        if ($pdfPath !== null) {
            $needles[] = basename($pdfPath);
        }

        foreach ($this->logger->records as $record) {
            self::assertSame('word_to_pdf', $record['context']['bundle']);
            foreach ([$record['message'], ...array_values($record['context'])] as $value) {
                if (!is_scalar($value)) {
                    continue;
                }
                foreach ($needles as $needle) {
                    self::assertFalse(str_contains((string) $value, $needle), 'Log leaked "' . $needle . '"');
                }
            }
        }
    }
}
