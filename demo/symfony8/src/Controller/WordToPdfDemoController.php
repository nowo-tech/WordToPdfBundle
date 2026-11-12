<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\UploadDocumentsType;
use Nowo\WordToPdfBundle\Config\ProfileResolver;
use Nowo\WordToPdfBundle\Converter\WordToPdfConverterInterface;
use Nowo\WordToPdfBundle\Exception\ConversionFailedException;
use Nowo\WordToPdfBundle\Exception\MissingDependencyException;
use Nowo\WordToPdfBundle\Exception\WordToPdfExceptionInterface;
use Nowo\WordToPdfBundle\Http\PdfResponseFactory;
use Nowo\WordToPdfBundle\Naming\PdfNaming;
use Nowo\WordToPdfBundle\Result\ConvertedPdf;
use Nowo\WordToPdfBundle\Runtime\RuntimeRequirementsChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;
use ZipArchive;

use function count;
use function dirname;
use function in_array;
use function is_array;
use function sprintf;

use const PATHINFO_FILENAME;

final class WordToPdfDemoController extends AbstractController
{
    private const SAMPLE_SIMPLE = 'sample.docx';

    private const SAMPLE_STRESS = 'stress-styles.docx';

    private const MAX_UPLOADS = 10;

    public function __construct(
        private readonly WordToPdfConverterInterface $converter,
        private readonly PdfResponseFactory $responses,
        private readonly RuntimeRequirementsChecker $checker,
        private readonly ProfileResolver $profiles,
    ) {
    }

    #[Route('/', name: 'demo_home', methods: ['GET', 'POST'])]
    public function home(Request $request): Response
    {
        $runtime = $this->checker->diagnose($this->profiles->resolveDefault());

        $form = $this->createForm(UploadDocumentsType::class, null, ['max_uploads' => self::MAX_UPLOADS]);
        $form->handleRequest($request);
        $error = null;

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->flashIfBadUploadExtensions($form->get('documents')->getData());
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var list<UploadedFile>|UploadedFile|null $raw */
            $raw     = $form->get('documents')->getData();
            $uploads = $this->normalizeUploads($raw);
            if ($uploads === []) {
                $error = 'No file uploaded.';
            } elseif (!$this->flashIfBadUploadExtensions($uploads)) {
                try {
                    return $this->convertUploads($uploads);
                } catch (WordToPdfExceptionInterface $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->render('demo/index.html.twig', [
            'form'       => $form,
            'runtime'    => $runtime,
            'error'      => $error,
            'maxUploads' => self::MAX_UPLOADS,
        ]);
    }

    /**
     * @param list<UploadedFile>|UploadedFile|null $upload
     *
     * @return list<UploadedFile>
     */
    private function normalizeUploads(mixed $upload): array
    {
        if ($upload instanceof UploadedFile) {
            return [$upload];
        }

        if (!is_array($upload)) {
            return [];
        }

        $files = [];
        foreach ($upload as $item) {
            if ($item instanceof UploadedFile) {
                $files[] = $item;
            }
        }

        return $files;
    }

    /**
     * Flash error when any upload extension is not .docx/.doc.
     *
     * @param list<UploadedFile>|UploadedFile|null $upload
     *
     * @return bool True when a flash was emitted
     */
    private function flashIfBadUploadExtensions(mixed $upload): bool
    {
        $flashed = false;
        foreach ($this->normalizeUploads($upload) as $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            if (in_array($ext, ['docx', 'doc'], true)) {
                continue;
            }

            $this->addFlash(
                'error',
                sprintf(
                    'Invalid file extension%s on "%s". Please upload .docx or .doc files (not PDF or other formats).',
                    $ext !== '' ? sprintf(' ".%s"', $ext) : '',
                    $file->getClientOriginalName(),
                ),
            );
            $flashed = true;
        }

        return $flashed;
    }

    /**
     * Convert one or more uploads: single PDF, or a ZIP of PDFs for multipart batches.
     *
     * @param list<UploadedFile> $uploads
     */
    private function convertUploads(array $uploads): Response
    {
        $naming    = PdfNaming::suffix(' [converted]');
        $sources   = [];
        $tempPaths = [];

        try {
            foreach ($uploads as $upload) {
                $ext         = strtolower($upload->getClientOriginalExtension());
                $tempPath    = sys_get_temp_dir() . '/' . uniqid('wtp_upload_', true) . '.' . $ext;
                $tempPaths[] = $tempPath;
                $upload->move(dirname($tempPath), basename($tempPath));
                // Explicit map keeps the client Word basename (temp paths are extension-only).
                $sources[$tempPath] = $naming->resolve($upload->getClientOriginalName());
            }

            $converted = $this->converter->convertMany($sources);

            if (count($converted) === 1) {
                return $this->responses->binaryFile($converted[0]);
            }

            return $this->zipConvertedPdfs($converted);
        } finally {
            foreach ($tempPaths as $tempPath) {
                if (is_file($tempPath)) {
                    @unlink($tempPath);
                }
            }
        }
    }

    /**
     * @param list<ConvertedPdf> $pdfs
     */
    private function zipConvertedPdfs(array $pdfs): BinaryFileResponse
    {
        $zipPath = sys_get_temp_dir() . '/' . uniqid('wtp_batch_', true) . '.zip';
        $zip     = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            foreach ($pdfs as $pdf) {
                $pdf->dispose();
            }
            throw new ConversionFailedException('Could not create ZIP archive for multipart conversion.');
        }

        $usedNames = [];
        try {
            foreach ($pdfs as $pdf) {
                $name = $pdf->suggestedFilename();
                if (isset($usedNames[$name])) {
                    ++$usedNames[$name];
                    $base = pathinfo($name, PATHINFO_FILENAME);
                    $name = sprintf('%s (%d).pdf', $base, $usedNames[$name]);
                } else {
                    $usedNames[$name] = 0;
                }
                $zip->addFile($pdf->path(), $name);
            }
            $zip->close();
        } catch (Throwable $e) {
            $zip->close();
            @unlink($zipPath);
            foreach ($pdfs as $pdf) {
                $pdf->dispose();
            }
            throw $e;
        }

        foreach ($pdfs as $pdf) {
            $pdf->dispose();
        }

        $response = new BinaryFileResponse($zipPath);
        $response->headers->set('Content-Type', 'application/zip');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'word-to-pdf [converted].zip',
        );
        $response->deleteFileAfterSend(true);

        return $response;
    }

    #[Route('/sample.pdf', name: 'demo_sample', methods: ['GET'])]
    public function sample(): Response
    {
        return $this->convertBundledSample(self::SAMPLE_SIMPLE);
    }

    #[Route('/stress.pdf', name: 'demo_stress', methods: ['GET'])]
    public function stress(): Response
    {
        return $this->convertBundledSample(self::SAMPLE_STRESS);
    }

    #[Route('/demo/{name}', name: 'demo_download_source', methods: ['GET'], requirements: ['name' => 'sample\.docx|stress-styles\.docx'])]
    public function downloadSource(string $name): Response
    {
        $path = $this->demoFilePath($name);
        if (!is_file($path)) {
            return new Response('Sample not found.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return $this->file($path, $name);
    }

    private function convertBundledSample(string $filename): Response
    {
        $sample = $this->demoFilePath($filename);
        try {
            $pdfs = $this->converter->convertMany([$sample], PdfNaming::suffix(' [converted]'));

            return $this->responses->binaryFile($pdfs[0]);
        } catch (MissingDependencyException $e) {
            return new Response($e->getMessage(), 503, ['Content-Type' => 'text/plain; charset=UTF-8']);
        } catch (WordToPdfExceptionInterface $e) {
            return new Response($e->getMessage(), 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
    }

    private function demoFilePath(string $filename): string
    {
        return $this->getParameter('kernel.project_dir') . '/public/demo/' . $filename;
    }
}
