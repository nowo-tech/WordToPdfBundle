<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveDuplicatedReturnSelfDocblockRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests'])
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    )
    // REQ-CS-001: keep @return on public APIs even when native return types exist.
    ->withSkip([
        RemoveUselessReturnTagRector::class,
        RemoveDuplicatedReturnSelfDocblockRector::class,
    ]);
