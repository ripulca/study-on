<?php

namespace App\Enum;

class PaymentStatus
{
    public const ALREADY_PAID = 'Уже приобретен';
    public const NO_MONEY = 'Недостаточно средств';
    public const OK = 'Оплачено';
    public const FAIL = 'Не оплачено';

    public const FREE = 0;
    public const RENT = 1;
    public const BUY = 2;

    public const FREE_NAME = 'free';
    public const RENT_NAME = 'rent';
    public const BUY_NAME = 'buy';

    public const NAMES = [
        self::FREE => self::FREE_NAME,
        self::RENT => self::RENT_NAME,
        self::BUY => self::BUY_NAME,
    ];

    public const VALUES = [
        self::FREE_NAME => self::FREE,
        self::RENT_NAME => self::RENT,
        self::BUY_NAME => self::BUY,
    ];
}