<?php

namespace Tests\Feature\DeskQueue;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Prompt §10's data-minimisation rule, held on the source side: a desk list must not eager-load a whole student.
 *
 * §28.3 G-52. The runtime half of this lives in the seven queue classes (Admission, Assessment, Accounting,
 * Clearance, Clinic, ID and the Registrar's approval queue each assert the exact nested key set and a 200 first).
 * This class covers what those fixtures cannot reach: it walks every controller and refuses any action that still
 * loads `student` with no column list, unless that action is on the list below — the eleven detail and print sites
 * §28.3's row records as still open.
 *
 * The list is a debt, not an exemption: a site that has been narrowed but stays on the list FAILS, so the register
 * cannot keep a stale claim while the code moves on, and a NEW bare load anywhere also fails. This is the shape
 * `AssertNoSilentSkips::ALLOWED` uses for skipped tests, applied to a payload rule.
 *
 * Read the columns and the sites from `lane108-census-whole-student-loads.php` — it is the instrument that classifies
 * a LIST against the page and its whole import graph, and it prints the same action names. This test is deliberately
 * a second, dumber reader (presence of a bare literal, no page analysis), because a guard that re-derives the
 * census's judgement would drift from it; what it shares with the census is only the NAMES of the open sites.
 */
class DeskStudentLoadColumnsTest extends TestCase
{
    /**
     * Actions that still load a whole student and owe it to §28.3 G-52. This list must only ever get shorter, and
     * a site that leaves it without the code changing FAILS — a paid debt kept on the books is the same defect as
     * an unpaid one, in the other direction.
     *
     * These TEN names cover the ELEVEN sites the census counts, because `BlockingController::show` loads the
     * student twice and this reader keys by name. That is a stated blind spot, not a hidden one: the per-site
     * instrument is `lane108-census-whole-student-loads.php`, which answers 7 detail and 4 print sites today;
     * narrowing one of Blocking's two loads while leaving the other would show there first.
     */
    private const STILL_OPEN = [
        'AccountingController::show',
        'AssessmentController::show',
        'BlockingController::show',
        'ClinicController::show',
        'IDController::show',
        'RegistrarController::show',
        'ClearanceController::printSlip',
        'RegistrarController::printCertificate',
        'RegistrarController::printClassCards',
        'RegistrarController::printSubjectLoad',
    ];

    /**
     * Two sites are not a debt: the admission case screen and the evaluation record screen are where an officer
     * EDITS the person's birthdate, birthplace, citizenship, religion, civil status, contact number, telephone,
     * semesters completed and years in institution, so their student must carry those columns.
     *
     * `EvaluationController::show` moved here from the open list on 2026-10-31, after the page's own import graph
     * was read instead of the page file alone: it renders `EnrollmentProfileForm`, which writes all of the above.
     * Narrowing it would have emptied a desk form while every payload case stayed green, because those cases assert
     * the keys the server sent and never the keys the screen reads. Named separately because folding either screen
     * into the open list would let the register claim a fix that would break the screen.
     */
    private const LEGITIMATE = [
        'AdmissionController::show',
        'EvaluationController::show',
    ];

    #[Test]
    public function no_desk_action_loads_a_whole_student_except_the_sites_the_register_names(): void
    {
        $found = $this->wholeStudentLoadSites();
        $allowed = array_merge(self::STILL_OPEN, self::LEGITIMATE);

        $unexpected = array_values(array_diff($found, $allowed));
        $retired = array_values(array_diff($allowed, $found));

        $this->assertSame(
            [],
            $unexpected,
            'these actions eager-load a whole student row and §28.3 G-52 does not list them as open — a desk list'
            ." must name the columns it paints: \n  ".implode("\n  ", $unexpected)
        );
        $this->assertSame(
            [],
            $retired,
            '§28.3 G-52 still calls these sites open, but the code no longer loads a whole student there — narrow'
            ." the register row and this list together, or the document claims a debt that is paid: \n  "
            .implode("\n  ", $retired)
        );

        $this->assertNotEmpty($found, 'the scan found no whole-student load anywhere, which means the reader is'
            .' broken rather than the code being clean — plant a bare \'student\' load and watch this fail');
    }

    #[Test]
    public function the_open_sites_are_fewer_than_the_queue_sites_that_were_narrowed(): void
    {
        // A relation between the two populations, not a remembered number: the debt must be smaller than the work
        // done, and the day it stops being true is the day the register is finished. Stated as a relation because a
        // frozen count here is a reading of one day's tree (lane 61's register-row lesson).
        $open = count($this->wholeStudentLoadSites());
        $narrowed = 0;
        foreach (glob($this->root().'/app/Http/Controllers/*/*Controller.php') ?: [] as $f) {
            $narrowed += preg_match_all("/'(?:[a-zA-Z0-9_.]*\.)?student:studentId,schoolIdNumber,lastName,firstName,middleName'/", (string) file_get_contents($f));
        }

        $this->assertGreaterThan($open, $narrowed, "the register still owes more than it has paid: {$narrowed} narrowed"
            ." site(s) against {$open} open whole-student load(s) — that ordering is the point of the row");
    }

    /**
     * Every `with`/`load` literal whose dot-separated segments include `student` with no `:` column list.
     *
     * @return string[] `Controller::action` names, de-duplicated
     */
    private function wholeStudentLoadSites(): array
    {
        $sites = [];
        $files = array_merge(
            glob($this->root().'/app/Http/Controllers/*Controller.php') ?: [],
            glob($this->root().'/app/Http/Controllers/*/*Controller.php') ?: [],
            glob($this->root().'/app/Http/Controllers/*/*/*Controller.php') ?: [],
        );
        $this->assertNotEmpty($files, 'the controller walk found nothing, so this guard would clear the tree by reading none of it');

        foreach ($files as $abs) {
            $src = (string) file_get_contents($abs);
            $name = basename($abs, '.php');
            preg_match_all('/(?:public|protected|private) function (\w+)\s*\(/', $src, $dm, PREG_OFFSET_CAPTURE);
            $decls = $dm[1];
            preg_match_all('/\b(?:with|load)\(\s*(?:\[)?([^\]()]{0,400})/s', $src, $lm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach ($lm as $m) {
                $at = $m[0][1];
                $action = 'TOP';
                foreach ($decls as $d) {
                    if ($d[1] < $at) {
                        $action = $d[0];
                    }
                }
                preg_match_all("/'([a-zA-Z0-9_.]*)'/", $m[1][0], $pm);
                foreach ($pm[1] as $path) {
                    if (in_array('student', explode('.', $path), true) && ! str_contains($path, ':')) {
                        $sites[$name.'::'.$action] = true;
                    }
                }
            }
        }

        return array_keys($sites);
    }

    private function root(): string
    {
        return dirname(__DIR__, 3);
    }
}
