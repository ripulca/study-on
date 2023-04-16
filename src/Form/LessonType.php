<?php

namespace App\Form;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class LessonType extends AbstractType
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label'=>'Название',
                'required' => true,
                'constraints' => [
                    new Length(max: 255, maxMessage: 'Название урока должно быть не более {{max}} символов'),
                ],
            ])
            ->add('content', TextareaType::class, [
                'label'=>'Содержимое урока',
                'required' => true,
            ])
            ->add('serialNumber', IntegerType::class, [
                'label' => 'Порядковый номер',
                'required' => true,
                'attr'=>[
                    'max'=>10000,
                    'min'=>1,
                ]
            ])
            ->add('course', HiddenType::class, )
        ;
        $builder->get('course')
            ->addModelTransformer(new CallbackTransformer(
                function ($courseAsObj): string {
                    return $courseAsObj->getId();
                },
                function ($courseId): Course {
                    return $this->entityManager
                    ->getRepository(Course::class)
                    ->find($courseId);
                }
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lesson::class,
            'course' => null,
        ]);
        $resolver->setRequired(['course']);
    }
}
