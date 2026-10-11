<?php

namespace Tests\Feature\Errors;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * What a desk sees when the database refuses something the application did not predict.
 *
 * Every constraint the app knows about is checked in code first, so a QueryException means an
 * unanticipated clash — a duplicate no rule covered, or a row deleted while a form was open. The
 * default is a 500 page, which reads to a non-technical user as "the system broke and my work may
 * be gone". A submission is sent back with the two things they can act on instead: nothing was
 * written, and reload the record. The exception is still logged in full.
 */
class DataConflictTest extends TestCase
{
    private function queryException(): QueryException
    {
        return new QueryException(
            'mysql',
            'insert into `students` (`schoolIdNumber`) values (?)',
            ['2024-01567'],
            new \PDOException(
                'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '
                .'\x272024-01567\x27 for key \x27students.uq_students_schoolid\x27',
                23000
            ),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        RouteFacade::middleware('web')->post('__test/db-conflict', function () {
            throw $this->queryException();
        });

        RouteFacade::middleware('web')->get('__test/db-conflict', function () {
            throw $this->queryException();
        });
    }

    public function test_a_submission_that_the_database_refuses_is_sent_back_not_to_a_500_page(): void
    {
        $this->post('/__test/db-conflict', [], ['Referer' => '/admin/reference-data/courses'])
            ->assertRedirect('/admin/reference-data/courses');

        $this->assertNotNull(session('error'));
    }

    public function test_the_message_says_nothing_was_written_and_gives_the_next_step(): void
    {
        $this->post('/__test/db-conflict', [], ['Referer' => '/admin/reference-data/courses']);

        $error = session('error');

        // The three things a desk needs, in this order: it did not happen, your data is intact,
        // and here is what to do. A SQL fragment answers none of them.
        $this->assertStringContainsString('could not be saved', $error);
        $this->assertStringContainsString('Nothing was written', $error);
        $this->assertStringContainsString('Reload the record', $error);
        $this->assertStringNotContainsString('SQLSTATE', $error);
        $this->assertStringNotContainsString('1062', $error);
    }

    public function test_a_page_that_was_only_being_read_keeps_the_normal_server_error(): void
    {
        // Nothing was submitted, so there is no step to repeat — the 500 screen is the honest one.
        $this->get('/__test/db-conflict')->assertStatus(500);

        $this->assertArrayNotHasKey('error', session()->all());
    }

    public function test_a_json_caller_gets_a_conflict_status_and_a_plain_message(): void
    {
        $this->postJson('/__test/db-conflict')
            ->assertStatus(409)
            ->assertJsonPath('message', 'That could not be saved because it conflicts with information already on file. Reload the record and try again.');
    }
}
