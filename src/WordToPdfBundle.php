<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle;

use Nowo\WordToPdfBundle\DependencyInjection\WordToPdfExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle name {@code WordToPdfBundle} is wired to the extension alias {@code nowo_word_to_pdf}.
 *
 * Stateless for FrankenPHP worker (FRANKENPHP_RESET_KERNEL unset/false): getContainerExtension()
 * does not mutate instance state.
 */
final class WordToPdfBundle extends Bundle
{
    /**
     * Returns the DI extension (alias nowo_word_to_pdf).
     *
     * @return ExtensionInterface
     */
    public function getContainerExtension(): ExtensionInterface
    {
        return new WordToPdfExtension();
    }
}
