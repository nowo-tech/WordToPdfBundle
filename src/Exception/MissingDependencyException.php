<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Exception;

use RuntimeException;

use function sprintf;

final class MissingDependencyException extends RuntimeException implements WordToPdfExceptionInterface
{
    /**
     * Factory when LibreOffice Writer cannot be located.
     *
     * @param string|null $binaryPath Configured binary path, if any
     *
     * @return self
     */
    public static function libreOfficeWriterNotFound(?string $binaryPath = null): self
    {
        $hint = $binaryPath !== null && $binaryPath !== ''
            ? sprintf('Configured binary_path "%s" was not found or is not executable.', $binaryPath)
            : 'LibreOffice Writer (soffice/libreoffice) was not found on this system.';

        return new self(<<<MSG
{$hint}

WordToPdfBundle requires LibreOffice Writer for high-fidelity Word → PDF conversion.

Install the system package (examples):
  Debian/Ubuntu:  sudo apt-get install -y libreoffice-writer
  Alpine:         apk add libreoffice
  Fedora/RHEL:    sudo dnf install -y libreoffice-writer
  Docker:         install libreoffice-writer in your image (see the bundle demo Dockerfile)

Then verify with:  php bin/console nowo:word-to-pdf:check
MSG);
    }

    /**
     * Factory when the configured temp directory is not writable.
     *
     * @param string $tempDir Temporary directory path
     *
     * @return self
     */
    public static function tempDirNotWritable(string $tempDir): self
    {
        return new self(sprintf(
            'Temporary directory "%s" does not exist or is not writable. Configure nowo_word_to_pdf.profiles.*.temp_dir.',
            $tempDir,
        ));
    }

    /**
     * Factory when the LibreOffice version is below min_version.
     *
     * @param string $found Detected version
     * @param string $required Required minimum version
     *
     * @return self
     */
    public static function versionTooOld(string $found, string $required): self
    {
        return new self(sprintf(
            'LibreOffice version "%s" is below the required minimum "%s". Upgrade libreoffice-writer.',
            $found,
            $required,
        ));
    }

    /**
     * Factory when the LibreOffice version cannot be read.
     *
     * @param string $binary Binary path used for --version
     *
     * @return self
     */
    public static function versionUnreadable(string $binary): self
    {
        return new self(sprintf(
            'Could not read LibreOffice version from "%s". Ensure libreoffice-writer is installed correctly.',
            $binary,
        ));
    }
}
