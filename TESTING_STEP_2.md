## Этап 2. Однократная выдача под гонками

Выдача ключа защищена от повторных и параллельных запросов:

* повторный webhook с тем же `event_id` обрабатывается идемпотентно;
* два одновременных webhook по одному заказу не создают две выдачи;
* повторная доставка с тем же `Idempotency-Key` возвращает тот же результат;
* разные `Idempotency-Key` для одного заказа также не приводят к повторной выдаче;
* два разных заказа не могут одновременно получить один и тот же inventory item;
* при конкурентной обработке 50 paid webhook одному заказу выдаётся ровно один ключ.

### Тесты

Проверка выполняется командой:

```bash
docker compose exec app php artisan test
```

Полный результат:

```text
Tests:    80 passed (450 assertions)
```

Ключевые тесты:

```text
Tests\Feature\Api\PaymentWebhookConcurrencyTest
✓ same pending webhook is idempotent under concurrency
✓ fifty paid webhooks deliver one key under concurrency

Api\OrderDeliveryConcurrencyTest
✓ same idempotency key is safe under concurrency
✓ failed attempt is safe under concurrent retry
✓ different idempotency keys are safe for same order under concurrency
✓ different orders are safe when competing for last inventory item
✓ same idempotency key cannot be used for different orders under concurrency
```

### Воспроизведение параллельных запросов

Конкурентные сценарии воспроизводятся непосредственно PHPUnit-тестами. Для запуска только проверки webhook:

```bash
docker compose exec app php artisan test \
  tests/Feature/Api/PaymentWebhookConcurrencyTest.php
```

Для проверки конкурентной выдачи:

```bash
docker compose exec app php artisan test \
  tests/Feature/Api/OrderDeliveryConcurrencyTest.php
```

Для полного прогона:

```bash
docker compose exec app php artisan test
```

Тесты запускают несколько HTTP-запросов одновременно к одному заказу и проверяют не только HTTP-ответы, но и состояние БД: количество delivery attempts, состояние inventory item и итоговый выданный ключ.

