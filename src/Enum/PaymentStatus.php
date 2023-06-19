<?php

namespace App\Enum;

class PaymentStatus
{
    public const ALREADY_PAID = 0;
    public const NO_MONEY = 2;
    public const OK = 1;
    public const FAIL = 3;
    public const ALREADY_PAID_NAME = 'Уже приобретен';
    public const NO_MONEY_NAME = 'Недостаточно средств';
    public const OK_NAME = 'Оплачено';
    public const FAIL_NAME = 'Не оплачено';
    public const PAY_NAMES = [
        self::ALREADY_PAID => self::ALREADY_PAID_NAME,
        self::NO_MONEY => self::NO_MONEY_NAME,
        self::OK => self::OK_NAME,
        self::FAIL => self::FAIL_NAME,
    ];

    public const PAY_VALUES = [
        self::FAIL_NAME => self::FAIL,
        self::OK_NAME => self::OK,
        self::NO_MONEY_NAME => self::NO_MONEY,
        self::ALREADY_PAID_NAME => self::ALREADY_PAID,
    ];

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

    public const PER_WEEK=' в неделю';
    public const ROUBLES='₽';
    public const FREE_RUS='Бесплатный';
    public const RENT_TILL='Арендовано до';
    public const BOUGHT='Куплено';
}