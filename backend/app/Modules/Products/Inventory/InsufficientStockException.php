<?php

namespace App\Modules\Products\Inventory;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    /**
     * @param  array<int, int>  $available  product id => quantity still in stock, for each product that is short
     */
    public function __construct(public readonly array $available)
    {
        parent::__construct('Not enough stock for products: '.implode(', ', array_keys($available)));
    }
}
