<?php

namespace App\DTO;

class CourseDTO
{
    private string $name;
    private string $code;
    private int $type;
    private float $price = 0.0;

    public function __construct(string $name, string $code, int $type, float $price){
        $this->name=$name;
        $this->code=$code;
        $this->type=$type;
        $this->price=$price;
    }
}