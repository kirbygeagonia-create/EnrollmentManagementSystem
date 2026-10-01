<?php

namespace App\Enums;

enum PaymentMode: string
{
    case Cash = 'cash';
    case Check = 'check';
    case Online = 'online';

    /**
     * The wording the cashier sees. The payment form used to hard-code its own
     * option list while this enum carried a different one, so the two drifted
     * apart and "Bank Check" was accepted by validation but rejected on insert.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash Payment',
            self::Check => 'Bank Check',
            self::Online => 'Online / G-Cash',
        };
    }
}
