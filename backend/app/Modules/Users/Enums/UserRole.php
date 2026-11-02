<?php

namespace App\Modules\Users\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Admin = 'admin';
}
