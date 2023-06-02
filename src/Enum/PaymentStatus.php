<?php

namespace App\Enum;

class PaymentStatus
{
    public const ALREADY_PAID = 'Уже приобретен';
    public const NO_MONEY = 'Недостаточно средств';
    public const OK = 'Оплачено';
    public const FAIL = 'Не оплачено';
}