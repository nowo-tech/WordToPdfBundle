<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Http;

use Nowo\WordToPdfBundle\Export\PdfExporter;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

use function preg_replace;
use function trim;

/**
 * Download headers shared by {@see PdfResponseFactory} and the deprecated response methods of
 * {@see PdfExporter}, so both paths send the same headers.
 *
 * @internal
 */
final class PdfDownloadHeaders
{
    public const CONTENT_TYPE = 'application/pdf';

    /**
     * @return array{Content-Type: string, Content-Disposition: string}
     */
    public static function for(string $filename): array
    {
        return [
            'Content-Type'        => self::CONTENT_TYPE,
            'Content-Disposition' => self::contentDisposition($filename),
        ];
    }

    /**
     * RFC 6266 attachment header (quoted ASCII fallback + filename* UTF-8) via {@see HeaderUtils::makeDisposition()},
     * so quotes, CR/LF, path separators or non-ASCII characters in export.filename cannot break or inject headers.
     */
    public static function contentDisposition(string $filename): string
    {
        $filename = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', $filename));
        if (trim($filename, ' _.') === '') {
            $filename = 'document.pdf';
        }
        $fallback = (string) preg_replace('/[^\x20-\x7E]|[%"]/u', '_', $filename);

        return HeaderUtils::makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename, $fallback);
    }
}
