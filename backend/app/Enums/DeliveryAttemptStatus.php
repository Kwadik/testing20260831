<?php

namespace App\Enums;

enum DeliveryAttemptStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case TIMEOUT = 'timeout';
}
