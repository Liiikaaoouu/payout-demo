<?php

namespace App\Console\Commands;

use App\Models\Payout;
use App\Services\PayoutStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Message;

class ConsumePayoutEvents extends Command
{
    protected $signature = 'kafka:consume-payouts';

    protected $description = 'Читает события payouts из Kafka и двигает статусы';

    public function handle(PayoutStateMachine $machine): int
    {
        $conf = new Conf();
        $conf->set('group.id', config('kafka.group_id'));
        $conf->set('metadata.broker.list', config('kafka.brokers'));
        $conf->set('auto.offset.reset', 'earliest');

        $consumer = new KafkaConsumer($conf);
        $consumer->subscribe([config('kafka.topic')]);

        $this->info('Слушаю топик '.config('kafka.topic'));

        while (true) {
            $message = $consumer->consume(1000);
            $this->handleMessage($message, $machine);
        }
    }

    private function handleMessage(Message $message, PayoutStateMachine $machine): void
    {
        if ($message->err === RD_KAFKA_RESP_ERR__TIMED_OUT) {
            return;
        }

        if ($message->err) {
            $this->error('Kafka: '.$message->errstr());

            return;
        }

        $event = json_decode($message->payload, true);

        if (! is_array($event) || ! isset($event['event_id'], $event['payout_id'])) {
            $this->warn('Некорректное событие, пропускаю');

            return;
        }

        // Идемпотентность: at-least-once означает возможные дубли.
        // Уникальный индекс + insertOrIgnore — обрабатываем событие ровно один раз.
        $inserted = DB::table('processed_events')->insertOrIgnore([
            'event_id' => $event['event_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            $this->line("Дубль {$event['event_id']}, пропускаю");

            return;
        }

        $payout = Payout::find($event['payout_id']);

        if (! $payout) {
            $this->warn("Выплата {$event['payout_id']} не найдена");

            return;
        }

        $machine->apply($payout, Payout::STATUS_PROCESSING);

        $this->info("Выплата {$payout->id} -> processing");
    }
}
