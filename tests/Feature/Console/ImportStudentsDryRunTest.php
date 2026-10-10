<?php

namespace Tests\Feature\Console;

use App\Models\Religions;
use App\Models\Students;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Registrar's real records have never been loaded — the demo pipeline is what the desks run
 * on. The owner ruled the migration be approached as a dry run first: a column map that says what
 * a sheet must carry, and a report of what the app would refuse, before any row may be inserted.
 *
 * These tests pin the report's honesty. The one that matters most is the last: a command that
 * prints "would create" while writing nothing is only safe if it really writes nothing, so every
 * case asserts the student table is still empty afterwards.
 */
class ImportStudentsDryRunTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = 'school_id_number,last_name,first_name,middle_name,suffix,gender,birthdate,'
        .'birthplace,citizenship,contact_number,telephone_number,email,username,password,civil_status,religion,status';

    /** @var array<int, string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }

        $this->paths = [];

        parent::tearDown();
    }

    private function sheet(string $body): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ems-import-').'.csv';
        file_put_contents($path, self::HEADERS."\n".$body."\n");
        $this->paths[] = $path;

        return $path;
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function row(array $overrides = []): string
    {
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
            'religion' => '',
            'status' => 'active',
        ];

        return implode(',', array_map(
            // birthplace holds a comma, so a bare implode would silently split it into two
            // fields and shift every column after it — the sheet must be real CSV.
            fn (string $v): string => str_contains($v, ',') ? '"'.$v.'"' : $v,
            array_replace($cells, $overrides)
        ));
    }

    private function report(string $path): string
    {
        Artisan::call('ems:import:students', ['--csv' => $path]);

        return Artisan::output();
    }

    #[Test]
    public function a_clean_row_is_reported_as_creatable_and_nothing_is_written(): void
    {
        $output = $this->report($this->sheet($this->row()));

        $this->assertStringContainsString('would create', $output);
        $this->assertStringContainsString('Rows read: 1   would create: 1   refused: 0', $output);
        $this->assertStringContainsString('Nothing was written', $output);
        $this->assertSame(0, Students::count(), 'the dry run wrote a row');
    }

    #[Test]
    public function a_school_id_repeated_inside_one_sheet_is_refused_naming_the_row_it_met_first(): void
    {
        $output = $this->report($this->sheet($this->row()."\n".$this->row()));

        $this->assertStringContainsString('repeats row 2', $output);
        $this->assertStringContainsString('Rows read: 2   would create: 1   refused: 1', $output);
        $this->assertSame(0, Students::count());
    }

    #[Test]
    public function a_school_id_already_on_file_is_refused_as_a_duplicate(): void
    {
        Students::create([
            'schoolIdNumber' => '2024-00901',
            'lastName' => 'Onfile',
            'firstName' => 'Already',
            'gender' => 'female',
            'birthdate' => '2005-01-01',
            'birthplace' => 'Tupi, South Cotabato',
            'citizenship' => 'Filipino',
            'contactNumber' => '09179999999',
            'email' => 'already.on.file@example.com',
            'username' => 'already_on_file',
            'passwordHash' => 'x',
            'status' => 'active',
        ]);

        $output = $this->report($this->sheet($this->row()));

        $this->assertStringContainsString("school_id_number '2024-00901' is already on file", $output);
        $this->assertStringContainsString('refused: 1', $output);
    }

    #[Test]
    public function a_gender_outside_the_enum_names_the_values_the_column_accepts(): void
    {
        $output = $this->report($this->sheet($this->row(['gender' => 'Male'])));

        $this->assertStringContainsString("gender 'Male' is not one of male, female", $output);
        $this->assertSame(0, Students::count());
    }

    #[Test]
    public function a_religion_reference_data_does_not_hold_is_refused_by_name(): void
    {
        Religions::create(['religionName' => 'Roman Catholic']);

        $output = $this->report($this->sheet($this->row(['religion' => 'Born Again'])));

        $this->assertStringContainsString("religion 'Born Again' matches no religion in Reference Data", $output);
        $this->assertSame(0, Students::count());
    }

    #[Test]
    public function an_optional_column_may_be_left_off_the_sheet_entirely(): void
    {
        $headers = 'school_id_number,last_name,first_name,gender,birthdate,birthplace,citizenship,'
            .'contact_number,email,username';
        $path = tempnam(sys_get_temp_dir(), 'ems-import-').'.csv';
        file_put_contents($path, $headers."\n"
            .'2024-00902,Ramirez,Juan,male,2004-09-02,General Santos,Filipino,09170000002,juan@example.com,juan_test'."\n");
        $this->paths[] = $path;

        $output = $this->report($path);

        $this->assertStringContainsString('Rows read: 1   would create: 1   refused: 0', $output);
        $this->assertStringContainsString('no password', $output);
        $this->assertSame(0, Students::count());
    }

    #[Test]
    public function a_sheet_missing_a_required_column_is_refused_before_any_row_is_read(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ems-import-').'.csv';
        file_put_contents($path, "school_id_number,last_name\n2024-00903,Dela Cruz\n");
        $this->paths[] = $path;

        Artisan::call('ems:import:students', ['--csv' => $path]);

        $this->assertStringContainsString("Missing required column: 'first_name'", Artisan::output());
        $this->assertSame(0, Students::count());
    }

    #[Test]
    public function a_row_with_the_wrong_field_count_is_refused_naming_both_counts(): void
    {
        // The classic spreadsheet accident is an unquoted comma inside a value: the columns after
        // it silently shift and every field reads its neighbour's answer, which is how a sheet of
        // 200 rows can be quietly wrong. A short row is the same defect, so it stands in here.
        $output = $this->report($this->sheet('2024-00905,Reyes,Juan,male,2005-05-05'));

        $this->assertStringContainsString('5 fields where the header declares 17', $output);
        $this->assertSame(0, Students::count());
    }

    #[Test]
    public function the_template_prints_the_map_without_reading_a_file(): void
    {
        Artisan::call('ems:import:students', ['--template' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('school_id_number', $output);
        $this->assertStringContainsString('a calendar date', $output);
        $this->assertStringContainsString(self::HEADERS, $output);
    }
}
