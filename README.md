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

## Разбор host и request target

`ServerRequestCreator::fromGlobals()`:

- host и порт берёт из заголовка `Host`; IPv6-адрес указывается в скобках (`[::1]:8080`), пустой или некорректный
  порт (`example.com:`) отбрасывается. `SERVER_NAME`/`SERVER_PORT` используются, только если `Host` не пришёл;
- некорректный `Host` (например, со `/`) не приводит к ошибке — URI остаётся без host;
- `REQUEST_URI` делится на path и query вручную: `//evil/x?y=1` даёт path `//evil/x`, а не authority `evil`;
- заголовки запроса с недопустимым именем или значением пропускаются.

## Прокси

`ServerRequestCreator` не учитывает `X-Forwarded-Proto`: заголовок подделывает клиент. За прокси схему, host, порт и
IP клиента подставляет `TrustedProxyMiddleware` из `phpsoftbox/application` — только для запросов от доверенных
прокси. Для замены `REMOTE_ADDR` у `ServerRequest` есть `withServerParams()` (вне PSR-7).


## Соответствие PSR-7

### Заголовки

- Имя заголовка — token из RFC 7230 (буквы, цифры и ``!#$%&'*+-.^_`|~``), иначе `InvalidArgumentException`.
- Значение не может содержать CR, LF, NUL и другие управляющие символы, кроме табуляции: `withHeader('X-A',
  "a\r\nSet-Cookie: x=1")` бросает `InvalidArgumentException`. Проверка действует в конструкторе, `withHeader()` и
  `withAddedHeader()`. Пробелы и табуляция по краям значения удаляются.
- Значение — строка, число или непустой массив из них; пустой массив и `null` отклоняются.

### Метод и request target

Метод регистрозависим и сохраняется как передан: `new Request('get', '/')->getMethod()` вернёт `get`. Допустим только
token из RFC 7230. Request target с пробельными символами отклоняется.

### Uri

- Path, query, fragment и user info кодируются: недопустимые символы заменяются на `%XX`, уже закодированные
  последовательности не кодируются повторно (`/a b` → `/a%20b`, `/a%20b` остаётся как есть).
- Стандартный порт схемы (`http` — 80, `https` — 443, `ws`/`wss`) возвращается из `getPort()` как `null` и не
  попадает в authority.
- При сборке строки rootless path при наличии authority дополняется `/` (`http://example.com` + `foo` →
  `http://example.com/foo`); path на `//` без authority сокращается до одного `/`.
- Host с пробелами, управляющими символами, `/`, `?`, `#`, `@` отклоняется.

### Прочее

- `ServerRequest::getAttribute()` возвращает сохранённый `null`, а не значение по умолчанию.
- `StreamFactory::createStreamFromFile()` бросает `RuntimeException`, если файл не открылся, и
  `InvalidArgumentException` для недопустимого режима.
- `Response` подставляет стандартную reason phrase для всех статусов реестра IANA.
- `UploadedFile::moveTo()` переносит файлы, загруженные через SAPI, функцией `move_uploaded_file()`; остальные
  stream копирует с начала. Ошибка открытия или записи целевого файла даёт `RuntimeException`, файл при этом не
  считается перенесённым.
