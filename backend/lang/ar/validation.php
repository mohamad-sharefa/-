<?php

// Arabic messages for the validation rules the API uses. Missing keys fall back to English.
return [
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'email' => 'يجب أن يكون :attribute عنوان بريد إلكتروني صحيحاً.',
    'max' => [
        'string' => 'يجب ألا يزيد :attribute عن :max حرفاً.',
    ],
    'min' => [
        'string' => 'يجب أن يتكون :attribute من :min أحرف على الأقل.',
    ],
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
    ],
];
