<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Runtime;

use function is_executable;
use function is_file;
use function is_string;

use const DIRECTORY_SEPARATOR;
use const PATH_SEPARATOR;

/**
 * Locates the LibreOffice Writer binary (soffice / libreoffice).
 */
class LibreOfficeBinaryLocator
{
    /** @var list<string> */
    public const CANDIDATE_PATHS = [
        '/usr/bin/soffice',
        '/usr/bin/libreoffice',
        '/usr/lib/libreoffice/program/soffice',
        '/usr/lib64/libreoffice/program/soffice',
        '/opt/libreoffice/program/soffice',
        '/Applications/LibreOffice.app/Contents/MacOS/soffice',
    ];

    /** @var list<string> */
    private readonly array $candidatePaths;

    /**
     * @param list<string>|null $candidatePaths
     */
    /**
     * @param list<string>|null $candidatePaths Optional override of default candidate paths
     */
    public function __construct(?array $candidatePaths = null)
    {
        $this->candidatePaths = $candidatePaths ?? self::CANDIDATE_PATHS;
    }

    /**
     * Locate a usable LibreOffice binary path.
     *
     * @param string|null $configuredPath Explicit binary path from config, if any
     *
     * @return non-empty-string|null
     */
    public function locate(?string $configuredPath = null): ?string
    {
        if ($configuredPath !== null && $configuredPath !== '') {
            return $this->isUsableBinary($configuredPath) ? $configuredPath : null;
        }

        foreach ($this->candidatePaths as $candidate) {
            if ($candidate !== '' && $this->isUsableBinary($candidate)) {
                return $candidate;
            }
        }

        return $this->findOnPath('soffice') ?? $this->findOnPath('libreoffice');
    }

    /**
     * Whether the path points to an executable LibreOffice binary.
     *
     * @param string $path Candidate binary path
     *
     * @return bool
     */
    public function isUsableBinary(string $path): bool
    {
        return is_file($path) && is_executable($path);
    }

    /**
     * @return non-empty-string|null
     */
    private function findOnPath(string $name): ?string
    {
        $pathEnv = getenv('PATH');
        if (!is_string($pathEnv) || $pathEnv === '') {
            return null;
        }

        foreach (explode(PATH_SEPARATOR, $pathEnv) as $dir) {
            if ($dir === '') {
                continue;
            }
            $candidate = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
            if ($this->isUsableBinary($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
