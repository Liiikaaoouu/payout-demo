# payout-demo

Учебный сервис выплат на Laravel 12. Демонстрирует паттерны, которые спрашивают на собеседованиях: state machine, идемпотентность вебхуков, Docker, PostgreSQL и Kafka.

## Что внутри

- **State machine** — выплата проходит только разрешённые переходы: `created → processing → completed | failed`. Невалидный переход бросает исключение.
- **Идемпотентность вебхуков** — уникальный индекс `(gateway, event_id)` + атомарная вставка `insertOrIgnore`: повторный вебхук не проводит операцию дважды.
- **Kafka** — создание выплаты публикует событие в топик `payouts`; консьюмер идемпотентно его обрабатывает и двигает статус. Ключ сообщения — `payout_id`, поэтому события одной выплаты идут в одну партицию и порядок сохраняется.
- **Docker** — `app` (php-fpm) + `nginx` + `postgres:16` + `redpanda` (Kafka).
- **Тесты** — 11 тестов: переходы статусов, идемпотентность вебхуков, создание выплаты.

## Стек

PHP 8.3 · Laravel 12 · PostgreSQL 16 · Kafka (Redpanda) · Docker · PHPUnit

## Запуск тестов (без Docker)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan test
```

Тесты идут на SQLite in-memory (см. `phpunit.xml`), реальная база не нужна.

## Запуск в Docker

```bash
docker compose up -d --build
docker compose exec app php artisan migrate
```

| Сервис | Адрес |
|---|---|
| Приложение | http://localhost:8080 |
| PostgreSQL | localhost:5432 (`payout` / `payout` / `payout`) |
| Kafka (Redpanda) | localhost:19092 |

## Полный цикл Kafka

```bash
# 1. Создать выплату — событие уйдёт в Kafka
curl -X POST http://localhost:8080/api/payouts \
  -H "Content-Type: application/json" \
  -d '{"amount":5000}'

# 2. Запустить консьюмер (в отдельном терминале)
docker compose exec app php artisan kafka:consume-payouts
```

Статус выплаты изменится `created → processing`, а в `processed_events` появится запись.

## API

| Метод | Путь | Описание |
|---|---|---|
| POST | `/api/payouts` | Создать выплату и опубликовать `payout.created` |
| POST | `/api/webhooks/bank` | Вебхук банка (идемпотентный) |

## Как это устроено

```
POST /api/payouts
  → Payout (status=created)
  → EventProducer публикует payout.created (ключ = payout_id) в топик payouts
      → Redpanda (Kafka)
          → ConsumePayoutEvents читает событие
              → insertOrIgnore в processed_events (защита от дублей)
                  → статус created → processing
```

## Структура

```
app/
├── Models/Payout.php                        модель выплаты и статусы
├── Services/
│   ├── PayoutStateMachine.php               разрешённые переходы статусов
│   └── Kafka/EventProducer.php              публикация событий (ленивое подключение)
├── Console/Commands/
│   └── ConsumePayoutEvents.php              консьюмер, идемпотентный
└── Http/Controllers/Api/
    ├── PayoutController.php                 создание выплаты + событие
    └── WebhookController.php                вебхук с защитой от дублей

database/migrations/                         payouts, webhook_events, processed_events
tests/Feature/                               PayoutStateMachineTest, WebhookIdempotencyTest, PayoutCreationTest
docker-compose.yml                           app, nginx, postgres, redpanda
```

## CI

GitHub Actions (`.github/workflows/tests.yml`) запускает `php artisan test` на каждый push и pull request.
