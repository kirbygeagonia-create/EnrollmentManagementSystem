<?php

namespace App\Support;

/**
 * Fills the columns a desk form treats as optional but the schema declares NOT NULL.
 *
 * An intake payload arrives two ways: the wizard drops empty fields entirely, and
 * `ConvertEmptyStringsToNull` turns a blank text box into NULL. Either way the
 * column is missing or NULL at insert time and the whole transaction dies on a NOT
 * NULL constraint — `addresses.sitioPurok`, `students.suffix`, `guardians.email`
 * and the two guardian booleans all behaved this way. The columns are NOT NULL by
 * design (see the data dictionary), so "not provided" is stored as an empty string
 * rather than the schema being relaxed.
 *
 * Columns that really are nullable in the schema (addresses.district,
 * students.telephoneNumber) are deliberately left alone.
 */
class StudentRecordDefaults
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function person(array $row): array
    {
        foreach (['middleName' => '', 'suffix' => ''] as $column => $default) {
            $row[$column] ??= $default;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function address(array $row): array
    {
        foreach ([
            'houseBuildingNo' => '',
            'street' => '',
            'sitioPurok' => '',
            'region' => '',
            'zipCode' => '',
            'country' => 'Philippines',
        ] as $column => $default) {
            $row[$column] ??= $default;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function guardian(array $row): array
    {
        foreach ([
            'email' => '',
            'isEmergencyContact' => false,
            'isAuthorizedToActOnBehalf' => false,
        ] as $column => $default) {
            $row[$column] ??= $default;
        }

        return $row;
    }
}
