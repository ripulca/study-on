<?php

namespace App\Form;

use App\Entity\Lesson;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class LessonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('course_id', HiddenType::class, [
                'data' => $options['course_id'],
                'mapped' => false,
            ])
            ->add('name', TextType::class, [
                'label' => 'Название',
                'required' => true,
                'empty_data' => '',
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Содержимое урока',
                'required' => true,
                'empty_data' => '',
            ])
            ->add('serialNumber', IntegerType::class, [
                'label' => 'Порядковый номер',
                'required' => true,
                'attr' => [
                    'max' => 10000,
                    'min' => 1,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lesson::class,
            'course_id' => 0,
        ]);
        $resolver->setRequired(['course_id']);
        $resolver->setAllowedTypes('course_id', 'int');
    }
}
