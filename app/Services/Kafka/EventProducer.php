<?php

namespace App\Services\Kafka;

use RdKafka\Conf;
use RdKafka\Producer;

class EventProducer
{
    private ?Producer $producer = null;

    /**
     * Ленивое подключение: конструктор не трогает Kafka,
     * соединение создаётся только при первой публикации.
     */
    private function producer(): Producer
    {
        if ($this->producer === null) {
            $conf = new Conf();
            $conf->set('metadata.broker.list', config('kafka.brokers'));

            $this->producer = new Producer($conf);
            $this->producer->addBrokers(config('kafka.brokers'));
        }

        return $this->producer;
    }

    /**
     * $key — ключ партиционирования. Сообщения с одинаковым ключом
     * попадают в одну партицию, поэтому порядок событий по одному
     * объекту (например, payout_id) сохраняется.
     */
    public function publish(string $topic, array $payload, ?string $payout_id = null): void
    {
        $producer = $this->producer();

        // RD_KAFKA_PARTITION_UA = «выбери партицию сам» по ключу/круговому
        $producer->newTopic($topic)->produce(
            RD_KAFKA_PARTITION_UA,
            0,
            json_encode($payload),
            $payout_id
        );

        // poll отправляет накопленные сообщения, flush гарантирует доставку
        $producer->poll(0);
        $producer->flush(10000);
    }
}
