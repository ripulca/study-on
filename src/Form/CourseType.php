<?php

namespace App\Form;

use App\Entity\Course;
use App\Enum\PaymentStatus;
use App\Service\BillingClient;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class CourseType extends AbstractType
{
    private BillingClient $billingClient;

    public function __construct(BillingClient $billingClient)
    {
        $this->billingClient = $billingClient;
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $entity=$builder->getData();
        $billingCourse = $this->billingClient->getCourse($entity->getCode());
        if(!isset($billingCourse['price'])){
            $billingCourse['price']=0;
        }
        if(!isset($billingCourse['type'])){
            $billingCourse['type']='free';
        }
        $builder
            ->add('code', TextType::class, [
                'label' => 'Код',
                'required' => true,
                'empty_data' => '',
            ])
            ->add('name', TextType::class, [
                'label' => 'Название',
                'required' => true,
                'empty_data' => '',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Описание',
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Тип',
                'choices'  => [
                    'бесплатный' => 0,
                    'аренда' => 1,
                    'покупка' => 2,
                ],
                'required' => true,
                'mapped'=>false,
                'data'=>PaymentStatus::VALUES[$billingCourse['type']],
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Цена',
                'currency'=>'RUB',
                'html5'=>true,
                'mapped'=>false,
                'empty_data' => 0,
                'attr' => ['min' => 0],
                'data'=>$billingCourse['price'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
            'price' => 0.0,
            'type' => PaymentStatus::FREE
        ]);
    }
}
