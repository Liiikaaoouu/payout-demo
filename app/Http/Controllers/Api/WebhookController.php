<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Services\PayoutStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    public function store(Request $request, PayoutStateMachine $machine): JsonResponse
    {
        $validated = $request->validate([
            'gateway' => ['required', 'string'],
            'event_id' => ['required', 'string'],
            'payout_id' => ['required', 'integer'],
            'status' => ['required', 'string'],
        ]);

        // Идемпотентность: запись события с уникальным индексом.
        // insertOrIgnore вернёт 0, если (gateway, event_id) уже существует.
        $inserted = DB::table('webhook_events')->insertOrIgnore([
            'gateway' => $validated['gateway'],
            'event_id' => $validated['event_id'],
            'payload' => json_encode($validated),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            return response()->json([
                'status' => 'duplicate',
                'message' => 'Событие уже обработано',
            ]);
        }

        $payout = Payout::find($validated['payout_id']);

        if (! $payout) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $machine->apply($payout, $validated['status']);

        return response()->json(['status' => 'processed']);
    }
}
