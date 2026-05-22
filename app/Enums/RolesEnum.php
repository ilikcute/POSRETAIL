<?php

namespace App\Enums;

enum RolesEnum: string
{
    case SuperAdmin = 'super_admin';
    case Manager = 'manager';
    case Cashier = 'cashier';
}
