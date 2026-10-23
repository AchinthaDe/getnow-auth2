<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Enums;

enum UserRole: string
{
    case Superadmin = 'superadmin';
    case Retailer = 'retailer';
    case Customer = 'customer';
}
