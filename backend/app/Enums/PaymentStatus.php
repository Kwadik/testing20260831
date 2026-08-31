<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PAID = 'paid';
    case FAILED = 'failed';
}
