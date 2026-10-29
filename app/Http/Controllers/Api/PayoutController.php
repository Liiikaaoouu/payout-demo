<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Services\Kafka\EventProducer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PayoutController extends Controller
{
    public function store(Request $request, EventProducer $producer): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $payout = Payout::create([
            'amount' => $validated['amount'],
            'status' => Payout::STATUS_CREATED,
        ]);

        // Ключ = payout_id: все события одной выплаты идут в одну партицию,
        // значит порядок между ними гарантирован.
        $producer->publish(
            config('kafka.topic'),
            [
                'event_id' => (string) Str::uuid(),
                'event' => 'payout.created',
                'payout_id' => $payout->id,
                'occurred_at' => now()->toIso8601String(),
            ],
            (string) $payout->id
        );

        return response()->json([
            'status' => 'created',
            'payout' => $payout,
        ], 201);
    }
}
