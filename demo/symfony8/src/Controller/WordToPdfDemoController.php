<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\WordToPdfBundle\Config\ProfileResolver;
use Nowo\WordToPdfBundle\Converter\WordToPdfConverterInterface;
use Nowo\WordToPdfBundle\Exception\MissingDependencyException;
use Nowo\WordToPdfBundle\Exception\WordToPdfExceptionInterface;
use Nowo\WordToPdfBundle\Export\ExporterInterface;
use Nowo\WordToPdfBundle\Runtime\RuntimeRequirementsChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\File;

final class WordToPdfDemoController extends AbstractController
{
    public function __construct(
        private readonly WordToPdfConverterInterface $converter,
        private readonly ExporterInterface $exporter,
        private readonly RuntimeRequirementsChecker $checker,
        private readonly ProfileResolver $profiles,
    ) {
    }

    #[Route('/', name: 'demo_home', methods: ['GET', 'POST'])]
    public function home(Request $request): Response
    {
        $runtime = $this->checker->diagnose($this->profiles->resolveDefault());

        $form = $this->createFormBuilder()
            ->add('document', FileType::class, [
                'label'       => 'Word document (.docx / .doc)',
                'mapped'      => false,
                'required'    => true,
                'constraints' => [
                    new File([
                        'maxSize'   => '20M',
                        'mimeTypes' => [
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/msword',
                            'application/zip',
                            'application/octet-stream',
                        ],
                        'mimeTypesMessage' => 'Please upload a .docx or .doc file.',
                    ]),
                ],
            ])
            ->add('convert', SubmitType::class, ['label' => 'Convert to PDF'])
            ->getForm();

        $form->handleRequest($request);
        $error = null;

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $upload */
            $upload = $form->get('document')->getData();
            if (!$upload instanceof UploadedFile) {
                $error = 'No file uploaded.';
            } else {
                try {
                    $pdf = $this->converter->convert($upload->getPathname());

                    return $this->exporter->toBinaryResponse($pdf);
                } catch (WordToPdfExceptionInterface $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->render('demo/index.html.twig', [
            'form'    => $form,
            'runtime' => $runtime,
            'error'   => $error,
        ]);
    }

    #[Route('/sample.pdf', name: 'demo_sample', methods: ['GET'])]
    public function sample(): Response
    {
        $sample = $this->getParameter('kernel.project_dir') . '/public/demo/sample.docx';
        try {
            $pdf = $this->converter->convert($sample);

            return $this->exporter->toBinaryResponse($pdf);
        } catch (MissingDependencyException $e) {
            return new Response($e->getMessage(), 503, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
    }
}
