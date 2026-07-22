<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Config;

use Nowo\WordToPdfBundle\Exception\InvalidProfileException;

use function array_replace_recursive;
use function sprintf;

/**
 * Resolves profiles: default YAML profile + named profile + ad-hoc array (deepest wins).
 */
final readonly class ProfileResolver
{
    /**
     * @param array<string, array<string, mixed>> $profiles
     */
    public function __construct(
        private array $profiles,
        private string $defaultProfile,
    ) {
    }

    /**
     * @param array<string, mixed> $adhoc
     */
    public function resolve(string $profile, array $adhoc = []): ResolvedConfig
    {
        if (!isset($this->profiles[$profile])) {
            throw new InvalidProfileException(sprintf('Profile "%s" is not defined in nowo_word_to_pdf configuration.', $profile));
        }

        $base   = $this->profiles[$this->defaultProfile] ?? [];
        $named  = $this->profiles[$profile];
        $merged = array_replace_recursive($base, $named, $adhoc);

        return ResolvedConfig::fromArray($merged);
    }

    /**
     * @param array<string, mixed> $adhoc
     */
    public function resolveDefault(array $adhoc = []): ResolvedConfig
    {
        return $this->resolve($this->defaultProfile, $adhoc);
    }

    /**
     * @param array<string, mixed> $profileConfig
     */
    public function resolveInline(array $profileConfig): ResolvedConfig
    {
        return ResolvedConfig::fromArray($profileConfig);
    }

    public function getDefaultProfileKey(): string
    {
        return $this->defaultProfile;
    }
}
