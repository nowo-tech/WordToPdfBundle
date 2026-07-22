<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle;

use Nowo\WordToPdfBundle\DependencyInjection\WordToPdfExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle name {@code WordToPdfBundle} is wired to the extension alias {@code nowo_word_to_pdf}.
 */
final class WordToPdfBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof WordToPdfExtension) {
            $this->extension = new WordToPdfExtension();
        }

        return $this->extension;
    }
}
