<?php

namespace App\Enums;

/**
 * What became of a receipt.
 *
 * `paid` and `partial` are money the school is holding — the difference is whether it
 * settled the account. `pending` is a receipt the cashier withdrew (a void: it should never
 * have been filed). `refunded` is money that was collected and then handed back to the
 * student, which reopens the account it closed (ruling 14) and is a different fact from a
 * mistake, because the school did once have the cash and spent it out again.
 */
enum PaymentStatus: string
{
    case Paid = 'paid';
    case Partial = 'partial';
    case Pending = 'pending';
    case Refunded = 'refunded';
}
