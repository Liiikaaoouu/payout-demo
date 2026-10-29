<?php

namespace Tests\Feature;

use App\Models\Payout;
use App\Services\Kafka\EventProducer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PayoutCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_payout_can_be_created_and_event_published_with_payout_id_key(): void
    {
        // Продюсер замокан: тест не требует живого Kafka.
        // Проверяем, что ключом сообщения передан payout_id (ради порядка в партиции).
        $this->mock(EventProducer::class, function ($mock) {
            $mock->shouldReceive('publish')
                ->once()
                ->withArgs(function ($topic, $payload, $key) {
                    return $key === (string) $payload['payout_id'];
                });
        });

        $this->postJson('/api/payouts', ['amount' => 5000])
            ->assertCreated()
            ->assertJsonPath('payout.amount', 5000)
            ->assertJsonPath('payout.status', Payout::STATUS_CREATED);

        $this->assertDatabaseHas('payouts', ['amount' => 5000]);
    }

    public function test_amount_is_required(): void
    {
        $this->postJson('/api/payouts', [])
            ->assertStatus(422);
    }
}
