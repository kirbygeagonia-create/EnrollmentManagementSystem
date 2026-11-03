<?php

namespace Tests\Feature\Admin;

use App\Models\Clearancerequirements;
use App\Models\Offices;
use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Prompt §14's sorting control, on the clearance checklist catalogue — the twenty-sixth table wired to
 * `App\Support\ListSort` (§28.3 G-40, which stays leading `Open` because the owed list is printed by
 * `lane140-measure-wired-screens.php` and cross-checked page-for-page by `lane215-plan-remaining-screens.php`,
 * not counted from memory).
 *
 * Two things make this screen its own case rather than a copy of the rooms one. The Office header is a hop, and
 * choosing it as the OPENING order was measured rather than assumed: the checklist sorted by `officeId` before this
 * wiring (Registrar, Accounting, Scholarship…), which is the seeder's numbering, while the name order the desk
 * reads puts Academic Department first — live `ems` answers both sequences and they differ, so this file pins the
 * change instead of letting it arrive silently. And `requirementName` is nullable: the page paints a warning for an
 * unworded requirement, so the order must not decide whether that row exists.
 */
class ClearanceRequirementSortTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function office(string $name): Offices
    {
        return Offices::create(['officeName' => $name]);
    }

    private function requirement(int $officeId, ?string $name): Clearancerequirements
    {
        return Clearancerequirements::create(['officeId' => $officeId, 'requirementName' => $name]);
    }

    private function admin(): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => 1,
            'employeeNo' => 'EMP-CLRREQ-'.uniqid(),
            'username' => 'clrreq_'.uniqid(),
            'email' => 'clrreq_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('SysAdmin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    /** @return array<string, mixed> */
    private function props(array $query = []): array
    {
        $response = $this->actingAs($this->admin())->get(route('admin.reference-data.clearance-requirements', $query));
        // A refusal renders as a page too, so every comparison case asks for a 200 before it reads a sequence.
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    /** @return list<string> */
    private function offices(array $props): array
    {
        return array_map(
            static fn (array $r): string => (string) ($r['office']['officeName'] ?? ''),
            $props['requirements']['data']
        );
    }

    /** @return list<string> */
    private function names(array $props): array
    {
        return array_map(
            static fn (array $r): string => (string) ($r['requirementName'] ?? ''),
            $props['requirements']['data']
        );
    }

    #[Test]
    public function the_checklist_opens_with_the_office_names_reading_alphabetically(): void
    {
        // Created so that the id order (the sequence this screen used) and the name order (the one it now opens
        // with) are different — that difference is the behaviour change, and it is pinned rather than mentioned.
        $this->requirement($this->office('Zeta Registrar')->officeId, 'Loan clearance');
        $this->requirement($this->office('Academic Department')->officeId, 'Grade reconciliation');
        $this->requirement($this->office('Middle Clinic')->officeId, 'Vaccination record');

        $props = $this->props();

        // The payload claims lead, so a plant against the sequence cannot fail on the label and leave the harness
        // reading a defect it did not name.
        $this->assertCount(3, $props['requirements']['data'], 'the fixture is not the whole population this claim is about');
        $this->assertSame('office', $props['sort']['column'], 'the page was not told which order was applied');
        $this->assertSame('asc', $props['sort']['direction'], 'a catalogue with no direction asked for opens ascending');
        $this->assertSame(['Academic Department', 'Middle Clinic', 'Zeta Registrar'], $this->offices($props),
            'the checklist did not open in office-name order');
    }

    #[Test]
    public function the_office_header_orders_through_the_hop_it_names_and_adds_nothing_to_the_row(): void
    {
        $this->requirement($this->office('Clinic Annex')->officeId, 'Medical certificate');
        $first = $this->requirement($this->office('Registrar Central')->officeId, 'Form 137');
        $this->requirement($this->office('Bursar Office')->officeId, 'Library clearance');
        unset($first);

        $ascending = $this->props(['sort' => 'office', 'direction' => 'asc']);
        $descending = $this->props(['sort' => 'office', 'direction' => 'desc']);

        $this->assertSame(['Bursar Office', 'Clinic Annex', 'Registrar Central'], $this->offices($ascending),
            'the Office header did not order by the name one hop away');
        $this->assertSame(['Registrar Central', 'Clinic Annex', 'Bursar Office'], $this->offices($descending),
            'the second click did not reverse the hop');

        // §28.3 G-51: the join exists to order rows and may not reach the payload. A builder with no select list
        // emits `select *`, and once `offices` is joined that star covers the office too — so a desk row would
        // change shape depending on which header was clicked.
        $plainKeys = array_keys($this->props()['requirements']['data'][0]);
        $joinedKeys = array_keys($ascending['requirements']['data'][0]);
        sort($plainKeys);
        sort($joinedKeys);
        $this->assertSame($plainKeys, $joinedKeys,
            'ordering by Office widened the row: '.implode(',', array_diff($joinedKeys, $plainKeys)).' arrived only with the hop');
        $this->assertArrayNotHasKey('officeName', $ascending['requirements']['data'][0],
            'the hop leaked its own column onto the requirement row instead of staying inside the relation');
        // `officeId` is the requirement's OWN column, not a leak — the equality above is what proves the hop added
        // nothing. The key still has to arrive, because the edit form seeds itself from it.
        $this->assertArrayHasKey('officeId', $ascending['requirements']['data'][0],
            'ordering by Office cost the row the key it edits itself by');
    }

    #[Test]
    public function a_requirement_the_school_has_not_worded_yet_stays_on_the_list_whatever_the_order_is(): void
    {
        // `requirementName` is nullable and the page paints a warning in its place, so an order over it may not
        // become a filter: a row with no word is exactly the row the desk needs to find and finish.
        $office = $this->office('Registrar Central');
        $this->requirement($office->officeId, 'Good moral character');
        $blank = $this->requirement($office->officeId, null);

        $byName = $this->props(['sort' => 'requirementName', 'direction' => 'asc']);

        $this->assertCount(2, $byName['requirements']['data'], 'ordering by a nullable column dropped the row it cannot read');
        $this->assertSame($blank->clearanceRequirementId, $byName['requirements']['data'][0]['clearanceRequirementId'],
            'the unworded row is not on the list at all, so the desk cannot finish it');
        $this->assertNull($byName['requirements']['data'][0]['requirementName']);
    }

    #[Test]
    public function only_the_two_columns_the_page_paints_are_offered_as_orders(): void
    {
        $this->requirement($this->office('Any Office')->officeId, 'Any requirement');

        $available = $this->props()['sort']['available'];
        $sorted = $available;
        sort($sorted);

        // The named absent leads, so a plant that offers the record key fails on its own claim and not on the set
        // arithmetic beside it.
        $this->assertNotContains('clearanceRequirementId', $available,
            'the record key became an order on a screen that paints no id');
        $this->assertSame(['office', 'requirementName'], $sorted,
            'an order was offered that the query cannot answer, or a painted column lost its header');
    }

    #[Test]
    public function every_header_the_page_paints_is_a_key_the_action_obeys(): void
    {
        $available = $this->props()['sort']['available'];
        $jsx = (string) file_get_contents(base_path('resources/js/Pages/Admin/ReferenceData/ClearanceRequirements.jsx'));

        // `DataTable` renders a header only for a key in `sort.available`, so a `sortKey` the handler does not list
        // is a control that never appears and every figure on the page stays correct — no backend case sees the loss.
        $found = preg_match_all("/sortKey: '([A-Za-z]+)'/", $jsx, $m) >= 1 ? $m[1] : [];

        $this->assertNotSame([], $found, 'the page lists no sortable header at all, so this case would prove nothing');
        foreach ($found as $key) {
            $this->assertContains($key, $available, "the page offers a header for [{$key}] and the action would not obey it");
        }
        $this->assertCount(2, $found, 'a painted column lost its header, so the desk cannot order what it can see');
    }

    #[Test]
    public function a_column_the_desk_never_lists_falls_back_to_the_checklist_order(): void
    {
        $this->requirement($this->office('Zeta Registrar')->officeId, 'One');
        $this->requirement($this->office('Academic Department')->officeId, 'Two');

        $default = $this->props();
        // No direction beside the unlisted key: an unlisted key WITH a direction is a legitimate order the desk
        // could ask for, and comparing it to the unsorted request is how one fallback case in this family wrote a
        // wrong expectation over right code.
        $forged = $this->props(['sort' => 'passwordHash']);

        $this->assertSame('office', $forged['sort']['column'],
            'an unlisted key did not fall back to the column the checklist is ordered by anyway');
        $this->assertSame($this->offices($default), $this->offices($forged),
            'the fallback answered a different sequence than the checklist opens with');
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_never_passed_through(): void
    {
        $this->requirement($this->office('Academic Department')->officeId, 'One');
        $this->requirement($this->office('Zeta Registrar')->officeId, 'Two');

        $props = $this->props(['sort' => 'office', 'direction' => '; drop tables']);

        $this->assertSame('desc', $props['sort']['direction'], 'an unusable direction was answered as a direction');
        $this->assertSame(['Zeta Registrar', 'Academic Department'], $this->offices($props),
            'the list still came back, ordered by the real column');
    }

    #[Test]
    public function a_search_and_an_order_are_both_echoed_so_neither_control_resets(): void
    {
        $clinic = $this->office('Clinic Annex');
        $registrar = $this->office('Registrar Central');
        $this->requirement($clinic->officeId, 'Medical certificate');
        $this->requirement($registrar->officeId, 'Good moral character');
        $this->requirement($registrar->officeId, 'Form 137');

        $props = $this->props(['search' => 'Form', 'sort' => 'requirementName', 'direction' => 'desc']);

        $this->assertSame('requirementName', $props['filters']['sort'],
            'the order the server obeyed never came back, so the header resets while the list stays ordered');
        $this->assertSame('desc', $props['filters']['direction']);
        $this->assertSame('Form', $props['filters']['search']);
        $this->assertSame(['Form 137'], $this->names($props), 'the search did not narrow the set the order arranges');
    }

    #[Test]
    public function a_search_that_reaches_the_office_is_not_undone_by_the_order(): void
    {
        // The checklist's search already groups its alternatives inside one `where`, and the Office header joins the
        // same table for a different reason. Both must survive together: a grouped OR and a private join alias are
        // two separate ways the filter could stop meaning the desk's words.
        $clinic = $this->office('Clinic Annex');
        $registrar = $this->office('Registrar Central');
        $this->requirement($clinic->officeId, 'Medical certificate');
        $this->requirement($registrar->officeId, 'Good moral character');

        $props = $this->props(['search' => 'Clinic', 'sort' => 'office', 'direction' => 'asc']);

        $this->assertCount(1, $props['requirements']['data'], 'an office-name search did not reach the requirement it belongs to');
        $this->assertSame('Medical certificate', $this->names($props)[0]);
        $this->assertSame('office', $props['filters']['sort'], 'the order was dropped because the search joined the office');
    }

    #[Test]
    public function the_action_reads_its_own_request_and_no_hard_coded_order_survives(): void
    {
        // `lane46-filter-contract.php` judges a key on the action's own line: one consumed a call deeper reads as
        // DEAD-SEND, so the two request values are named here rather than hidden inside the helper.
        $src = (string) file_get_contents(base_path('app/Http/Controllers/Admin/ReferenceDataController.php'));
        $start = (int) strpos($src, 'public function clearanceRequirements(Request $request): Response');
        $this->assertGreaterThan(0, $start, 'the clearanceRequirements action was renamed or moved');
        $end = (int) strpos(substr($src, $start), "\n    public function ");
        $body = substr($src, $start, $end > 0 ? $end : 4000);

        $this->assertStringContainsString("\$request->input('sort', '')", $body);
        $this->assertStringContainsString("\$request->input('direction', 'asc')", $body,
            'a catalogue that defaults to descending opens on the last requirement, which is a queue answer');
        $this->assertStringContainsString('ListSort::apply(', $body);
        $this->assertStringNotContainsString("->orderBy('officeId')", $body,
            'the hard-coded seeder-numbering order the mechanism replaced is back, so a header would reset while the list stayed put');
        $this->assertStringNotContainsString("->orderBy('clearanceRequirementId')", $body,
            'a second hard-coded order survived beside the mechanism');
    }
}
