<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\File;

use function sprintf;

/**
 * REQ-DEMO-015: a FormKit form (profile `demo`) with CSRF: the Word upload of the converter demo.
 *
 * @extends AbstractType<array<string, mixed>>
 */
#[FormKitConfig('demo')]
final class UploadDocumentsType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $max = $options['max_uploads'];

        $this->addWithDefaults($builder, 'documents', FileType::class, [
            'label'              => 'Word documents (.docx / .doc) — one or more',
            'mapped'             => false,
            'required'           => true,
            'multiple'           => true,
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
            'attr'               => [
                'accept'   => '.docx,.doc,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/msword',
                'multiple' => 'multiple',
            ],
            'constraints' => [
                new Count(
                    min: 1,
                    max: $max,
                    minMessage: 'Please upload at least one Word document.',
                    maxMessage: sprintf('You can upload at most %d Word documents at once.', $max),
                ),
                new All([
                    new File(
                        maxSize: '20M',
                        extensions: ['docx', 'doc'],
                        extensionsMessage: 'Please upload .docx or .doc files (not PDF or other formats).',
                        mimeTypes: [
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/msword',
                            'application/zip',
                            'application/octet-stream',
                        ],
                        mimeTypesMessage: 'Please upload .docx or .doc files (not PDF or other formats).',
                    ),
                ]),
            ],
        ]);
        $this->addWithDefaults($builder, 'convert', SubmitType::class, [
            'label'              => 'Convert to PDF',
            'translation_domain' => false,
            'attr'               => ['class' => 'nowo-ui-btn nowo-ui-btn--primary'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['max_uploads' => 10]);
        $resolver->setAllowedTypes('max_uploads', 'int');
    }
}
