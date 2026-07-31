<?php

namespace Tests\Feature\Billing;

use Core\Billing\Enums\QuoteStatus;
use Core\Billing\Models\BillingSequence;
use Core\Billing\Models\Quote;
use Core\Billing\Services\QuoteNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class QuoteNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuoteNumberService $numberService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->numberService = app(QuoteNumberService::class);

        config([
            'corepanel.billing.quote_numbering.prefix' => 'QUO',
            'corepanel.billing.quote_numbering.padding' => 6,
            'corepanel.billing.quote_numbering.include_year' => true,
            'corepanel.billing.quote_numbering.reset_yearly' => true,
            'corepanel.billing.quote_numbering.separator' => '-',
        ]);
    }

    public function test_quote_number_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(QuoteNumberService::class),
            app(QuoteNumberService::class),
        );
    }

    public function test_assign_number_sets_formatted_number_on_draft_quote(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-18 10:00:00'));

        $quote = Quote::factory()->draft()->create();
        $numbered = $this->numberService->assignNumber($quote);

        $this->assertSame('QUO-2026-000001', $numbered->quote_number);
        $this->assertSame(QuoteStatus::Draft, $numbered->status);
        $this->assertSame(1, BillingSequence::query()->where('name', 'quote')->value('current_value'));
    }

    public function test_assign_number_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-18 10:00:00'));

        $quote = Quote::factory()->draft()->create();
        $first = $this->numberService->assignNumber($quote);
        $second = $this->numberService->assignNumber($first);

        $this->assertSame($first->quote_number, $second->quote_number);
        $this->assertSame(1, BillingSequence::query()->where('name', 'quote')->value('current_value'));
    }

    public function test_assign_number_rejects_non_draft_quote(): void
    {
        $quote = Quote::factory()->draft()->create();
        $quote->forceFill([
            'status' => QuoteStatus::Sent,
            'sent_at' => now(),
        ])->save();

        $this->expectException(InvalidArgumentException::class);

        $this->numberService->assignNumber($quote->fresh() ?? $quote);
    }

    public function test_preview_next_does_not_consume_sequence(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-18 10:00:00'));

        $preview = $this->numberService->previewNext();
        $this->assertSame('QUO-2026-000001', $preview);

        $this->numberService->assignNumber(Quote::factory()->draft()->create());
        $this->assertSame('QUO-2026-000002', $this->numberService->previewNext());
    }
}
