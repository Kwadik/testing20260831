<?php

namespace App\Enums;

enum OrderStatus: string
{
    case CREATED = 'created';
    case PAID = 'paid';
    case DELIVERING = 'delivering';
    case DELIVERED = 'delivered';
    case PAYMENT_FAILED = 'payment_failed';
    case OUT_OF_STOCK = 'out_of_stock';
    case DELIVERY_FAILED = 'delivery_failed';
}
