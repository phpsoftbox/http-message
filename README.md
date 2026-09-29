# Http Message

Собственная реализация PSR-7 и PSR-17 для PhpSoftBox.

## Пример

```php
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Http\Message\Uri;

$request = new ServerRequest('GET', new Uri('https://example.com/ping'));
```

## Создание запроса из globals

```php
use PhpSoftBox\Http\Message\ServerRequestCreator;

$creator = new ServerRequestCreator();
$request = $creator->fromGlobals();
```

## Прокси

`ServerRequestCreator` не учитывает `X-Forwarded-Proto`: заголовок подделывает клиент. За прокси схему, host, порт и
IP клиента подставляет `TrustedProxyMiddleware` из `phpsoftbox/application` — только для запросов от доверенных
прокси. Для замены `REMOTE_ADDR` у `ServerRequest` есть `withServerParams()` (вне PSR-7).

