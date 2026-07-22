<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Converter;

use Nowo\WordToPdfBundle\Exception\InvalidProfileException;
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
