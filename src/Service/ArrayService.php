<?php

namespace App\Service;

class ArrayService
{
    public static function mapToKey($array, $key):array
    {
        $result=[];
        foreach($array as $el){
            $result[$el[$key]]=$el;
        }
        return $result;
    }
}