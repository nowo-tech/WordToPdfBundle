<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Naming;

use InvalidArgumentException;
use Nowo\WordToPdfBundle\Converter\WordToPdfConverterInterface;

use function basename;
use function in_array;
use function is_callable;
use function pathinfo;
use function sprintf;
use function str_ends_with;
use function strtolower;

use const PATHINFO_FILENAME;

/**
 * Builds suggested PDF download filenames from Word source paths.
 *
 * Use with {@see WordToPdfConverterInterface::convertMany()}.
 * Explicit path => filename maps passed to convertMany override this strategy.
 */
final readonly class PdfNaming
{
    private const MODE_KEEP = 'keep';

    private const MODE_PREFIX = 'prefix';

    private const MODE_SUFFIX = 'suffix';

    private const MODE_SURROUND = 'surround';

    private const MODE_FIXED = 'fixed';

    private const MODE_CALLBACK = 'callback';

    /**
     * @param callable(string, int): string|null $callback
     */
    private function __construct(
        private string $mode,
        private string $prefix = '',
        private string $suffix = '',
        private string $fixed = '',
        private mixed $callback = null,
    ) {
    }

    /**
     * Keep the Word basename: contract.docx → contract.pdf.
     *
     * @return self
     */
    public static function keep(): self
    {
        return new self(self::MODE_KEEP);
    }

    /**
     * Prepend a prefix to the Word basename: OUT- + contract → OUT-contract.pdf.
     *
     * @param string $prefix Filename prefix (not including path separators)
     *
     * @return self
     */
    public static function prefix(string $prefix): self
    {
        return new self(self::MODE_PREFIX, prefix: $prefix);
    }

    /**
     * Append a suffix before .pdf: contract + " [converted]" → contract [converted].pdf.
     *
     * @param string $suffix Filename suffix before the .pdf extension
     *
     * @return self
     */
    public static function suffix(string $suffix): self
    {
        return new self(self::MODE_SUFFIX, suffix: $suffix);
    }

    /**
     * Prefix and suffix around the Word basename.
     *
     * @param string $prefix Filename prefix
     * @param string $suffix Filename suffix before .pdf
     *
     * @return self
     */
    public static function surround(string $prefix, string $suffix): self
    {
        return new self(self::MODE_SURROUND, prefix: $prefix, suffix: $suffix);
    }

    /**
     * Use the same fixed PDF name for every item (useful for a single file).
     *
     * @param string $filename Output filename (`.pdf` appended when missing)
     *
     * @return self
     */
    public static function fixed(string $filename): self
    {
        return new self(self::MODE_FIXED, fixed: $filename);
    }

    /**
     * Custom naming: callback receives absolute source path and zero-based index.
     *
     * @param callable(string, int): string $callback
     *
     * @return self
     */
    public static function callback(callable $callback): self
    {
        return new self(self::MODE_CALLBACK, callback: $callback);
    }

    /**
     * Resolve the suggested PDF filename for a source Word path.
     *
     * @param string $sourcePath Absolute path to the Word file
     * @param int $index Zero-based index in the batch
     *
     * @return string
     */
    public function resolve(string $sourcePath, int $index = 0): string
    {
        return match ($this->mode) {
            self::MODE_KEEP     => $this->ensurePdf($this->wordBasename($sourcePath)),
            self::MODE_PREFIX   => $this->ensurePdf($this->prefix . $this->wordBasename($sourcePath)),
            self::MODE_SUFFIX   => $this->ensurePdf($this->wordBasename($sourcePath) . $this->suffix),
            self::MODE_SURROUND => $this->ensurePdf($this->prefix . $this->wordBasename($sourcePath) . $this->suffix),
            self::MODE_FIXED    => $this->ensurePdf($this->fixed !== '' ? $this->fixed : 'document'),
            self::MODE_CALLBACK => $this->resolveCallback($sourcePath, $index),
            default             => throw new InvalidArgumentException(sprintf('Unknown PdfNaming mode "%s".', $this->mode)),
        };
    }

    /**
     * Normalize an explicit or strategy filename to end with .pdf.
     *
     * @param string $filename Raw filename
     *
     * @return string
     */
    public static function ensurePdfExtension(string $filename): string
    {
        $name = basename($filename);
        if (in_array($name, ['', '.', '..'], true)) {
            $name = 'document';
        }

        if (!str_ends_with(strtolower($name), '.pdf')) {
            $name .= '.pdf';
        }

        return $name;
    }

    private function ensurePdf(string $filename): string
    {
        return self::ensurePdfExtension($filename);
    }

    private function wordBasename(string $sourcePath): string
    {
        $base = pathinfo(basename($sourcePath), PATHINFO_FILENAME);
        if (in_array($base, ['', '.', '..'], true)) {
            return 'document';
        }

        return $base;
    }

    private function resolveCallback(string $sourcePath, int $index): string
    {
        // @codeCoverageIgnoreStart
        if (!is_callable($this->callback)) {
            throw new InvalidArgumentException('PdfNaming callback mode requires a callable.');
        }
        // @codeCoverageIgnoreEnd

        /** @var string $name */
        $name = ($this->callback)($sourcePath, $index);

        return $this->ensurePdf($name);
    }
}
