# Invoice service

Небольшой Symfony 7.4 сервис для технического ревью. Он выставляет счёт, считает НДС и проводит оплату или аннулирование. Это учебный срез биллинга, а не продуктовая налоговая система: ставки в конфиге иллюстративные.

## Что смотреть на звонке

1. `src/Billing/Domain/Model/Invoice.php` — агрегат и статусная машина `draft → issued → paid | void`.
2. `src/Billing/Domain/Model/Money.php` — деньги в минорных единицах, округление half-up, без `float`.
3. `src/Billing/Application/IssueInvoiceHandler.php` — сценарий и идемпотентность по `clientRequestId`.
4. `src/Billing/Infrastructure/Http/InvoiceController.php` — тонкий HTTP. Команды идут в Messenger, чтение идёт напрямую.
5. `src/Billing/Infrastructure/Http/DomainExceptionListener.php` — доменные ошибки как `application/problem+json`.
6. `tests/` — правила живут в домене, хендлер и HTTP их проверяют снаружи.

Зависимости направлены внутрь. Домен не знает про Symfony. Порты, которые подменяются в бою: `InvoiceRepository`, `VatPolicy`, `Clock`, `DomainEventDispatcher`.

## Правила

- Счёт без строк выставить нельзя. Строки после `issue` не добавляются. Валюта строки совпадает с валютой счёта.
- НДС берётся из политики по стране и замораживается в момент `issue` (`net`, `tax`, `gross`, ставка).
- Тот же `clientRequestId` и то же тело возвращают тот же счёт (`200`). Другое тело на тот же ключ — `409`. Порядок строк входит в отпечаток: те же строки в другом порядке сейчас считаются другим запросом.
- Повторная оплата уже оплаченного счёта ничего не меняет и не шлёт второе событие. Из `draft` и `void` оплатить нельзя.
- Аннулировать можно только выставленный и ещё не оплаченный счёт.

Событие пишется в агрегат и публикуется только после успешного `save`. `LogBillingEvent` здесь стоит на месте аутбокса: если процесс умрёт между сохранением и публикацией, событие потеряется. В проде слушатель заменили бы записью в ту же транзакцию БД.

## API

Суммы — целые минорные единицы. `10000` и `EUR` это 100.00 €.

```bash
curl -s -D - -X POST http://127.0.0.1:8000/api/invoices \
  -H 'Content-Type: application/json' \
  -d '{
    "clientRequestId": "6f1c1d3e-1c4a-4a1e-9c1a-1c1d3e1c4a1e",
    "customerId": "acme",
    "countryCode": "DE",
    "currency": "EUR",
    "lines": [
      {"description": "Consulting", "quantity": 2, "unitPriceMinor": 10000}
    ]
  }'
```

Ответ `201`: `netMinor = 20000`, `taxMinor = 3800` (НДС 19%), `grossMinor = 23800`.

- `GET /api/invoices/{id}`
- `POST /api/invoices/{id}/pay`
- `POST /api/invoices/{id}/void` с телом `{"reason": "duplicate"}`

Коды: `400` битый JSON или id, `404` нет счёта, `409` конфликт ключа идемпотентности, `422` валидация или бизнес-правило.

Страны со ставкой: DE 19%, FR 20%, NL 21%, KZ 12%, US 0%. Другая страна — `422`.

## Запуск

Нужны PHP 8.3+ и Composer.

```bash
composer install
composer test
php -S 127.0.0.1:8000 -t public
```

Репозиторий в этом срезе — память процесса (`InMemoryInvoiceRepository`), чтобы ревью не упиралось в схему БД. В бою тот же интерфейс закрывается Doctrine: уникальный индекс на `client_request_id`, суммы целыми, статус строкой.
# invoice-service
