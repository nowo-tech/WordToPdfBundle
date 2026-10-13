<?php

declare(strict_types=1);

// Runs with League\Flysystem made unloadable, to prove the bundle works without league/flysystem.
// Usage: php without-flysystem.php <vendor/autoload.php> <pdf path> (non-zero exit on any failure)

use Nowo\WordToPdfBundle\DependencyInjection\WordToPdfExtension;
use Nowo\WordToPdfBundle\Exception\ExportException;
use Nowo\WordToPdfBundle\Export\PdfExporter;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require $argv[1];

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'League\\Flysystem\\')) {
        throw new LogicException('Flysystem class autoloaded: ' . $class);
    }
}, true, true);

$container = new ContainerBuilder();
$container->setParameter('kernel.project_dir', sys_get_temp_dir());
$container->setParameter('kernel.cache_dir', sys_get_temp_dir());
(new WordToPdfExtension())->load([['profiles' => ['default' => []]]], $container);

$exporter = new PdfExporter();
$copy     = $argv[2] . '.copy.pdf';
$exporter->toFile(new ConvertedPdf($argv[2], false), $copy);
if (!is_file($copy)) {
    throw new RuntimeException('toFile() did not write the copy');
}
unlink($copy);
$exporter->toBinaryResponse(new ConvertedPdf($argv[2], false));

try {
    $exporter->toFlysystem(new ConvertedPdf($argv[2], false), 'remote.pdf');
} catch (ExportException $e) {
    echo $e->getMessage();

    return;
}

throw new RuntimeException('toFlysystem() did not throw');
