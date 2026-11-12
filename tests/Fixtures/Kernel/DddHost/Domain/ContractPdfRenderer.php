<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Tests\Fixtures\Kernel\DddHost\Domain;

/** Host domain port: plain PHP, no bundle or framework type. */
interface ContractPdfRenderer
{
    /** Returns the contract's DOCX rendered as PDF bytes. */
    public function render(string $contractId, string $docxPath): string;
}
