<?php

namespace Tests\Feature\E2E;

use App\Models\Admissions;
use App\Models\Staffusers;
use App\Models\Studentrequirementsubmissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DebugExamTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'ems',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
        ]);
        DB::purge('mysql');
    }

    private function staffForOffice(int $officeId): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => $officeId,
            'role' => 'officeHead',
            'username' => 'dbg_office'.$officeId.'_'.uniqid(),
            'email' => 'dbg_office'.$officeId.'_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();
        $staff->assignRole('OfficeHead');

        return $staff;
    }

    #[Test]
    public function debug_exam_flow(): void
    {
        // Use an existing pending admission for course 3 (BSCrim)
        $admission = Admissions::where('courseId', 3)->where('admissionStatus', 'pending')->firstOrFail();
        dump('admission: '.$admission->admissionId.' student='.$admission->studentId.' term='.$admission->termId);

        Studentrequirementsubmissions::where('admissionId', $admission->admissionId)
            ->update(['submissionStatus' => 'verified']);

        // General exam (Guidance, office 7)
        $r1 = $this->actingAs($this->staffForOffice(7))
            ->post(route('exam.general.record'), [
                'studentId' => $admission->studentId,
                'courseId' => $admission->courseId,
                'termId' => $admission->termId,
                'examResult' => 'pass',
                'examDate' => now()->toDateString(),
            ]);
        dump('general status: '.$r1->status());

        // Course-specific (office 4)
        $r2 = $this->actingAs($this->staffForOffice(4))
            ->post(route('exam.course-specific.record'), [
                'studentId' => $admission->studentId,
                'courseId' => $admission->courseId,
                'termId' => $admission->termId,
                'examResult' => 'pass',
                'examDate' => now()->toDateString(),
            ]);
        dump('course-specific status: '.$r2->status());

        $admission->refresh();
        dump('admission status: '.$admission->admissionStatus->value);
        $this->assertTrue(true);
    }
}
