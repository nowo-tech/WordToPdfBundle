<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Config;

use function is_array;
use function is_int;
use function is_string;

/**
 * Resolved profile settings for a single conversion.
 *
 * @phpstan-type ExportConfig array{filename: string, storage: string, local_path: ?string, flysystem_adapter: ?string}
 */
final readonly class ResolvedConfig
{
    /**
     * @param ExportConfig $export
     */
    public function __construct(
        public ?string $binaryPath,
        public ?string $tempDir,
        public int $timeout,
        public int $maxSourceBytes,
        public bool $checkOnBoot,
        public string $bootFailure,
        public ?string $minVersion,
        public string $filter,
        public array $export,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var ExportConfig $export */
        $export = array_replace([
            'filename'          => 'document.pdf',
            'storage'           => 'memory',
            'local_path'        => null,
            'flysystem_adapter' => null,
        ], is_array($data['export'] ?? null) ? $data['export'] : []);

        return new self(
            binaryPath: isset($data['binary_path']) && is_string($data['binary_path']) ? $data['binary_path'] : null,
            tempDir: isset($data['temp_dir']) && is_string($data['temp_dir']) ? $data['temp_dir'] : null,
            timeout: isset($data['timeout']) && is_int($data['timeout']) ? $data['timeout'] : 120,
            maxSourceBytes: isset($data['max_source_bytes']) && is_int($data['max_source_bytes']) ? $data['max_source_bytes'] : 52428800,
            checkOnBoot: (bool) ($data['check_on_boot'] ?? false),
            bootFailure: isset($data['boot_failure']) && is_string($data['boot_failure']) ? $data['boot_failure'] : 'exception',
            minVersion: isset($data['min_version']) && is_string($data['min_version']) ? $data['min_version'] : null,
            filter: isset($data['filter']) && is_string($data['filter']) && $data['filter'] !== '' ? $data['filter'] : 'pdf:writer_pdf_Export',
            export: $export,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'binary_path'      => $this->binaryPath,
            'temp_dir'         => $this->tempDir,
            'timeout'          => $this->timeout,
            'max_source_bytes' => $this->maxSourceBytes,
            'check_on_boot'    => $this->checkOnBoot,
            'boot_failure'     => $this->bootFailure,
            'min_version'      => $this->minVersion,
            'filter'           => $this->filter,
            'export'           => $this->export,
        ];
    }
}
