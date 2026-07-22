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
     * @param array<string, array<string, mixed>> $profiles Named profile configurations
     * @param string $defaultProfile Default profile key
     */
    public function __construct(
        private array $profiles,
        private string $defaultProfile,
    ) {
    }

    /**
     * Resolve a named profile merged with optional ad-hoc overrides.
     *
     * @param string $profile Profile key from configuration
     * @param array<string, mixed> $adhoc Per-call overrides (deepest wins)
     *
     * @throws InvalidProfileException if the profile does not exist
     *
     * @return ResolvedConfig
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
     * Resolve the default profile merged with optional ad-hoc overrides.
     *
     * @param array<string, mixed> $adhoc Per-call overrides (deepest wins)
     *
     * @return ResolvedConfig
     */
    public function resolveDefault(array $adhoc = []): ResolvedConfig
    {
        return $this->resolve($this->defaultProfile, $adhoc);
    }

    /**
     * Build a profile from a full config array without YAML merge.
     *
     * @param array<string, mixed> $profileConfig Profile-shaped configuration
     *
     * @return ResolvedConfig
     */
    public function resolveInline(array $profileConfig): ResolvedConfig
    {
        return ResolvedConfig::fromArray($profileConfig);
    }

    /**
     * Default profile key from configuration.
     *
     * @return string
     */
    public function getDefaultProfileKey(): string
    {
        return $this->defaultProfile;
    }
}
