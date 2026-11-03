<?php

namespace Tests\Feature\Admin;

use App\Enums\OfficeId;
use App\Models\Admissionrequirements;
use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\EnumColumnOrder;
use Tests\TestCase;

/**
 * Prompt §14's sorting control, on the intake requirement catalogue — the twenty-fifth table wired to
 * `App\Support\ListSort` (§28.3 G-40; the owed list is printed by `lane140-measure-wired-screens.php` and read
 * page-for-page by `lane215-plan-remaining-screens.php`, never remembered in prose).
 *
 * Two shapes meet on one screen here. `appliesTo` is an ENUM, and the owner's ruling of 2026-10-31 is that a desk
 * may sort one because on the school's server the order is the declaration the school wrote — so the sequence is
 * asked of the connection through the one shared helper rather than typed. `isRequired` is a flag painted as a
 * badge, and a flag is a column the query holds, which is the courses-catalogue precedent. The screen also arrived
 * with its filter's option list written out a second time beside the handler's enum read; both dropdowns now come
 * from that prop, and the last case is what keeps the vocabulary from being written twice again.
 */
class AdmissionRequirementSortTest extends TestCase
{
    use RefreshDatabase;

    private const TYPES = ['firstYear', 'transferee', 'shifter', 'continuing', 'all'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function requirement(string $name, string $appliesTo, bool $required = true): Admissionrequirements
    {
        return Admissionrequirements::create([
            'requirementName' => $name,
            'appliesTo' => $appliesTo,
            'isRequired' => $required,
        ]);
    }

    private function admin(): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => OfficeId::Admission->value,
            'employeeNo' => 'EMP-REQ-'.uniqid(), 'username' => 'req_'.uniqid(), 'email' => 'req_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('SysAdmin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    /** @return array<string, mixed> */
    private function props(array $query = []): array
    {
        $response = $this->actingAs($this->admin())->get(route('admin.reference-data.admission-requirements', $query));
        // A refusal renders as a page too, so every comparison case asks for a 200 before it reads a sequence.
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    /** @return list<string> */
    private function names(array $props): array
    {
        return array_map(static fn (array $r): string => (string) $r['requirementName'], $props['requirements']['data']);
    }

    #[Test]
    public function the_catalogue_opens_with_the_requirement_names_reading_top_to_bottom(): void
    {
        $this->requirement('Zoe Form', 'all');
        $this->requirement('Authentic Form', 'firstYear');
        $this->requirement('Mixed Form', 'shifter');

        $props = $this->props();

        // The payload claims lead so a plant against one cannot fail on the other and leave the harness reading a
        // defect it did not name.
        $this->assertCount(3, $props['requirements']['data'], 'the fixture is not the whole population this claim is about');
        $this->assertSame('requirementName', $props['sort']['column'], 'the page was not told which order was applied');
        $this->assertSame('asc', $props['sort']['direction'], 'a catalogue with no direction asked for opens ascending');
        $this->assertSame(['Authentic Form', 'Mixed Form', 'Zoe Form'], $this->names($props),
            'the catalogue did not open in name order');
    }

    #[Test]
    public function the_applies_to_header_sorts_the_enum_the_way_this_connection_sorts_it(): void
    {
        // Five rows, one per declared value: with two or three the withheld-column fallback can coincide with the
        // real order and the case passes on the defect. The names are deliberately a different alphabetical sequence
        // from any enum order, so a handler that ignored the header has no way to land on the expectation.
        $this->requirement('Lamp', 'shifter');
        $this->requirement('Bear', 'all');
        $this->requirement('Yoke', 'continuing');
        $this->requirement('Anvil', 'transferee');
        $this->requirement('Cog', 'firstYear');

        $expected = EnumColumnOrder::ascending('admissionrequirements', 'appliesTo', self::TYPES);
        $ascending = $this->props(['sort' => 'appliesTo', 'direction' => 'asc']);
        $answer = array_map(static fn (array $r): string => (string) $r['appliesTo'], $ascending['requirements']['data']);

        $this->assertSame($expected, $answer,
            'the enum header did not answer the order this connection holds for the column');
        // Reversal is the other half: a header that only ever sorts one way is a list, not a control.
        $descending = $this->props(['sort' => 'appliesTo', 'direction' => 'desc']);
        $this->assertSame(array_reverse($expected),
            array_map(static fn (array $r): string => (string) $r['appliesTo'], $descending['requirements']['data']),
            'the second click did not reverse the enum');
    }

    #[Test]
    public function the_status_header_orders_the_flag_the_badge_is_painted_from(): void
    {
        $this->requirement('Zeta Free', 'all', false);
        $this->requirement('Alpha Paid', 'all', true);
        $this->requirement('Beta Free', 'transferee', false);

        $ascending = $this->props(['sort' => 'isRequired', 'direction' => 'asc']);

        $this->assertSame([false, false, true],
            array_map(static fn (array $r): bool => (bool) $r['isRequired'], $ascending['requirements']['data']),
            'the flag did not sort optional-before-required ascending');
        // The names were picked so that sorting by requirementName gives a DIFFERENT sequence (Alpha, Beta, Zeta)
        // than sorting by the flag (Zeta, Beta, Alpha): with Annex/Charter/Bylaw the two coincided, and a mutant
        // that pointed this header at the name column stayed green. The two optionals are held apart by the
        // tie-break, not by luck.
        $this->assertSame(['Zeta Free', 'Beta Free', 'Alpha Paid'], $this->names($ascending),
            'the tie-break did not hold the two equal flags in one sequence');
    }

    #[Test]
    public function only_the_three_columns_the_query_holds_are_offered_as_orders(): void
    {
        $this->requirement('Any Requirement', 'all');

        $available = $this->props()['sort']['available'];
        sort($available);

        $this->assertSame(['appliesTo', 'isRequired', 'requirementName'], $available,
            'an order was offered that the admissionrequirements table does not hold, or a real column lost its header');
        $this->assertNotContains('requirementId', $available, 'the record key became an order on a screen that paints no id');
    }

    #[Test]
    public function every_header_the_page_paints_is_a_key_the_action_obeys(): void
    {
        $available = $this->props()['sort']['available'];
        $jsx = (string) file_get_contents(base_path('resources/js/Pages/Admin/ReferenceData/AdmissionRequirements.jsx'));

        // `DataTable` renders a header only for a key in `sort.available`, so a `sortKey` the handler does not list
        // is a control that never appears and every figure on the page stays correct — no backend case sees the loss.
        $found = preg_match_all("/sortKey: '([A-Za-z]+)'/", $jsx, $m) >= 1 ? $m[1] : [];

        $this->assertNotSame([], $found, 'the page lists no sortable header at all, so this case would prove nothing');
        foreach ($found as $key) {
            $this->assertContains($key, $available, "the page offers a header for [{$key}] and the action would not obey it");
        }
        $this->assertCount(3, $found, 'a painted column lost its header, so the desk cannot order what it can see');
    }

    #[Test]
    public function the_type_filter_and_the_type_form_read_one_list_from_the_server(): void
    {
        $prop = $this->props()['appliesTo'];
        $jsx = (string) file_get_contents(base_path('resources/js/Pages/Admin/ReferenceData/AdmissionRequirements.jsx'));

        // The page used to hold `appliesToOptions` — the enum's values written out a second time — while the handler
        // sent the same list from `App\Enums\AppliesTo`. Two definitions of one vocabulary is this file's oldest
        // defect, and it drifts silently: the form would offer a new type and the filter would not.
        $this->assertStringNotContainsString('appliesToOptions', $jsx,
            'the page spells the type list again, so the filter and the form can drift apart from the enum');
        $this->assertStringContainsString('options={typeOptions}', $jsx,
            'the type filter no longer reads the list the handler sends');

        $sent = array_map(static fn (array $a): string => (string) $a['value'], $prop);
        sort($sent);
        $fromPhp = self::TYPES;
        sort($fromPhp);
        $this->assertSame($fromPhp, $sent, 'the payload stopped answering the PHP enum it is derived from');
    }

    #[Test]
    public function a_filter_and_an_order_are_both_echoed_so_neither_control_resets(): void
    {
        $this->requirement('Zoe Form', 'shifter');
        $this->requirement('Authentic Form', 'shifter');
        $this->requirement('Other Form', 'all');

        $props = $this->props([
            'search' => 'Form', 'appliesTo' => 'shifter', 'status' => 'required',
            'sort' => 'requirementName', 'direction' => 'desc',
        ]);

        $this->assertSame('requirementName', $props['filters']['sort'],
            'the order the server obeyed never came back, so the header resets while the list stays ordered');
        $this->assertSame('desc', $props['filters']['direction']);
        $this->assertSame('shifter', $props['filters']['appliesTo']);
        $this->assertSame('required', $props['filters']['status']);
        $this->assertSame('Form', $props['filters']['search']);
        $this->assertSame(['Zoe Form', 'Authentic Form'], $this->names($props),
            'the three filters narrowed the set the order arranges');
    }

    #[Test]
    public function a_column_the_desk_never_lists_falls_back_to_the_catalogue_order(): void
    {
        $c = $this->requirement('Zoe Form', 'all');
        $this->requirement('Authentic Form', 'all');

        $default = $this->props();
        // No direction beside the unlisted key: an unlisted key WITH a direction is a legitimate order the desk could
        // ask for, and comparing it to the unsorted request is how one fallback case in this family wrote a wrong
        // expectation over right code.
        $forged = $this->props(['sort' => 'passwordHash']);

        $this->assertSame('requirementName', $forged['sort']['column'],
            'an unlisted key did not fall back to the column the catalogue is ordered by anyway');
        $this->assertSame($this->names($default), $this->names($forged),
            'the fallback answered a different sequence than the catalogue opens with');
        $this->assertNotNull($c->requirementId, 'the fixture lost its own key, so the sequences above compare nothing');
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_never_passed_through(): void
    {
        $this->requirement('Authentic Form', 'all');
        $this->requirement('Zoe Form', 'all');

        $props = $this->props(['sort' => 'requirementName', 'direction' => '; drop tables']);

        $this->assertSame('desc', $props['sort']['direction'], 'an unusable direction was answered as a direction');
        $this->assertSame(['Zoe Form', 'Authentic Form'], $this->names($props), 'the list still came back, ordered by the real column');
    }

    #[Test]
    public function the_action_reads_its_own_request_and_no_hard_coded_order_survives(): void
    {
        // `lane46-filter-contract.php` judges a key on the action's own line: one consumed a call deeper reads as
        // DEAD-SEND, so the two request values are named here rather than hidden inside the helper.
        $src = (string) file_get_contents(base_path('app/Http/Controllers/Admin/ReferenceDataController.php'));
        $start = (int) strpos($src, 'public function admissionRequirements(Request $request): Response');
        $this->assertGreaterThan(0, $start, 'the admissionRequirements action was renamed or moved');
        $end = (int) strpos(substr($src, $start), "\n    public function ");
        $body = substr($src, $start, $end > 0 ? $end : 4000);

        $this->assertStringContainsString("\$request->input('sort', '')", $body);
        $this->assertStringContainsString("\$request->input('direction', 'asc')", $body,
            'a catalogue that defaults to descending opens on the last requirement, which is a queue answer');
        $this->assertStringContainsString('ListSort::apply(', $body);
        $this->assertStringNotContainsString("->orderByDesc('requirementId')", $body,
            'the hard-coded order the mechanism replaced is back, so a header would reset while the list stayed put');
    }
}
