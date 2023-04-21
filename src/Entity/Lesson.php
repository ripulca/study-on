<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\LessonRepository;

#[ORM\Entity(repositoryClass: LessonRepository::class)]
class Lesson
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lessons')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Course $course = null;

    #[ORM\Column(length: 255)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Название не должно превышать 255 символов'
    )]
    #[Assert\NotBlank(
        message: "Название не может быть пустым"
    )]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(
        message: "Контент не может быть пустым"
    )]
    private ?string $content = null;

    #[ORM\Column]
    #[Assert\Range(
        min: 1,
        max: 10000,
        notInRangeMessage: 'Порядковый номер урока должен быть между 1 и 10000',
    )]
    #[Assert\Type(
        type: 'integer',
        message: 'Значение должно иметь тип integer',
    )]
    #[Assert\NotNull(
        message: "Порядковый номер не может быть пустым"
    )]
    private ?int $serialNumber = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): self
    {
        $this->course = $course;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getSerialNumber(): ?int
    {
        return $this->serialNumber;
    }

    public function setSerialNumber(int $serialNumber = null): self
    {
        $this->serialNumber = $serialNumber;

        return $this;
    }
}
