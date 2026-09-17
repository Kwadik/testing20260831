# Gamer Shop

Цифровой магазин игр на Laravel 13, PostgreSQL, Redis, Docker и Vue 3.

Проект реализует автоматическую выдачу цифровых ключей после успешного webhook платежа, идемпотентную обработку платежей, защиту от конкурентных запросов, идемпотентность выдачи и восстановление после ошибок доставки.

Frontend реализован на Vue 3 + TypeScript + Vite и содержит основную страницу магазина по макету.

## Стек

### Backend

* PHP 8.4
* Laravel 13
* PostgreSQL 17
* Redis 7
* Docker Compose
* PHPUnit / Laravel Testing

### Frontend

* Vue 3
* TypeScript
* Vite
* SCSS

## Архитектура

Проект состоит из следующих Docker-сервисов:

* `app` — Laravel HTTP-приложение;
* `queue` — worker очереди Laravel;
* `postgres` — PostgreSQL;
* `redis` — очередь и cache.

Frontend находится в отдельной директории `frontend/` и запускается через Vite.

HTTP-приложение Laravel доступно на порту `8000`.

Frontend dev server по умолчанию доступен на порту `5173`.

## Структура проекта

```text
/
├── backend/
│   ├── app/
│   ├── database/
│   ├── routes/
│   └── tests/
│
├── frontend/
│   ├── src/
│   │   ├── api/
│   │   ├── components/
│   │   ├── data/
│   │   │   └── catalog.ts
│   │   ├── pages/
│   │   ├── styles/
│   │   ├── types/
│   │   ├── App.vue
│   │   └── main.ts
│   ├── public/
│   ├── package.json
│   └── vite.config.ts
│
├── compose.yaml
└── README.md
```

## Требования

Необходимы:

* Docker;
* Docker Compose;
* Node.js и npm для разработки frontend.

Локальная установка PHP, Composer, PostgreSQL и Redis не требуется.

## Запуск backend

Из корня репозитория:

```bash
docker compose up -d --build
```

Проверить состояние контейнеров:

```bash
docker compose ps
```

Laravel-приложение будет доступно по адресу:

```text
http://localhost:8000
```

Worker очереди запускается автоматически в контейнере `queue`.

## Запуск frontend

Перейти в директорию frontend:

```bash
cd frontend
```

Установить зависимости:

```bash
npm install
```

Запустить dev server:

```bash
npm run dev
```

Frontend будет доступен по адресу:

```text
http://localhost:5173
```

Production build:

```bash
npm run build
```

На текущем этапе production build frontend успешно собирается.

## Frontend

Основная задача frontend на текущем этапе — реализовать главную страницу магазина по предоставленному Figma-макету без pixel-perfect копирования.

В рамках текущего этапа реализованы:

* Header;
* Catalog dropdown;
* Hero/Banner;
* Services;
* Steam Top Up;
* Popular Products;
* Product Card;
* базовая дизайн-система на SCSS;
* типизированная модель товара на TypeScript.

Отзывы и footer не требуются по текущему ТЗ и не реализуются.

Mobile-версия, dark theme, полноценная авторизация, полноценный каталог, admin panel и реальный acquiring также не входят в текущий этап.

## Интерактивность frontend

Реализованы все пять интерактивных точек, предусмотренных ТЗ.

### Hero carousel

Поддерживает:

* переключение стрелками;
* переключение по dots;
* автоматическое переключение слайдов;
* несколько слайдов.

### Catalog dropdown

Поддерживает:

* открытие по кнопке `Каталог`;
* закрытие повторным нажатием;
* закрытие при клике вне меню;
* отображение структуры категорий согласно макету.

### Currency switch

В блоке Steam Top Up реализован переключатель:

```text
$ / ₸ / ₽
```

Переключатель меняет только активное состояние.

Пересчёт стоимости между валютами по ТЗ не выполняется.

Базовая цена товара хранится в RUB.

### Service hover

Для сервисов реализирован hover-state с визуальным изменением элемента.

### Product card hover

Карточки товаров имеют hover-state с плавным визуальным изменением, включая подъём и тень.

## Каталог товаров

На текущем frontend-этапе товары хранятся локально в:

```text
frontend/src/data/catalog.ts
```

Источник данных соответствует каталогу из ТЗ и существующему backend seeder.

Тип товара:

```ts
type ProductType =
    | 'topup'
    | 'key'
    | 'subscription'
    | 'giftcard'
```

Валюта:

```ts
type ProductCurrency = 'RUB'
```

Модель товара:

```ts
interface Product {
    sku: string
    name: string
    type: ProductType
    price: number
    currency: ProductCurrency
    image?: string
}
```

`image` является необязательным.

Если изображение товара отсутствует, `ProductCard` использует дефолтное изображение.

Это позволяет сейчас использовать повторяющееся изображение из макета, а в дальнейшем получать реальные изображения с backend без изменения интерфейса карточки.

Текущий каталог содержит следующие товары:

```text
STEAM-TOPUP-500
STEAM-TOPUP-1000
STEAM-TOPUP-2500

KEY-CS2-PRIME
KEY-GTA5
KEY-EFT

SUB-DISCORD-1M
SUB-YT-3M
SUB-SPOTIFY-1M

GIFT-PSN-1000
GIFT-XBOX-1500
GIFT-ROBLOX-800
```

Frontend-компоненты не завязаны на конкретный товар: `ProductSection` передаёт типизированный `Product` в `ProductCard`.

В дальнейшем локальный `catalog.ts` может быть заменён на получение каталога через API.

## SCSS и дизайн-система

Frontend использует SCSS.

Общие стили и design tokens находятся в:

```text
frontend/src/styles/
```

Основные файлы:

```text
_variables.scss
_reset.scss
_base.scss
_typography.scss
_mixins.scss
main.scss
```

В variables вынесены:

* цвета;
* типографика;
* spacing;
* border radius;
* shadows;
* layout;
* transitions.

Компоненты используют локальные:

```vue
<style scoped lang="scss">
```

Общие UI-правила не дублируются между компонентами без необходимости.

Подход к стилизации:

```text
Figma
  ↓
design tokens
  ↓
SCSS variables / mixins
  ↓
UI/component styles
  ↓
page styles
```

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

### Backend

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

### Frontend

```bash
cd frontend
npm install
npm run dev
```

Production build:

```bash
npm run build
```

## Результаты проверки

### Backend

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

### Frontend

Проверено:

```bash
npm install
npm run build
```

Установка зависимостей проходит успешно.

Production build проходит успешно.

Основная страница frontend открывается через Vite dev server.

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
| Frontend главной страницы  | Реализован                                   |
| Product catalog            | Реализован локальный типизированный каталог  |
| Product cards              | Динамический вывод                           |
| Hero carousel              | Реализован                                   |
| Catalog dropdown           | Реализован                                   |
| Currency switch            | Реализован                                   |
| Service hover              | Реализован                                   |
| Product card hover         | Реализован                                   |

## Текущее состояние проекта

На текущем этапе завершена верстка и базовая интерактивность главной страницы frontend.

Реализованы все интерактивные точки из ТЗ.

Каталог товаров пока используется локально из `frontend/src/data/catalog.ts`.

Backend API создания заказа уже реализован и покрыт тестами.

### Следующий этап

Следующий этап — связать frontend с существующим backend API и реализовать минимальный purchase flow для одного товара:

```text
ProductCard
    ↓
Купить
    ↓
POST /api/orders
    ↓
Order created
    ↓
Оплатить
    ↓
Payment webhook stub
    ↓
Order paid
    ↓
Automatic delivery
    ↓
Order delivered
    ↓
Order status page
```

Реальный acquiring/payment provider на frontend не реализуется.

Первый этап purchase flow должен работать хотя бы для одного товара.

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

Для frontend:

```bash
cd frontend
npm install
npm run build
```

Ожидаемый результат backend текущей версии:

```text
75 passed
438 assertions
```

Ожидаемый результат frontend:

```text
npm run build
```

завершается успешно.

## Ссылка на репозиторий

https://github.com/Kwadik/testing20260831

## Live-приложение

```text
TODO: добавить URL работающего приложения, если доступно
```

## Фактическое время разработки

```text
TODO: указать фактическое затраченное время
```

