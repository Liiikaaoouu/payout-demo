<?php

namespace Tests\Feature;

use App\Models\Payout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_webhook_does_not_change_status_twice(): void
    {
        $payout = Payout::create(['amount' => 1000, 'status' => Payout::STATUS_CREATED]);

        $payload = [
            'gateway' => 'bank',
            'event_id' => 'evt_123',
            'payout_id' => $payout->id,
            'status' => Payout::STATUS_PROCESSING,
        ];

        // Первый вебхук обрабатывается.
        $this->postJson('/api/webhooks/bank', $payload)
            ->assertOk()
            ->assertJson(['status' => 'processed']);

        // Повторный вебхук с тем же event_id — дубль, статус не меняется повторно.
        $this->postJson('/api/webhooks/bank', $payload)
            ->assertOk()
            ->assertJson(['status' => 'duplicate']);

        $this->assertSame(Payout::STATUS_PROCESSING, $payout->fresh()->status);
        $this->assertDatabaseCount('webhook_events', 1);
    }

    public function test_webhook_with_new_event_id_is_processed(): void
    {
        $payout = Payout::create(['amount' => 1000, 'status' => Payout::STATUS_PROCESSING]);

        $this->postJson('/api/webhooks/bank', [
            'gateway' => 'bank',
            'event_id' => 'evt_456',
            'payout_id' => $payout->id,
            'status' => Payout::STATUS_COMPLETED,
        ])->assertOk()->assertJson(['status' => 'processed']);

        $this->assertSame(Payout::STATUS_COMPLETED, $payout->fresh()->status);
    }
}
