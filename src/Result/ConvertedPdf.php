<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Result;

use RuntimeException;

use function sprintf;

/**
 * Resulting PDF path: use {@see self::readContents()} for bytes or copy the file to a stable location.
 */
final readonly class ConvertedPdf
{
    public function __construct(
        private string $path,
        private bool $isTemporary,
        private string $suggestedFilename = 'document.pdf',
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isTemporary(): bool
    {
        return $this->isTemporary;
    }

    public function suggestedFilename(): string
    {
        return $this->suggestedFilename;
    }

    public function readContents(): string
    {
        $c = @file_get_contents($this->path);
        if ($c === false) {
            throw new RuntimeException(sprintf('Could not read converted PDF at "%s".', $this->path));
        }

        return $c;
    }

    /**
     * Removes the temporary output file when {@see self::isTemporary()} is true.
     */
    public function dispose(): void
    {
        if (!$this->isTemporary) {
            return;
        }

        if (is_file($this->path)) {
            @unlink($this->path);
        }
    }
}
