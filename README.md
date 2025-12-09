# The path of the samurai

---

# Внесённые изменения

## 1. Legacy-сервис: Pascal → Python

**Коммит:** `59b2319`

- Удалён `services/pascal-legacy/` (Dockerfile, legacy.pas, run.sh)
- Создан `services/python-csv/csv_generator.py`
- Логика идентична: генерация CSV с телеметрией, загрузка в PostgreSQL через `psql \copy`

---

## 2. AstroController: исправлена работа с API

**Коммиты:** `631f623`, `e4a13db`, `cbd02e7`, `5078eb1`

- Исправлен endpoint: `/api/v2/bodies/events` → `/api/v2/bodies/events/{body}`
- Добавлен цикл по телам `['sun', 'moon']`
- Исправлены параметры API
- Добавлена валидация через `AstroRequestValidator`

---

## 3. Docker-окружение для разработки

**Коммит:** `7e1f94f`

- Создан `docker-compose.dev.yml` с hot-reload
- `entrypoint.dev.sh`: синхронизация патчей через inotifywait

---

## 4. Защита от XSS и SQL-инъекций

**Коммит:** `cbd02e7`

- Создан `HtmlSanitizer` — удаление опасных тегов
- Параметризованные SQL-запросы
- Валидация slug через regex

---

## 5. Секреты вынесены в .env

**Коммит:** `0a73c6b`

- Все API-ключи перенесены из docker-compose в `.env`

---

## 6. Разделение на страницы

**Коммит:** `5777b33`

- Создана `/astro`, `/cms`, `/telemetry`
- Dashboard упрощён

---

## 7. Анимации и UI

**Коммиты:** `1ada49b`, `ab09739`, `5078eb1`

- Bootstrap spinners и placeholders
- CSS fade-in анимации
- Поиск и сортировка в таблицах
- График Chart.js на странице Telemetry

---

## 8. Redis

**Коммит:** `86a6c80`

Добавлен Redis в docker-compose:

```yaml
redis:
  image: redis:7-alpine
  container_name: iss_redis
  healthcheck:
    test: ["CMD", "redis-cli", "ping"]
  volumes:
    - redisdata:/data
  networks:
    - backend
  ports:
    - "6379:6379"
```

---

## 9. Redis кэширование

**Коммит:** `a29b4ee`

**Расположение:** `app/Support/RedisCache.php`

```php
class RedisCache
{
    public function get(string $key): ?string;
    public function set(string $key, string $value, int $ttlSeconds): void;
    public function remember(string $key, int $ttlSeconds, callable $callback): mixed;
}
```

**Кэшируемые данные:**

| Данные | TTL | Ключ |
|--------|-----|------|
| CMS-блоки | 1 час (3600 сек) | `cms:{slug}` |
| ISS API | 2 мин (120 сек) | `api:iss:{hash}` |
| JWST API | 5 мин (300 сек) | `api:jwst:{hash}` |

Заголовок `X-Cache: HIT/MISS` показывает был ли ответ из кэша.

---

## 10. Rate-Limit Middleware

**Коммит:** `86a6c80`

**Расположение:** `app/Http/Middleware/RateLimitMiddleware.php`

Ограничение: 60 запросов в минуту на IP.

```php
class RateLimitMiddleware
{
    private int $maxRequests = 60;
    private int $windowSeconds = 60;

    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();
        $key = 'rate_limit:' . $ip;

        $redis = $this->getRedis();
        if (!$redis) {
            return $next($request); // graceful degradation
        }

        $current = (int) $redis->get($key);

        if ($current >= $this->maxRequests) {
            return response()->json([
                'ok' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Too many requests. Please try again later.',
                ]
            ], 429);
        }

        $redis->incr($key);
        if ($current === 0) {
            $redis->expire($key, $this->windowSeconds);
        }

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', $this->maxRequests);
        $response->headers->set('X-RateLimit-Remaining', max(0, $this->maxRequests - $current - 1));

        return $response;
    }
}
```

Применяется к API-маршрутам:
```php
Route::middleware([RateLimitMiddleware::class])->group(function () {
    Route::get('/api/iss/last', ...);
    Route::get('/api/iss/trend', ...);
    Route::get('/api/jwst/feed', ...);
    Route::get('/api/astro/events', ...);
});
```

---

## 11. Классы валидации

**Коммит:** `86a6c80`

**Расположение:** `app/Validation/`

### AstroRequestValidator

```php
class AstroRequestValidator
{
    public float $lat;
    public float $lon;
    public int $elevation;
    public int $days;
    public string $time;

    public function __construct(Request $request)
    {
        $this->lat = max(-90.0, min(90.0, (float) $request->query('lat', 55.7558)));
        $this->lon = max(-180.0, min(180.0, (float) $request->query('lon', 37.6176)));
        $this->elevation = max(0, min(10000, (int) $request->query('elevation', 0)));
        $this->days = max(1, min(30, (int) $request->query('days', 7)));
        $this->time = $request->query('time') ? $request->query('time') . ':00' : now('UTC')->format('H:i:s');
    }
}
```

### OsdrRequestValidator

```php
class OsdrRequestValidator
{
    public int $limit;

    public function __construct(Request $request)
    {
        $this->limit = max(1, min(100, (int) $request->query('limit', 20)));
    }
}
```

### ProxyRequestValidator

```php
class ProxyRequestValidator
{
    private array $allowedKeys = ['from', 'to', 'limit'];
    public array $params = [];

    public function __construct(Request $request)
    {
        foreach ($this->allowedKeys as $key) {
            $val = $request->query($key);
            if ($val !== null && preg_match('/^[\d\-:TZ]+$/', (string) $val)) {
                $this->params[$key] = $val;
            }
        }
    }

    public function toQueryString(): string
    {
        return $this->params ? '?' . http_build_query($this->params) : '';
    }
}
```

---

## Применённые паттерны проектирования

### 1. Strategy (Стратегия)

**Расположение:** `app/Export/`

**Описание:** Определяет семейство алгоритмов, инкапсулирует каждый из них и делает их взаимозаменяемыми.

**Применение:** Экспорт телеметрии в разные форматы (CSV, Excel).

```
ExportStrategy (interface)
├── CsvExportStrategy
└── ExcelExportStrategy
```

```php
// app/Export/ExportStrategy.php
interface ExportStrategy
{
    public function getContentType(): string;
    public function getFilename(): string;
    public function writeHeader($handle): void;
    public function writeRow($handle, object $row): void;
}

// app/Http/Controllers/TelemetryController.php
private function export(ExportStrategy $strategy): StreamedResponse
{
    return response()->streamDownload(function () use ($strategy) {
        $out = fopen('php://output', 'w');
        $strategy->writeHeader($out);
        DB::table('telemetry_legacy')->chunk(500, function ($rows) use ($out, $strategy) {
            foreach ($rows as $row) {
                $strategy->writeRow($out, $row);
            }
        });
        fclose($out);
    }, $strategy->getFilename(), ['Content-Type' => $strategy->getContentType()]);
}

public function exportCsv(): StreamedResponse {
    return $this->export(new CsvExportStrategy());
}
```

---

### 2. Repository

**Расположение:** `app/Repository/CmsRepository.php`

**Описание:** Посредник между доменом и слоем доступа к данным.

**Применение:** Доступ к CMS-блокам.

```php
class CmsRepository
{
    public function findActiveBySlug(string $slug): ?object
    {
        return DB::selectOne(
            "SELECT slug, title, body FROM cms_blocks WHERE slug = ? AND is_active = TRUE LIMIT 1",
            [$slug]
        );
    }

    public function getBodyBySlug(string $slug): ?string
    {
        $row = $this->findActiveBySlug($slug);
        return $row?->body;
    }
}
```

---

## Сводная таблица

| Модуль | Проблема | Решение | Паттерн |
|--------|----------|---------|---------|
| TelemetryController | Дублирование кода экспорта | Вынесены стратегии CSV/Excel | **Strategy** |
| CmsController | SQL в контроллере | Вынесен в CmsRepository | **Repository** |
| API | Нет ограничения запросов | RateLimitMiddleware + Redis | — |
| Контроллеры | Валидация в контроллерах | Отдельные классы Validator | — |
| docker-compose | Нет кэша | Добавлен Redis | — |
