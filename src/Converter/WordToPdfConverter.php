<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Converter;

use Nowo\WordToPdfBundle\Config\ProfileResolver;
use Nowo\WordToPdfBundle\Config\ResolvedConfig;
use Nowo\WordToPdfBundle\Exception\ConversionFailedException;
use Nowo\WordToPdfBundle\Exception\UnsupportedFormatException;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;
use Nowo\WordToPdfBundle\Runtime\LibreOfficeProcessRunner;
use Nowo\WordToPdfBundle\Runtime\RuntimeRequirementsChecker;

use function filesize;
use function function_exists;
use function in_array;
use function is_file;
use function is_readable;
use function pathinfo;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strtolower;

use const FILEINFO_MIME_TYPE;
use const PATHINFO_EXTENSION;

/**
 * Default converter: validates the source, asserts runtime readiness, and runs LibreOffice.
 */
final readonly class WordToPdfConverter implements WordToPdfConverterInterface
{
    /** @var list<string> */
    private const SUPPORTED_EXTENSIONS = ['docx', 'doc'];

    /**
     * @param ProfileResolver $profileResolver Profile resolver
     * @param RuntimeRequirementsChecker $requirementsChecker Runtime checker
     * @param LibreOfficeProcessRunner $processRunner Process runner
     */
    public function __construct(
        private ProfileResolver $profileResolver,
        private RuntimeRequirementsChecker $requirementsChecker,
        private LibreOfficeProcessRunner $processRunner,
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * @param string $sourcePath Absolute path to a .docx or .doc file
     *
     * @return ConvertedPdf
     */
    public function convert(string $sourcePath): ConvertedPdf
    {
        return $this->convertWithOptions($sourcePath);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $sourcePath Absolute path to a .docx or .doc file
     * @param string $profile Profile key from configuration
     *
     * @return ConvertedPdf
     */
    public function convertWithProfile(string $sourcePath, string $profile): ConvertedPdf
    {
        return $this->convertWithOptions($sourcePath, [], $profile);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $sourcePath Absolute path to a .docx or .doc file
     * @param array<string, mixed> $options Same shape as a single YAML profile (subset allowed)
     * @param string|null $profile Base profile key, or null for the default
     *
     * @return ConvertedPdf
     */
    public function convertWithOptions(string $sourcePath, array $options = [], ?string $profile = null): ConvertedPdf
    {
        $key    = $profile ?? $this->profileResolver->getDefaultProfileKey();
        $config = $this->profileResolver->resolve($key, $options);

        return $this->doConvert($sourcePath, $config);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $sourcePath Absolute path to a .docx or .doc file
     * @param array<string, mixed> $profileConfig Profile-shaped configuration
     *
     * @return ConvertedPdf
     */
    public function convertWithInlineProfile(string $sourcePath, array $profileConfig): ConvertedPdf
    {
        $config = $this->profileResolver->resolveInline($profileConfig);

        return $this->doConvert($sourcePath, $config);
    }

    /**
     * {@inheritdoc}
     *
     * @param string|null $profile Profile key, or null for the default
     *
     * @return void
     */
    public function assertRuntimeReady(?string $profile = null): void
    {
        $key    = $profile ?? $this->profileResolver->getDefaultProfileKey();
        $config = $this->profileResolver->resolve($key);
        $this->requirementsChecker->assertReady($config);
    }

    private function doConvert(string $sourcePath, ResolvedConfig $config): ConvertedPdf
    {
        $this->assertSource($sourcePath, $config);
        $binary  = $this->requirementsChecker->assertReady($config);
        $pdfPath = $this->processRunner->convert($sourcePath, $binary, $config);

        $filename = $config->export['filename'];
        if (!str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        return new ConvertedPdf($pdfPath, true, $filename);
    }

    private function assertSource(string $sourcePath, ResolvedConfig $config): void
    {
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new ConversionFailedException(sprintf('Source Word file "%s" does not exist or is not readable.', $sourcePath));
        }

        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (!in_array($ext, self::SUPPORTED_EXTENSIONS, true)) {
            throw new UnsupportedFormatException(sprintf('Unsupported Word format ".%s". Supported: .docx, .doc.', $ext));
        }

        $size = filesize($sourcePath);
        // @codeCoverageIgnoreStart
        if ($size === false) {
            throw new ConversionFailedException(sprintf('Could not determine size of "%s".', $sourcePath));
        }
        // @codeCoverageIgnoreEnd
        if ($size > $config->maxSourceBytes) {
            throw new ConversionFailedException(sprintf('Source file "%s" exceeds max_source_bytes (%d > %d).', $sourcePath, $size, $config->maxSourceBytes));
        }

        // Light magic check when fileinfo is available
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $sourcePath) ?: '';
                finfo_close($finfo);
                if ($mime !== '' && $mime !== 'inode/x-empty' && (str_starts_with($mime, 'text/') || str_starts_with($mime, 'image/'))) {
                    throw new UnsupportedFormatException(sprintf('File "%s" does not look like a Word document (mime: %s).', $sourcePath, $mime));
                }
            }
        }
    }
}
