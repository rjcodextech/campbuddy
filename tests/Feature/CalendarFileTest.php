<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * POST /calendar.ics echoes a calendar file built on the phone back with the
 * headers iPhone Safari needs to offer "Add to Calendar" — and nothing else.
 */
class CalendarFileTest extends TestCase
{
    private const ICS = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//CampBuddy//Day planner//EN\r\nBEGIN:VEVENT\r\nUID:x@campbuddy\r\nDTSTAMP:20260924T100000Z\r\nDTSTART:20260924T130000Z\r\nDTEND:20260924T131500Z\r\nSUMMARY:Meet Aisha\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

    public function test_a_calendar_comes_back_as_a_calendar(): void
    {
        $response = $this->post('/calendar.ics', ['ics' => self::ICS, 'filename' => 'meet-aisha.ics'])
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertHeader('Content-Disposition', 'inline; filename="meet-aisha.ics"')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame(self::ICS, $response->getContent());
        $response->assertCookieMissing(config('session.cookie'));
    }

    public function test_anything_that_is_not_a_calendar_is_refused(): void
    {
        $this->post('/calendar.ics', ['ics' => '<script>alert(1)</script>'])->assertStatus(422);
        $this->post('/calendar.ics', ['ics' => self::ICS, 'filename' => '../../etc/passwd'])->assertSessionHasErrors('filename');
        $this->post('/calendar.ics', ['ics' => str_repeat('A', 200001)])->assertSessionHasErrors('ics');
    }
}
