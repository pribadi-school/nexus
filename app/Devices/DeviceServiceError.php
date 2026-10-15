<?php

declare(strict_types=1);

namespace App\Devices;

use RuntimeException;

/** A write was refused for a reason the form should show next to $field. */
final class DeviceServiceError extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly string $field)
    {
        parent::__construct($reason);
    }
}
