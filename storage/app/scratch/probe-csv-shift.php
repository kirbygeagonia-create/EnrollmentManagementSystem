<?php
// What does the CSV reader actually return for the test's own header and row?
$cells = [
    'school_id_number' => '2024-00901',
    'last_name' => 'Dela Cruz',
    'first_name' => 'Ana',
    'middle_name' => '',
    'suffix' => '',
    'gender' => 'female',
    'birthdate' => '2005-04-11',
    'birthplace' => 'Tupi, South Cotabato',
    'citizenship' => 'Filipino',
    'contact_number' => '09170000001',
    'telephone_number' => '',
    'email' => 'ana.testable@example.com',
    'username' => 'ana_test',
    'password' => '',
    'civil_status' => 'single',
    'religion' => 'Born Again',
    'status' => 'active',
];

$line = implode(',', $cells);
$header = implode(',', array_keys($cells));

echo "cells: ".count($cells)."\n";
echo "line : {$line}\n\n";

$path = tempnam(sys_get_temp_dir(), 'probe').'.csv';
file_put_contents($path, $header."\n".$line."\n");

$file = new SplFileObject($path, 'r');
$file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

foreach ($file as $n => $row) {
    echo "record {$n}: ".var_export($row, true)."\n\n";
}

echo "-- naive parse of the data line (the birthplace has a comma and is unquoted):\n";
var_export(str_getcsv($line));
echo "\n";
unlink($path);
