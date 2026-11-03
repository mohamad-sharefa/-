<?php

namespace App\Modules\Products\Inventory;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

/**
 * The inventory service is down, or didn't answer before the deadline.
 * Shown to the user as a 503 with a clear message instead of an endless wait.
 */
class InventoryUnavailableException extends ServiceUnavailableHttpException
{
    public function __construct(public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct(retryAfter: 10, message: __('errors.inventory_unavailable'), previous: $previous);
    }
}
