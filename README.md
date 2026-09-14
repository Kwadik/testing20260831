# Gamer Shop

Цифровой магазин игр на Laravel 13, PostgreSQL, Redis и Docker.

Проект реализует автоматическую выдачу цифровых ключей после успешного webhook платежа, идемпотентную обработку платежей, защиту от конкурентных запросов, идемпотентность выдачи и восстановление после ошибок доставки.

## Стек

* PHP 8.4
* Laravel 13
* PostgreSQL 17
* Redis 7
* Docker Compose
* PHPUnit / Laravel Testing

## Архитектура

Проект состоит из следующих Docker-сервисов:

* `app` — Laravel HTTP-приложение;
* `queue` — worker очереди Laravel;
* `postgres` — PostgreSQL;
* `redis` — очередь и cache.

HTTP-приложение доступно на порту `8000`.

## Требования

Необходимы:

* Docker;
* Docker Compose.

Локальная установка PHP, Composer, PostgreSQL и Redis не требуется.

## Запуск проекта

Из корня репозитория:

```bash
docker compose up -d --build
```

Проверить состояние контейнеров:

```bash
docker compose ps
```

Приложение будет доступно по адресу:

```text
http://localhost:8000
```

Worker очереди запускается автоматически в контейнере `queue`.

## База данных

Laravel-приложение использует:

```text
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=gamer_shop_testing
DB_USERNAME=gamer_shop
```

PostgreSQL-контейнер инициализируется с базой:

```text
gamer_shop
```

Миграции Laravel:

```bash
docker compose exec app php artisan migrate
```

## Тесты

Запустить весь набор тестов:

```bash
docker compose exec app php artisan test
```

Последний проверенный результат:

```text
75 passed
438 assertions
Duration: 71.68s
```

Тестами покрываются:

* создание заказа;
* payment webhook;
* повторные и конкурентные webhook;
* обработка очереди;
* идемпотентность выдачи;
* конкурентные попытки выдачи;
* конкуренция нескольких заказов за последний ключ;
* Provider A / Provider B;
* конкурентная работа Provider A;
* восстановление после `out_of_stock`;
* восстановление после `delivery_failed`;
* идемпотентность ручного восстановления;
* повторное восстановление уже доставленного заказа.

## Основные API endpoints

### Создание заказа

```http
POST /api/orders
```

Создаёт заказ на активный товар.

### Payment webhook

```http
POST /api/payment/webhook
```

Обрабатывает событие оплаты.

Webhook рассчитан на модель **at-least-once delivery**:

* одинаковый `event_id` обрабатывается только один раз;
* повторная доставка одного события идемпотентна;
* payment event может прийти до создания заказа;
* такой event может быть привязан к заказу позже;
* конкурентные webhook для одного заказа сериализуются блокировкой строки заказа.

### Прямая выдача

```http
POST /api/orders/{publicId}/deliver
```

Требуется заголовок:

```http
Idempotency-Key: <unique-key>
```

`Idempotency-Key` идентифицирует попытку выдачи.

Один ключ идемпотентности нельзя использовать для другого заказа.

### Восстановление выдачи

```http
POST /api/orders/{publicId}/retry-delivery
```

Требуется:

```http
Idempotency-Key: <unique-key>
```

Endpoint предназначен для восстановления заказов в состояниях:

* `out_of_stock`;
* `delivery_failed`.

После пополнения склада или устранения проблемы доставки заказ можно безопасно повторить.

Если заказ уже находится в состоянии `delivered`, существующий выданный ключ возвращается без создания новой попытки выдачи.

## Exactly-once delivery

Защита от повторной выдачи построена на комбинации:

* уникальных ограничений базы данных;
* блокировок строк PostgreSQL;
* уникального `request_id`;
* транзакций.

Для одного заказа конкурентные попытки выдачи не могут привести к выдаче одного inventory item двум заказам.

Назначение inventory item и перевод заказа в `delivered` выполняются в одной транзакции базы данных.

Для платежей используется идемпотентность по `event_id` и блокировка строки заказа. Поэтому конкурентные и повторные paid webhook приводят только к одной фактической выдаче.

## Защита от race conditions

В проекте есть отдельные тесты конкурентных сценариев. Проверяется не только последовательное выполнение запросов, но и реальные параллельные операции.

### 50 параллельных payment webhook

Ключевой acceptance-сценарий:

```text
50 параллельных paid webhook
            ↓
        один заказ
            ↓
      одна выдача
            ↓
    один использованный ключ
```

Запустить:

```bash
docker compose exec app php artisan test \
  --filter=PaymentWebhookConcurrencyTest
```

Тест проверяет, что 50 конкурентных paid webhook приводят ровно к одной выдаче цифрового ключа.

### Конкурентная выдача

Запустить:

```bash
docker compose exec app php artisan test \
  --filter=OrderDeliveryConcurrencyTest
```

Проверяются:

* одинаковый `Idempotency-Key`;
* разные `Idempotency-Key` для одного заказа;
* конкуренция за последний inventory item;
* использование одного `Idempotency-Key` разными заказами.

### Конкурентный Provider A

Запустить:

```bash
docker compose exec app php artisan test \
  --filter=ProviderAConcurrencyTest
```

Проверяется идемпотентность Provider A при конкурентных запросах.

## Восстановление после ошибки доставки

### Out of stock

Если оплата успешна, но доступного цифрового ключа нет:

```text
PAID
  ↓
DELIVERING
  ↓
OUT_OF_STOCK
```

Заказ не теряется, а неуспешная попытка выдачи сохраняется в базе.

После пополнения inventory:

```text
OUT_OF_STOCK
      ↓
retry-delivery
      ↓
PAID
      ↓
DELIVERING
      ↓
DELIVERED
```

Проверка:

```bash
docker compose exec app php artisan test \
  --filter=test_out_of_stock_order_can_be_retried_after_inventory_is_restocked
```

### Delivery failed

При ошибке доставки заказ переводится в:

```text
DELIVERY_FAILED
```

После устранения причины можно использовать тот же endpoint:

```http
POST /api/orders/{publicId}/retry-delivery
```

Повторная попытка не создаёт вторую выдачу одного и того же ключа.

## Идемпотентность выдачи

Каждая попытка выдачи имеет `request_id`.

Один и тот же `request_id`:

* нельзя использовать для другого заказа;
* можно безопасно повторить;
* после успешной выдачи возвращает существующий результат;
* не расходует дополнительный inventory item.

Recovery также идемпотентен.

Повторный запрос с тем же `Idempotency-Key` возвращает тот же цифровой ключ, не создавая дополнительную попытку.

Отдельно проверяется случай, когда recovery вызывается повторно уже после `DELIVERED`: новая попытка выдачи не создаётся.

## Provider A и Provider B

В проекте реализованы два stub-провайдера:

* Provider A;
* Provider B.

Проверяются сценарии:

* успешный запрос;
* ошибка провайдера;
* timeout;
* повторный запрос с тем же `request_id`.

Одинаковый `request_id` должен приводить к одному и тому же логическому результату.

Timeout рассматривается отдельно от подтверждённой ошибки, поскольку после timeout конечное состояние внешнего провайдера может быть неизвестно.

## Статусы заказа

Используются следующие состояния:

```text
created
paid
delivering
delivered
payment_failed
out_of_stock
delivery_failed
```

Восстанавливаемыми состояниями доставки являются:

```text
out_of_stock
delivery_failed
```

## Структура тестов

Основные Feature/Service тесты находятся в:

```text
backend/tests/Feature/
```

Ключевые тесты:

```text
Api/CreateOrderTest.php
Api/OrderDeliveryConcurrencyTest.php
Api/OrderDeliveryControllerTest.php
Api/OrderDeliveryTest.php
Api/PaymentWebhookConcurrencyTest.php
Api/PaymentWebhookQueueTest.php
Api/PaymentWebhookTest.php

Services/DeliveryOrchestratorTest.php
Services/DeliveryServiceTest.php
Services/ProviderAConcurrencyTest.php
Services/ProviderATest.php
Services/ProviderBTest.php
```

## Полезные команды

Запустить проект:

```bash
docker compose up -d --build
```

Остановить проект:

```bash
docker compose down
```

Запустить все тесты:

```bash
docker compose exec app php artisan test
```

Запустить тесты выдачи и recovery:

```bash
docker compose exec app php artisan test \
  --filter=OrderDeliveryControllerTest
```

Запустить тесты конкурентных payment webhook:

```bash
docker compose exec app php artisan test \
  --filter=PaymentWebhookConcurrencyTest
```

Открыть shell приложения:

```bash
docker compose exec app bash
```

Логи Laravel-приложения:

```bash
docker compose logs -f app
```

Логи queue worker:

```bash
docker compose logs -f queue
```

## Результаты проверки

Последний запуск тестов контроллера и recovery:

```text
Tests:    16 passed (89 assertions)
Duration: 25.52s
```

Последний полный запуск:

```text
Tests:    75 passed (438 assertions)
Duration: 71.68s
```

Дополнительно выполнена проверка:

```bash
git diff --check
```

Ошибок whitespace не обнаружено.

Git выводит только предупреждения о нормализации CRLF/LF для изменённых файлов.

## Соответствие основным требованиям

| Требование                 | Реализация                                   |
| -------------------------- | -------------------------------------------- |
| Создание заказа            | Реализовано                                  |
| Payment webhook            | Реализовано                                  |
| Повторный webhook          | Идемпотентно                                 |
| Webhook до создания заказа | Поддерживается                               |
| 50 конкурентных webhook    | Покрыто тестом                               |
| Одна выдача одного ключа   | Защищено транзакциями и блокировками         |
| Idempotency-Key            | Реализован                                   |
| Конкурентная выдача        | Покрыта тестами                              |
| Out of stock               | Recoverable state                            |
| Delivery failed            | Recoverable state                            |
| Ручной recovery            | `POST /api/orders/{publicId}/retry-delivery` |
| Повторный recovery         | Идемпотентен                                 |
| Recovery после `DELIVERED` | Не создаёт новую выдачу                      |
| Provider A/B               | Реализованы                                  |
| Provider timeout           | Обрабатывается отдельно                      |
| Queue                      | Redis + Laravel queue worker                 |

## Ключевой принцип exactly-once

**Payment:** `event_id` + блокировка заказа + транзакция гарантируют, что повторные и конкурентные webhook не создают повторную выдачу.

**Delivery:** уникальный `request_id` + блокировка заказа + транзакционное резервирование inventory не позволяют одному цифровому ключу быть выданным двум заказам.

## Что проверить перед сдачей

Перед финальной сдачей рекомендуется выполнить:

```bash
docker compose up -d --build
docker compose exec app php artisan migrate
docker compose exec app php artisan test
```

Ожидаемый результат текущей версии:

```text
75 passed
438 assertions
```

## Ссылка на репозиторий

```text
TODO: добавить URL репозитория
```

## Live-приложение

```text
TODO: добавить URL работающего приложения, если доступно
```

## Фактическое время разработки

```text
TODO: указать фактическое затраченное время
```
