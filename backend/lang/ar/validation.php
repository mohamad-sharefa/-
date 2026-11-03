<?php

// Arabic messages for the validation rules the API uses. Missing keys fall back to English.
return [
    'array' => 'يجب أن يكون :attribute قائمة.',
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'distinct' => ':attribute مكرر.',
    'enum' => 'القيمة المختارة لـ :attribute غير صالحة.',
    'decimal' => 'يجب ألا يحتوي :attribute على أكثر من :decimal منازل عشرية.',
    'email' => 'يجب أن يكون :attribute عنوان بريد إلكتروني صحيحاً.',
    'exists' => ':attribute المختار غير موجود.',
    'image' => 'يجب أن يكون :attribute صورة.',
    'integer' => 'يجب أن يكون :attribute عدداً صحيحاً.',
    'max' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عناصر.',
        'file' => 'يجب ألا يزيد حجم :attribute عن :max كيلوبايت.',
        'numeric' => 'يجب ألا يزيد :attribute عن :max.',
        'string' => 'يجب ألا يزيد :attribute عن :max حرفاً.',
    ],
    'mimes' => 'يجب أن يكون :attribute من نوع: :values.',
    'min' => [
        'array' => 'يجب أن يحتوي :attribute على :min عنصر على الأقل.',
        'numeric' => 'يجب ألا يقل :attribute عن :min.',
        'string' => 'يجب أن يتكون :attribute من :min أحرف على الأقل.',
    ],
    'numeric' => 'يجب أن يكون :attribute رقماً.',
    'password' => [
        'letters' => 'يجب أن تحتوي :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن تحتوي :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن تحتوي :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن تحتوي :attribute على رمز واحد على الأقل.',
        'uncompromised' => ':attribute ظهرت في تسريب بيانات سابق. اختر :attribute مختلفة.',
    ],
    'required' => 'حقل :attribute مطلوب.',
    'string' => 'يجب أن يكون :attribute نصاً.',
    'unique' => ':attribute مستخدم من قبل.',

    'attributes' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'category_id' => 'التصنيف',
        'price' => 'السعر',
        'stock' => 'الكمية',
        'description' => 'الوصف',
        'image' => 'الصورة',
        'items' => 'المنتجات',
        'items.*.product_id' => 'المنتج',
        'items.*.quantity' => 'الكمية',
        'status' => 'الحالة',
    ],
];
