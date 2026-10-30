<?php

namespace Tests\Feature\Notification;

use App\Models\Notifications;
use App\Models\Staffusers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Prompt §8 and §28.3 G-47's ownership half: a notice may be marked read only by the desk it was addressed to,
 * and a refusal must not arrive dressed as a success.
 *
 * The endpoint already refused to WRITE another desk's notice, then answered `{"ok": true}` anyway. That is the
 * shape this repository has been caught by three times: a body that parses is taken as proof the act happened, so
 * a bell could grey out a notice nobody cleared. The refusal now carries a reason and a 403, and the row's
 * `read_at` is asserted to stay null rather than inferred from the response.
 *
 * `read_at` is an instant, so `now()` is the right writer here — this is not the DATE-column class lane 87 bounds
 * (prompt §1.2: a day is the school's civil date, a moment keeps the machine's clock).
 */
class NotificationMarkReadOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function notice(Staffusers $to): Notifications
    {
        return Notifications::create([
            'type' => 'App\\Notifications\\WorkflowStepSigned',
            'notifiable_type' => Staffusers::class,
            'notifiable_id' => $to->userId,
            'data' => ['title' => 'A box is waiting', 'message' => 'The record is at your desk.'],
        ]);
    }

    #[Test]
    public function the_desk_a_notice_is_addressed_to_marks_it_read(): void
    {
        $owner = Staffusers::factory()->create(['officeId' => 4]);
        $notice = $this->notice($owner);

        $this->assertNull($notice->read_at, 'the fixture starts unread');

        $this->actingAs($owner)
            ->post(route('notifications.read', $notice))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNotNull($notice->fresh()->read_at, 'the owner’s own notice came back unread');
    }

    #[Test]
    public function a_different_desk_is_refused_and_the_notice_stays_unread(): void
    {
        $owner = Staffusers::factory()->create(['officeId' => 4]);
        $stranger = Staffusers::factory()->create(['officeId' => 5]);
        $notice = $this->notice($owner);

        $this->actingAs($stranger)
            ->post(route('notifications.read', $notice))
            ->assertForbidden();

        // The promise is the row, not the response: an ok-shaped answer over an untouched record is the defect.
        $this->assertNull($notice->fresh()->read_at, 'a notice another desk tried to clear came back read');
    }

    #[Test]
    public function the_refused_attempt_does_not_change_the_addressed_desks_unread_count(): void
    {
        $owner = Staffusers::factory()->create(['officeId' => 4]);
        $stranger = Staffusers::factory()->create(['officeId' => 5]);
        $notice = $this->notice($owner);

        $before = (int) $this->actingAs($owner)
            ->getJson(route('notifications.index'))
            ->json('unreadCount');

        $this->actingAs($stranger)->post(route('notifications.read', $notice))->assertForbidden();

        $after = (int) $this->actingAs($owner)
            ->getJson(route('notifications.index'))
            ->json('unreadCount');

        $this->assertSame($before, $after, 'a desk that cannot see the notice still emptied another desk’s bell');
        $this->assertSame(1, $after);
    }

    #[Test]
    public function the_bell_hands_over_the_notice_the_addressed_desk_is_the_one_reading(): void
    {
        // The other half of the same rule, asserted from the payload: the list is filtered by the addressee, so a
        // notice written for one desk is not in another desk’s answer at all — which is what makes the 403 above
        // a refusal of an invention rather than a permission on a visible row.
        $owner = Staffusers::factory()->create(['officeId' => 4]);
        $stranger = Staffusers::factory()->create(['officeId' => 5]);
        $this->notice($owner);

        $ids = collect($this->actingAs($stranger)->getJson(route('notifications.index'))->json('notifications') ?? [])
            ->pluck('id')
            ->all();

        $this->assertNotContains(Notifications::latest('id')->first()->id, $ids, 'the stranger’s bell carried the other desk’s notice');
    }
}
