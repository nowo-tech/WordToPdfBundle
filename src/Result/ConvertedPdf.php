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
    /**
     * @param string $path Absolute filesystem path to the PDF
     * @param bool $isTemporary Whether the file should be deleted on dispose
     * @param string $suggestedFilename Suggested download filename
     */
    public function __construct(
        private string $path,
        private bool $isTemporary,
        private string $suggestedFilename = 'document.pdf',
    ) {
    }

    /**
     * Absolute filesystem path to the PDF.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Whether the PDF file should be deleted on dispose/send.
     *
     * @return bool
     */
    public function isTemporary(): bool
    {
        return $this->isTemporary;
    }

    /**
     * Suggested download filename.
     *
     * @return string
     */
    public function suggestedFilename(): string
    {
        return $this->suggestedFilename;
    }

    /**
     * Read PDF bytes from disk.
     *
     * @return string
     */
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
     *
     * @return void
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
