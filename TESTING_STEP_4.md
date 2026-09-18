# Stage 4 — Promo Codes for Steam Top Up

## Overview

На 4-м этапе добавлена поддержка промокодов для Steam Top Up.

Промокод обрабатывается полностью на backend: сервер проверяет его валидность, рассчитывает скидку, проверяет лимит использований и сохраняет результат в заказе.

Steam Top Up использует существующий lifecycle заказов, но создаётся как отдельный тип заказа:

* `sku = STEAM-TOPUP`
* `product_id = null`
* итоговая сумма хранится в `orders.amount`
* применённый промокод сохраняется в `orders.promo_code`
* размер скидки сохраняется в `orders.discount_amount`

После успешной симуляции оплаты Steam Top Up получает статус `paid`. Выдача ключа для такого заказа не запускается.

---

## Promo Codes

В seed добавлены следующие промокоды:

| Code        | Type    | Value | Currency | Max uses |
| ----------- | ------- | ----: | -------- | -------: |
| `WELCOME10` | percent |   10% | —        |      100 |
| `GG500`     | amount  |   500 | RUB      |       20 |
| `LIMIT3`    | percent |   25% | —        |        3 |
| `ONCEONLY`  | percent |   50% | —        |        1 |

Для процентной скидки:

```text
discount = amount * percent / 100
```

Для фиксированной скидки:

```text
discount = min(amount, promo value)
```

Итоговая сумма не может стать отрицательной.

---

## API

### Create Steam Top Up

```http
POST /api/steam-topups
Content-Type: application/json
Accept: application/json
```

Request:

```json
{
  "amount": 1000,
  "currency": "RUB",
  "promo_code": "LIMIT3"
}
```

Successful response:

```json
{
  "data": {
    "id": "order-public-id",
    "status": "created",
    "sku": "STEAM-TOPUP",
    "amount": 750,
    "currency": "RUB",
    "promo_code": "LIMIT3",
    "discount_amount": 250
  }
}
```

Сумма скидки и итоговая стоимость рассчитываются сервером. Frontend не передаёт рассчитанную скидку в API.

---

## Validation

Backend отклоняет:

* неизвестный промокод;
* неактивный промокод;
* промокод, у которого закончился лимит;
* фиксированный промокод для неподходящей валюты;
* некорректную сумму;
* неподдерживаемую валюту.

Например, если `LIMIT3` уже использован 3 раза:

```http
422 Unprocessable Entity
```

```json
{
  "errors": {
    "promo_code": [
      "Promo code usage limit has been reached."
    ]
  }
}
```

---

## Concurrency

Лимит промокода защищён от race condition.

При создании Steam Top Up промокод блокируется внутри транзакции:

```php
PromoCode::query()
    ->where('code', $normalizedPromoCode)
    ->where('is_active', true)
    ->lockForUpdate()
    ->first();
```

После проверки лимита `used_count` увеличивается в той же транзакции, в которой создаётся заказ.

Таким образом, несколько одновременных запросов не могут использовать один и тот же оставшийся слот промокода.

---

## Reproducing the concurrency test

Для проверки используется готовый скрипт:

```text
backend/tests/concurrency/steam_topup_promo.php
```

Сначала сбросить тестовую базу и seed:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Затем запустить 10 одновременных HTTP-запросов:

```bash
docker compose exec app php tests/concurrency/steam_topup_promo.php
```

Скрипт отправляет 10 параллельных запросов с:

```json
{
  "amount": 1000,
  "currency": "RUB",
  "promo_code": "LIMIT3"
}
```

У промокода `LIMIT3` установлен лимит:

```text
max_uses = 3
```

Ожидаемый результат:

```text
Successful: 3
Failed: 7
```

Успешные запросы получают:

```text
amount = 750 RUB
discount = 250 RUB
```

После выполнения:

```text
used_count = 3
```

Таким образом, при 10 одновременных запросах промокод не превышает установленный лимит.

---

## Frontend flow

Пользователь:

1. Открывает Steam Top Up.
2. Выбирает валюту.
3. Вводит сумму.
4. Открывает поле промокода.
5. Вводит промокод.
6. Нажимает «Оплатить».
7. Frontend создаёт Steam Top Up order через backend.
8. Пользователь попадает на страницу заказа.
9. Нажимает «Оплатить».
10. Используется существующая симуляция оплаты.
11. После успешной оплаты заказ получает статус `paid`.

Для Steam Top Up после `paid` frontend не ожидает выдачу ключа и останавливает polling.

---

## Tests

Промокоды покрыты feature-тестами:

* создание Steam Top Up без промокода;
* процентная скидка;
* фиксированная скидка;
* несовместимая валюта;
* неизвестный промокод;
* неактивный промокод;
* исчерпанный лимит;
* лимит в `1` использование;
* скидка не может сделать итоговую сумму отрицательной.

Запуск всех backend-тестов:

```bash
docker compose exec app php artisan test
```

---

## Main implementation

Основные изменения Stage 4:

```text
app/Models/PromoCode.php
app/Services/SteamTopUpService.php
app/Http/Requests/StoreSteamTopUpRequest.php
app/Http/Controllers/Api/SteamTopUpController.php
database/migrations/*_create_promo_codes_table.php
database/migrations/*_add_promo_fields_to_orders_table.php
database/seeders/PromoCodeSeeder.php
tests/Feature/SteamTopUpPromoTest.php
tests/concurrency/steam_topup_promo.php
```

Frontend:

```text
src/components/SteamTopUp.vue
src/api/orders.ts
src/pages/HomePage.vue
src/pages/OrderPage.vue
```

---

## Design notes

Промокоды не добавлялись непосредственно в `Product`, потому что Steam Top Up не является обычным catalog product.

Вместо этого используется отдельный `SteamTopUpService`, который отвечает за:

* создание Steam Top Up order;
* применение промокода;
* расчёт скидки;
* проверку лимита;
* атомарное увеличение `used_count`.

При этом существующая модель `Order` и lifecycle оплаты переиспользуются, поэтому Steam Top Up остаётся частью общей системы заказов.