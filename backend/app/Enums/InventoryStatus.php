<?php

namespace App\Enums;

enum InventoryStatus: string
{
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case DELIVERED = 'delivered';
}
