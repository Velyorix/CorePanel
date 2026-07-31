<?php

namespace Tests\Feature\Tickets;

use Core\Billing\Models\BillingSequence;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Services\TicketNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TicketNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    private TicketNumberService $numbers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->numbers = app(TicketNumberService::class);

        config([
            'corepanel.tickets.numbering.prefix' => 'TK',
            'corepanel.tickets.numbering.padding' => 5,
            'corepanel.tickets.numbering.include_year' => true,
            'corepanel.tickets.numbering.reset_yearly' => true,
            'corepanel.tickets.numbering.separator' => '-',
        ]);
    }

    public function test_ticket_number_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(TicketNumberService::class),
            app(TicketNumberService::class),
        );
    }

    public function test_assign_number_sets_tk_year_sequence_format(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00'));

        $ticket = Ticket::factory()->create(['ticket_number' => null]);

        $numbered = $this->numbers->assignNumber($ticket);

        $this->assertSame('TK-2026-00001', $numbered->ticket_number);
        $this->assertSame(1, BillingSequence::query()->where('name', 'ticket')->value('current_value'));
    }

    public function test_assign_number_is_idempotent_when_already_numbered(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00'));

        $ticket = Ticket::factory()->create(['ticket_number' => null]);
        $first = $this->numbers->assignNumber($ticket);
        $second = $this->numbers->assignNumber($first);

        $this->assertSame($first->ticket_number, $second->ticket_number);
        $this->assertSame(1, BillingSequence::query()->where('name', 'ticket')->value('current_value'));
    }

    public function test_assign_number_increments_sequence(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00'));

        $first = $this->numbers->assignNumber(Ticket::factory()->create(['ticket_number' => null]));
        $second = $this->numbers->assignNumber(Ticket::factory()->create(['ticket_number' => null]));

        $this->assertSame('TK-2026-00001', $first->ticket_number);
        $this->assertSame('TK-2026-00002', $second->ticket_number);
        $this->assertSame(2, BillingSequence::query()->where('name', 'ticket')->value('current_value'));
    }

    public function test_yearly_reset_starts_sequence_at_one_in_new_year(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-31 23:00:00'));

        $this->numbers->assignNumber(Ticket::factory()->create(['ticket_number' => null]));
        $this->assertSame(1, BillingSequence::query()->where('name', 'ticket')->value('current_value'));

        Carbon::setTestNow(Carbon::parse('2027-01-01 00:05:00'));

        $nextYear = $this->numbers->assignNumber(Ticket::factory()->create(['ticket_number' => null]));

        $this->assertSame('TK-2027-00001', $nextYear->ticket_number);
        $this->assertSame(1, BillingSequence::query()->where('name', 'ticket')->value('current_value'));
        $this->assertSame(2027, BillingSequence::query()->where('name', 'ticket')->value('year'));
    }

    public function test_preview_next_does_not_consume_sequence(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00'));

        $this->assertSame('TK-2026-00001', $this->numbers->previewNext());
        $this->assertSame(0, BillingSequence::query()->where('name', 'ticket')->value('current_value') ?? 0);

        $this->numbers->assignNumber(Ticket::factory()->create(['ticket_number' => null]));

        $this->assertSame('TK-2026-00002', $this->numbers->previewNext());
        $this->assertSame(1, BillingSequence::query()->where('name', 'ticket')->value('current_value'));
    }

    public function test_sequential_assignments_produce_unique_numbers(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00'));

        $numbers = [];

        for ($i = 0; $i < 5; $i++) {
            $numbers[] = $this->numbers
                ->assignNumber(Ticket::factory()->create(['ticket_number' => null]))
                ->ticket_number;
        }

        $this->assertSame([
            'TK-2026-00001',
            'TK-2026-00002',
            'TK-2026-00003',
            'TK-2026-00004',
            'TK-2026-00005',
        ], $numbers);
        $this->assertCount(5, array_unique($numbers));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
