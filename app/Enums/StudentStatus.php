<?php

namespace App\Enums;

/**
 * The person record's own standing, as opposed to any one enrollment's.
 *
 * `students.status` is `enum('active','inactive')` — the vocabulary the directory's Status filter and the
 * importer's column map both answer to. It used to be cast with `StaffStatus`, which happens to hold the same
 * two strings, so an account's standing and a person's standing were the same type by coincidence rather than
 * by decision.
 */
enum StudentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
