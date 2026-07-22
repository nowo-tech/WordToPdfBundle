<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Converter;

use Nowo\WordToPdfBundle\Exception\InvalidProfileException;
use Nowo\WordToPdfBundle\Naming\PdfNaming;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;

/**
 * Converts Microsoft Word documents (.docx / .doc) to PDF via LibreOffice Writer.
 */
interface WordToPdfConverterInterface
{
    /**
     * Converts using the configured default profile.
     *
     * @param string $sourcePath Absolute path to a .docx or .doc file
     *
     * @return ConvertedPdf
     */
    public function convert(string $sourcePath): ConvertedPdf;

    /**
     * Converts using a named YAML profile.
     *
     * @param string $sourcePath Absolute path to a .docx or .doc file
     * @param string $profile Profile key from configuration
     *
     * @throws InvalidProfileException if the profile does not exist
     *
     * @return ConvertedPdf
     */
    public function convertWithProfile(string $sourcePath, string $profile): ConvertedPdf;

    /**
     * Converts merging a base profile with ad-hoc options (deepest wins).
     *
     * @param string $sourcePath Absolute path to a .docx or .doc file
     * @param array<string, mixed> $options Same shape as a single YAML profile (subset allowed)
     * @param string|null $profile Base profile key, or null for the default
     *
     * @throws InvalidProfileException if the profile does not exist
     *
     * @return ConvertedPdf
     */
    public function convertWithOptions(string $sourcePath, array $options = [], ?string $profile = null): ConvertedPdf;

    /**
     * Converts using a profile-shaped configuration only (no YAML merge).
     *
     * @param string $sourcePath Absolute path to a .docx or .doc file
     * @param array<string, mixed> $profileConfig Profile-shaped configuration
     *
     * @return ConvertedPdf
     */
    public function convertWithInlineProfile(string $sourcePath, array $profileConfig): ConvertedPdf;

    /**
     * Converts several Word files in order (fail-fast).
     *
     * Pass a list of absolute paths, or a map of path => output PDF filename.
     * Explicit map filenames override {@see PdfNaming}. On failure, previously
     * converted PDFs are disposed before the exception is rethrown.
     *
     * @param iterable<array-key, string> $sources List of paths, or path => PDF filename
     * @param PdfNaming|null $naming Naming strategy (default: keep Word basename)
     * @param array<string, mixed> $options Same shape as a single YAML profile (subset allowed)
     * @param string|null $profile Base profile key, or null for the default
     *
     * @throws InvalidProfileException if the profile does not exist
     *
     * @return list<ConvertedPdf>
     */
    public function convertMany(
        iterable $sources,
        ?PdfNaming $naming = null,
        array $options = [],
        ?string $profile = null,
    ): array;

    /**
     * Asserts LibreOffice Writer is installed and ready for the given (or default) profile.
     *
     * @param string|null $profile Profile key, or null for the default
     *
     * @throws \Nowo\WordToPdfBundle\Exception\MissingDependencyException
     * @throws InvalidProfileException
     *
     * @return void
     */
    public function assertRuntimeReady(?string $profile = null): void;
}
