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
     */
    public function convert(string $sourcePath): ConvertedPdf;

    /**
     * Converts using a named YAML profile.
     *
     * @throws InvalidProfileException if the profile does not exist
     */
    public function convertWithProfile(string $sourcePath, string $profile): ConvertedPdf;

    /**
     * Converts merging a base profile with ad-hoc options (deepest wins).
     *
     * @param array<string, mixed> $options same shape as a single YAML profile (subset allowed)
     *
     * @throws InvalidProfileException if the profile does not exist
     */
    public function convertWithOptions(string $sourcePath, array $options = [], ?string $profile = null): ConvertedPdf;

    /**
     * Converts using a profile-shaped configuration only (no YAML merge).
     *
     * @param array<string, mixed> $profileConfig
     */
    public function convertWithInlineProfile(string $sourcePath, array $profileConfig): ConvertedPdf;

    /**
     * Asserts LibreOffice Writer is installed and ready for the given (or default) profile.
     *
     * @throws \Nowo\WordToPdfBundle\Exception\MissingDependencyException
     * @throws InvalidProfileException
     */
    public function assertRuntimeReady(?string $profile = null): void;
}
