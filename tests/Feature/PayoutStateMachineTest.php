<?php

namespace Tests\Feature;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Payout;
use App\Services\PayoutStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutStateMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_can_transition_to_processing(): void
    {
        $payout = Payout::create(['amount' => 1000, 'status' => Payout::STATUS_CREATED]);

        $machine = app(PayoutStateMachine::class);
        $machine->apply($payout, Payout::STATUS_PROCESSING);

        $this->assertSame(Payout::STATUS_PROCESSING, $payout->fresh()->status);
    }

    public function test_processing_can_transition_to_completed(): void
    {
        $payout = Payout::create(['amount' => 1000, 'status' => Payout::STATUS_PROCESSING]);

        $machine = app(PayoutStateMachine::class);
        $machine->apply($payout, Payout::STATUS_COMPLETED);

        $this->assertSame(Payout::STATUS_COMPLETED, $payout->fresh()->status);
    }

    public function test_processing_can_transition_to_failed(): void
    {
        $payout = Payout::create(['amount' => 1000, 'status' => Payout::STATUS_PROCESSING]);

        $machine = app(PayoutStateMachine::class);
        $machine->apply($payout, Payout::STATUS_FAILED);

        $this->assertSame(Payout::STATUS_FAILED, $payout->fresh()->status);
    }

    public function test_invalid_transition_throws_exception(): void
    {
        $payout = Payout::create(['amount' => 1000, 'status' => Payout::STATUS_CREATED]);

        $machine = app(PayoutStateMachine::class);

        $this->expectException(InvalidStatusTransitionException::class);
        $machine->apply($payout, Payout::STATUS_COMPLETED);
    }

    public function test_completed_payout_cannot_move_backwards(): void
    {
        $payout = Payout::create(['amount' => 1000, 'status' => Payout::STATUS_COMPLETED]);

        $machine = app(PayoutStateMachine::class);

        $this->expectException(InvalidStatusTransitionException::class);
        $machine->apply($payout, Payout::STATUS_PROCESSING);
    }
}
