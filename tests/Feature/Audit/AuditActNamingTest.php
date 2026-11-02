<?php

namespace Tests\Feature\Audit;

use App\Models\Auditlogs;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Support\AuditAct;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Development prompt §9 asks that seven families of act be answerable, and `AuditLogObserver` watches every model
 * so on its own it can only say `created`, `updated` or `deleted` — a fact about a row, not about what a desk did.
 * `App\Support\AuditAct` is the one place that names an act, and these cases pin the mechanism the per-act sites
 * write through: the name reaches the audit row, a write outside any act keeps the event word, the label is
 * restored when the act ends (so a second act in one request cannot inherit the first one's name), and a word
 * outside the list is refused rather than quietly inventing a ninth spelling of an act.
 *
 * Each act site's own pin lives with that act's test file, because a case that reaches a service through a stub
 * would assert the stub.
 */
class AuditActNamingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The acts are named for routes whose desks are seated by RBAC, and one case below signs in as SysAdmin,
        // so the roles have to exist rather than be assumed from a previous test's seeding.
        $this->seed(RbacSeeder::class);
    }

    private function lastAudit(): Auditlogs
    {
        /** @var Auditlogs $row */
        $row = Auditlogs::latest('auditId')->firstOrFail();

        return $row;
    }

    #[Test]
    public function an_act_names_every_audit_row_written_inside_it(): void
    {
        AuditAct::run(AuditAct::PAYMENT_VOID, function (): void {
            Religions::create(['religionName' => 'Test Faith One']);
        });

        $row = $this->lastAudit();
        $this->assertSame('religions', $row->entityTable, 'the row under test is the one the act wrote');
        $this->assertSame('payment.void', $row->action);
    }

    #[Test]
    public function a_write_outside_any_act_keeps_the_event_word(): void
    {
        $religion = Religions::create(['religionName' => 'Test Faith Two']);

        $this->assertSame('created', $this->lastAudit()->action);

        $religion->update(['religionName' => 'Test Faith Two, amended']);
        $this->assertSame('updated', $this->lastAudit()->action, 'the generic words are still written when no act names the edit');
    }

    #[Test]
    public function the_label_is_restored_when_the_act_ends(): void
    {
        AuditAct::run(AuditAct::STEP_SIGN, function (): void {
            Religions::create(['religionName' => 'Nested One']);

            AuditAct::run(AuditAct::ROLE_CHANGE, function (): void {
                Religions::create(['religionName' => 'Nested Two']);
            });

            // The inner act is over; the rows after it belong to the outer one, not to the inner. Without the
            // restore this would read `role.change` and the nesting rule would be a claim rather than a behaviour.
            Religions::create(['religionName' => 'Nested Three']);
        });

        $actions = Auditlogs::where('entityTable', 'religions')
            ->orderBy('auditId')
            ->pluck('action')
            ->values()
            ->all();

        $this->assertSame(['step.sign', 'role.change', 'step.sign'], $actions);
        $this->assertNull(AuditAct::current(), 'no act is running once the outer one returns');
    }

    #[Test]
    public function a_thrown_act_still_clears_its_label(): void
    {
        try {
            AuditAct::run(AuditAct::CREDIT_DECIDE, function (): void {
                throw new \RuntimeException('forced failure inside the act');
            });
            $this->fail('the act was expected to rethrow its own failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced failure inside the act', $e->getMessage());
        }

        $this->assertNull(AuditAct::current(), 'a failed act must not leave its name on the next write');

        Religions::create(['religionName' => 'After the Failure']);
        $this->assertSame('created', $this->lastAudit()->action);
    }

    #[Test]
    public function a_word_outside_the_named_acts_is_refused_by_name(): void
    {
        Religions::create(['religionName' => 'Before the Refusal']);

        try {
            AuditAct::run('payment.cancelled', function (): void {
                $this->fail('an unknown act must never run its body');
            });
            $this->fail('the unknown act was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('payment.cancelled', $e->getMessage());
            $this->assertStringContainsString(AuditAct::PAYMENT_VOID, $e->getMessage(),
                'the refusal names the vocabulary it accepts, so the caller need not guess it');
        }

        $this->assertSame('created', $this->lastAudit()->action, 'the refused act left the previous state alone');
    }

    #[Test]
    public function the_vocabulary_is_one_list_and_covers_every_family_the_prompt_names(): void
    {
        $this->assertSame($this->uniqueValues(AuditAct::LIST), AuditAct::LIST,
            'one act must not have two spellings in the list');

        // §9's seven families, in the order the prompt states them, plus the two the project's own rulings added
        // (an enrollment issued and an enrollment dropped, which §6.1 and §7 need answerable too).
        $families = [
            'requirement verification' => AuditAct::REQUIREMENT_VERIFY,
            'provisional to official' => AuditAct::EXAM_VERIFY,
            'a crediting decision' => AuditAct::CREDIT_DECIDE,
            'a registrar return' => AuditAct::RETURN,
            'a receipt void' => AuditAct::PAYMENT_VOID,
            'a receipt refund' => AuditAct::PAYMENT_REFUND,
            'a step signing' => AuditAct::STEP_SIGN,
            'a role change' => AuditAct::ROLE_CHANGE,
            'an enrollment issued' => AuditAct::ISSUE,
            'an enrollment dropped' => AuditAct::DROP,
        ];

        foreach ($families as $label => $word) {
            $this->assertContains($word, AuditAct::LIST, "§9's [{$label}] family has no act word");
            $this->assertMatchesRegularExpression('/^[a-z]+\.[a-z_]+$/', $word,
                "an act reads <table>.<verb> so a query on the audit table can name it; [{$word}] does not");
        }
    }

    #[Test]
    public function every_const_vocabulary_entry_is_in_the_list(): void
    {
        $declared = [];

        foreach ((new \ReflectionClass(AuditAct::class))->getConstants() as $name => $value) {
            if ($name !== 'LIST' && is_string($value)) {
                $declared[] = $value;
            }
        }

        $this->assertSame($this->uniqueValues($declared), $this->uniqueValues(AuditAct::LIST),
            'the constants and the list are two answers to one question about which acts exist');
    }

    /**
     * The route map is the whole mechanism for sixteen desk acts, so it is read against the route table rather
     * than trusted: a route renamed or retired would leave its act unnamed and every case that does not drive
     * that exact act would stay green. A value outside the list is refused for the same reason — the map may
     * point at a word, never invent one.
     */
    #[Test]
    public function every_route_the_map_names_exists_and_every_value_is_a_listed_act(): void
    {
        foreach (AuditAct::ROUTE_ACTS as $route => $word) {
            $this->assertTrue(Route::has($route),
                "the act map names [{$route}], which is not a route this application has — the act would go unnamed.");
            $this->assertContains($word, AuditAct::LIST,
                "the act map points [{$route}] at [{$word}], which is not one of the named acts.");
        }

        $this->assertNotEmpty(AuditAct::ROUTE_ACTS, 'an empty map means no desk act is named at all');
    }

    /**
     * A word in the list that nothing writes is decoration, and the register would claim a family the log cannot
     * answer. Each name must therefore be reachable: either the map names it for a route, or a call site in app/
     * runs it (which is how the issuing act works, reached from three routes and the demo pipeline).
     */
    #[Test]
    public function every_named_act_is_reachable_from_a_route_or_a_call_site(): void
    {
        $mapped = array_values(AuditAct::ROUTE_ACTS);
        $called = $this->actWordsUsedInApp();

        $unreachable = array_values(array_diff(AuditAct::LIST, $mapped, $called));

        $this->assertSame([], $unreachable,
            'these act words exist but nothing names them, so the audit trail cannot answer a query for them');
    }

    #[Test]
    public function a_desk_request_names_the_act_on_every_row_that_request_writes(): void
    {
        // The middleware is the only thing between the request and the observer, so this case is what proves the
        // route is resolved by the time the group middleware runs. Were it not, every act would fall back to the
        // event word and every other case in this file would still pass.
        $actor = $this->deskAccount('actor@example.com', ['SysAdmin']);
        $target = $this->deskAccount('target@example.com', ['Staff']);

        $this->actingAs($actor)
            ->post(route('admin.users.status.toggle', $target))
            ->assertRedirect();

        $rows = Auditlogs::where('entityTable', 'staffusers')->pluck('action')->unique()->values()->all();

        $this->assertContains('account.deactivate', $rows,
            'the toggle request wrote its staffusers row under the event word, so the act never reached the log');
        $this->assertNotContains('assigned', $rows,
            'no site may invent its own audit word now that one list names the acts');
    }

    #[Test]
    public function the_audit_desk_is_offered_only_the_words_the_log_can_hold(): void
    {
        $this->assertSame(array_merge(AuditAct::EVENTS, AuditAct::LIST), AuditAct::vocabulary(),
            'the vocabulary is the event words then the named acts, in one place');

        $page = file_get_contents(base_path('resources/js/Pages/Admin/UserManagement/AuditLogs.jsx'));
        $this->assertIsString($page);

        // The page used to type thirteen action words, nine of which no code writes. Its options must come from
        // the prop the handler fills from `AuditAct::vocabulary()`, so a later act word reaches the desk without
        // anyone remembering to edit the screen.
        $this->assertStringContainsString('...(actions || []).map(', $page,
            'the Action filter is no longer built from the vocabulary the server sends');
        $this->assertStringNotContainsString("'unassigned'", $page,
            'the page is typing its own action words again');

        $deadWords = ['viewed', 'printed', 'approved', 'rejected', 'status_changed', 'assigned', 'unassigned', 'login', 'logout'];
        $typed = array_values(array_filter($deadWords,
            static fn (string $w): bool => (bool) preg_match('/value:\s\''.preg_quote($w, '/').'\'/', $page)));
        $this->assertSame([], $typed,
            'these options name a word nothing writes: '.implode(', ', $typed));
    }

    /**
     * Every `AuditAct::run(<word>, …)` call site in app/, read off the source. There is no JavaScript suite and
     * PHPStan cannot reach a string constant's meaning, so reading the file is what makes the claim checkable —
     * the method §28.3 G-39's vocabulary guards already use.
     *
     * @return list<string>
     */
    private function actWordsUsedInApp(): array
    {
        $words = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php'
                && preg_match_all('/AuditAct::run\(\s*AuditAct::([A-Z_]+)/', (string) file_get_contents($file->getPathname()), $m)) {
                foreach ($m[1] as $const) {
                    $words[] = constant(AuditAct::class.'::'.$const);
                }
            }
        }

        return $words;
    }

    private function deskAccount(string $email, array $roles): Staffusers
    {
        $account = Staffusers::factory()->make([
            'employeeNo' => 'EMP-'.uniqid(),
            'username' => 'user'.uniqid(),
            'email' => $email,
            'role' => 'staff',
            'officeId' => null,
        ]);
        unset($account->remember_token);
        $account->save();

        $account->syncRoles($roles);

        return $account;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function uniqueValues(array $values): array
    {
        return array_values(array_unique($values));
    }
}
