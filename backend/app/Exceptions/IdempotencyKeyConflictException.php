<?php

namespace App\Exceptions;

use RuntimeException;

class IdempotencyKeyConflictException extends RuntimeException
{
}
