<?php

namespace App\DTO;

class CourseDTO
{
    private string $name;
    private string $code;
    private int $type;
    private float $price = 0.0;

    public static function getCourseDTO(string $name, string $code, int $type, float $price)
    {
        return (new self)
            ->setCode($code)
            ->setName($name)
            ->setPrice($price)
            ->setType($type);
    }

    public function setName(string $name){
        $this->name = $name;
        return $this;
    }

    public function setCode(string $code){
        $this->code = $code;
        return $this;
    }

    public function setType(int $type){
        $this->type = $type;
        return $this;
    }

    public function setPrice(float $price){
        $this->price = $price;
        return $this;
    }
}