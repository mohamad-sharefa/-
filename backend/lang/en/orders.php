<?php

return [
    'statuses' => [
        'pending' => 'pending',
        'confirmed' => 'confirmed',
        'shipped' => 'shipped',
        'delivered' => 'delivered',
        'cancelled' => 'cancelled',
    ],
    'insufficient_stock' => 'Only :available left of ":product".',
    'invalid_transition' => 'An order that is :from cannot become :to.',
];
