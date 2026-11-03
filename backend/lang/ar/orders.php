<?php

return [
    'statuses' => [
        'pending' => 'قيد الانتظار',
        'confirmed' => 'مؤكد',
        'shipped' => 'تم الشحن',
        'delivered' => 'تم التسليم',
        'cancelled' => 'ملغى',
    ],
    'insufficient_stock' => 'الكمية المتوفرة من ":product" هي :available فقط.',
    'invalid_transition' => 'لا يمكن تغيير حالة الطلب من ":from" إلى ":to".',
];
