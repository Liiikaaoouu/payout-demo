<?php

namespace App\Services;

use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Payout;

/**
 * Разрешает только явные переходы статусов выплаты.
 * created -> processing -> completed | failed
 */
class PayoutStateMachine
{
    private const TRANSITIONS = [
        Payout::STATUS_CREATED => [Payout::STATUS_PROCESSING],
        Payout::STATUS_PROCESSING => [Payout::STATUS_COMPLETED, Payout::STATUS_FAILED],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function apply(Payout $payout, string $to): Payout
    {
        if (! $this->canTransition($payout->status, $to)) {
            throw new InvalidStatusTransitionException(
                "Недопустимый переход: {$payout->status} -> {$to}"
            );
        }

        $payout->status = $to;
        $payout->save();

        return $payout;
    }
}
