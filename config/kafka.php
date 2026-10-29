<?php

return [
    'brokers' => env('KAFKA_BROKERS', 'redpanda:9092'),
    'topic' => env('KAFKA_TOPIC', 'payouts'),
    'group_id' => env('KAFKA_GROUP_ID', 'payout-consumer'),
];
