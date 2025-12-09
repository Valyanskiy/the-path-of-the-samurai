# Changelog (после коммита 898965c7)

## 1. Инфраструктура

### Замена Pascal на Python (59b2319)
- Удалён сервис `pascal_legacy` (Pascal)
- Добавлен сервис `python_csv` (Python 3.12-slim)
- Новый скрипт `csv_generator.py`:
  - Генерирует CSV с телеметрией (voltage, temp, recorded_at)
  - Записывает в PostgreSQL через `psql \copy`
  - Работает в бесконечном цикле с настраиваемым интервалом
- Переменная окружения: `PAS_LEGACY_PERIOD` → `PYTHON_CSV_PERIOD`

### Docker для разработки (7e1f94f)
- Добавлен `docker-compose.dev.yml` с hot-reload
- `entrypoint.dev.sh`:
  - Создаёт Laravel skeleton если отсутствует
  - Синхронизирует патчи через rsync
  - Следит за изменениями через inotifywait
- `Dockerfile.dev` для rust_iss с `cargo run`

### Redis (86a6c80)
- Добавлен сервис Redis 7-alpine в docker-compose
- Volume `redisdata` для персистентности
- Healthcheck через `redis-cli ping`
- Переменные: `REDIS_HOST`, `REDIS_PORT`

### Вынос секретов в .env (0a73c6b)
- Все секреты вынесены из docker-compose в `.env`:
  - `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD`
  - `NASA_API_KEY`, `NASA_API_URL`, `WHERE_ISS_URL`
  - `JWST_HOST`, `JWST_API_KEY`, `JWST_EMAIL`, `JWST_PROGRAM_ID`
  - `ASTRO_APP_ID`, `ASTRO_APP_SECRET`
  - `APP_ENV`, `APP_DEBUG`, `APP_URL`
  - Интервалы: `FETCH_EVERY_SECONDS`, `ISS_EVERY_SECONDS`, `APOD_EVERY_SECONDS`, `NEO_EVERY_SECONDS`, `DONKI_EVERY_SECONDS`, `SPACEX_EVERY_SECONDS`
- docker-compose использует `${VAR}` вместо hardcoded значений
- Добавлен `.env.example` с плейсхолдерами

### Отключение Laravel миграций (0a73c6b)
- Добавлен `config/database.php` с `'migrations' => null`
- БД инициализируется через `db/init.sql`

### Удаление неиспользуемых файлов (73a5e7e, 5ffa591)
- Удалена миграция `2025_11_03_193635_dummy_training_marker.php`
- Удалены `JwstHelper.php`, `web.php.orig`, `web.php.rej`

---

## 2. База данных (d83353e)

### Изменения в cms_blocks
- Таблица `cms_pages` переименована в `cms_blocks`
- Добавлено поле `is_active BOOLEAN NOT NULL DEFAULT FALSE`
- Поле `content` переименовано в `body`
- Seed данные обновлены с `is_active = true`

---

## 3. Безопасность

### XSS защита (cbd02e7, 86a6c80)
Класс `HtmlSanitizer` (`app/Support/HtmlSanitizer.php`):
```php
public function sanitize(?string $html): string
```
Удаляет:
- `<script>`, `<style>` теги с содержимым
- `<iframe>`, `<object>`, `<embed>`, `<form>` теги
- `on*` атрибуты (onclick, onerror, onload и т.д.)
- `javascript:` в href/src атрибутах

Применяется в:
- `CmsController::page()` — санитизация body из БД
- `CmsController::index()` — санитизация блоков welcome/unsafe
- `DashboardController::getCmsBlock()` (до рефакторинга в 5777b33)

### SQL Injection защита (cbd02e7)
- Параметризованные запросы везде: `WHERE slug = ?`
- Валидация slug через regex: `/^[a-zA-Z0-9_-]+$/`
- Route constraint: `->where('slug', '[a-zA-Z0-9_-]+')`

### Валидация входных параметров (cbd02e7, 86a6c80)

**AstroController** (cbd02e7, 86a6c80):
- `lat`: clamp(-90.0, 90.0)
- `lon`: clamp(-180.0, 180.0)
- `elevation`: clamp(0, 10000) — добавлено в 5078eb1
- `days`: clamp(1, 30)
- `time`: формат HH:MM:SS — добавлено в 5078eb1

**OsdrController** (cbd02e7):
- `limit`: clamp(1, 100), default 20

**ProxyController** (cbd02e7):
- Whitelist параметров: `from`, `to`, `limit`
- Regex валидация: `/^[\d\-:TZ]+$/`

### Rate Limiting (86a6c80)
Middleware `RateLimitMiddleware`:
- 60 запросов в минуту на IP
- Хранение счётчиков в Redis с TTL 60 сек
- HTTP 429 при превышении лимита
- Заголовки: `X-RateLimit-Limit`, `X-RateLimit-Remaining`
- Применяется к API routes: `/api/iss/*`, `/api/jwst/*`, `/api/astro/*`

---

## 4. Новые страницы

### /astro — Астрономические события (5777b33, 5078eb1)
**Контроллер:** `AstroController::index()`
**View:** `astro.blade.php`

Функционал:
- Форма с параметрами: lat, lon, elevation, time, days
- Таблица событий: тело, тип, дата, дополнительно
- Раскрывающийся блок с полным JSON
- Автозагрузка при открытии страницы

### /cms — CMS блоки (5777b33)
**Контроллер:** `CmsController::index()`
**View:** `cms.blade.php`

Функционал:
- Отображение блоков `welcome` и `unsafe`
- HTML санитизирован от XSS

### /telemetry — Телеметрия (36f3552, 5078eb1)
**Контроллер:** `TelemetryController`
**View:** `telemetry.blade.php`

Функционал:
- Таблица последних 500 записей из `telemetry_legacy`
- Экспорт в CSV: `/telemetry/export/csv`
- Экспорт в Excel (TSV): `/telemetry/export/excel`
- График Voltage/Temperature (Chart.js) — добавлен в 5078eb1
- Поиск по колонкам — добавлен в 5078eb1
- Сортировка таблицы — добавлена в 5078eb1

---

## 5. UI/UX улучшения

### CSS анимации (ab09739)
В `layouts/app.blade.php` добавлены стили:
```css
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px) }
  to { opacity: 1; transform: translateY(0) }
}
.fade-in { animation: fadeIn .3s ease-out both }
.fade-in-delay-1 { animation-delay: .1s }
.fade-in-delay-2 { animation-delay: .2s }
.fade-in-delay-3 { animation-delay: .3s }
```
Применяется ко всем карточкам и заголовкам на страницах.

### Спиннеры загрузки (1ada49b)
- Astro: `<span class="spinner-border spinner-border-sm">` при загрузке таблицы
- Dashboard: placeholder для данных МКС до загрузки
- JWST галерея: спиннер при загрузке изображений

### Поиск и сортировка таблиц (5078eb1)
**OSDR (`osdr.blade.php`):**
- Dropdown выбора колонки для поиска
- Input для текстового поиска
- Сортировка по клику на заголовок (↑/↓ индикаторы)

**Telemetry (`telemetry.blade.php`):**
- Аналогичный поиск и сортировка
- График Voltage/Temperature из данных таблицы

CSS для индикаторов сортировки:
```css
th[data-sort]::after { content: '⇅'; opacity: .3 }
th[data-dir="asc"]::after { content: '↑'; opacity: 1 }
th[data-dir="desc"]::after { content: '↓'; opacity: 1 }
```

### JWST Preview (ab09739)
В `dashboard.blade.php`:
- Блок `#jwstPreview` для отображения выбранного изображения
- Клик по изображению в галерее показывает превью с кнопкой "Открыть оригинал"

### Навигация (4b1d971, 5777b33, 36f3552)
Добавлены ссылки в `layouts/app.blade.php`:
- ISS (4b1d971)
- AstronomyAPI (5777b33)
- CMS (5777b33)
- Telemetry (36f3552)

### Динамическое обновление МКС (413a511)
В `dashboard.blade.php`:
- Карта и графики обновляются по интервалу `issEverySeconds`
- Карточки скорости и высоты обновляются в реальном времени
- Trail на карте ограничен 240 точками

---

## 6. Кэширование (a29b4ee)

### Класс RedisCache (`app/Support/RedisCache.php`)
```php
class RedisCache {
    public function get(string $key): ?string;
    public function set(string $key, string $value, int $ttlSeconds): void;
    public function remember(string $key, int $ttlSeconds, callable $callback): mixed;
}
```

### TTL по типам данных
| Данные | TTL | Где |
|--------|-----|-----|
| CMS блоки | 1 час (3600 сек) | `CmsRepository` |
| ISS API | 2 мин (120 сек) | `ProxyController` |
| JWST feed | 5 мин (300 сек) | `DashboardController` |

### Заголовок X-Cache
- `X-Cache: HIT` — данные из кэша
- `X-Cache: MISS` — данные получены из источника

---

## 7. Рефакторинг Rust сервиса (03776ad)

### Модульная архитектура
```
src/
├── main.rs           # Entry point, роутинг
├── config.rs         # AppState с конфигурацией
├── error.rs          # ApiError для единообразных ответов
├── clients.rs        # HTTP клиенты с retry и User-Agent
├── services.rs       # Бизнес-логика фоновых задач
├── handlers/         # HTTP handlers
│   ├── mod.rs
│   ├── iss.rs        # last_iss, trigger_iss, iss_trend
│   ├── osdr.rs       # osdr_sync, osdr_list
│   └── space.rs      # space_latest, space_refresh, space_summary
└── repo/             # Repository layer
    ├── mod.rs
    ├── iss_repo.rs   # IssRepo
    ├── osdr_repo.rs  # OsdrRepo
    └── cache_repo.rs # CacheRepo
```

### Repository Pattern (Rust)
**IssRepo:**
```rust
pub async fn init_table(pool: &PgPool) -> sqlx::Result<()>;
pub async fn insert(pool: &PgPool, url: &str, payload: Value) -> sqlx::Result<()>;
pub async fn get_last(pool: &PgPool) -> sqlx::Result<Option<(i64, DateTime<Utc>, String, Value)>>;
pub async fn get_last_n(pool: &PgPool, n: i32) -> sqlx::Result<Vec<(DateTime<Utc>, Value)>>;
```

**OsdrRepo:**
```rust
pub async fn init_table(pool: &PgPool) -> sqlx::Result<()>;
pub async fn upsert(pool: &PgPool, dataset_id: Option<&str>, ...) -> sqlx::Result<()>;
pub async fn list(pool: &PgPool, limit: i64) -> sqlx::Result<Vec<Value>>;
pub async fn count(pool: &PgPool) -> sqlx::Result<i64>;
```

**CacheRepo:**
```rust
pub async fn init_table(pool: &PgPool) -> sqlx::Result<()>;
pub async fn insert(pool: &PgPool, source: &str, payload: Value) -> sqlx::Result<()>;
pub async fn get_latest(pool: &PgPool, source: &str) -> sqlx::Result<Option<(DateTime<Utc>, Value)>>;
pub async fn get_latest_json(pool: &PgPool, source: &str) -> Value;
```

### Защита от наложения фоновых задач
В `services.rs` используются статические Mutex:
```rust
static ISS_LOCK: Mutex<()> = Mutex::const_new(());
static OSDR_LOCK: Mutex<()> = Mutex::const_new(());
// ...

pub async fn fetch_and_store_iss(pool: &PgPool, url: &str) -> anyhow::Result<()> {
    let _guard = ISS_LOCK.lock().await;
    // ...
}
```

### Унифицированный запуск фоновых задач
```rust
fn spawn_background_task<F, Fut>(state: AppState, interval_secs: u64, task: F)
where
    F: Fn(AppState) -> Fut + Send + 'static,
    Fut: std::future::Future<Output = ()> + Send,
{
    tokio::spawn(async move {
        loop {
            task(state.clone()).await;
            tokio::time::sleep(Duration::from_secs(interval_secs)).await;
        }
    });
}
```

---

## 8. Паттерны проектирования

### Strategy Pattern (86a6c80)
**Расположение:** `app/Export/`

**Интерфейс:**
```php
interface ExportStrategy {
    public function getContentType(): string;
    public function getFilename(): string;
    public function writeHeader($handle): void;
    public function writeRow($handle, object $row): void;
}
```

**Реализации:**
- `CsvExportStrategy` — CSV формат (fputcsv)
- `ExcelExportStrategy` — TSV формат (fwrite с табуляцией)

**Использование в TelemetryController:**
```php
private function export(ExportStrategy $strategy): StreamedResponse {
    return response()->streamDownload(function () use ($strategy) {
        $out = fopen('php://output', 'w');
        $strategy->writeHeader($out);
        DB::table('telemetry_legacy')->orderBy('id')->chunk(500, function ($rows) use ($out, $strategy) {
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

public function exportExcel(): StreamedResponse {
    return $this->export(new ExcelExportStrategy());
}
```

### Repository Pattern (86a6c80, 03776ad)

**PHP — CmsRepository:**
```php
class CmsRepository {
    private RedisCache $cache;
    private const CACHE_TTL = 3600;

    public function findActiveBySlug(string $slug): ?object {
        $key = 'cms:' . $slug;
        $data = $this->cache->remember($key, self::CACHE_TTL, function () use ($slug) {
            $row = DB::selectOne(
                "SELECT slug, title, body FROM cms_blocks WHERE slug = ? AND is_active = TRUE LIMIT 1",
                [$slug]
            );
            return $row ? (array) $row : null;
        });
        return $data ? (object) $data : null;
    }

    public function getBodyBySlug(string $slug): ?string {
        $row = $this->findActiveBySlug($slug);
        return $row?->body;
    }
}
```

**Rust — см. раздел 7**

### Middleware Pattern (86a6c80)
**Расположение:** `app/Http/Middleware/RateLimitMiddleware.php`

```php
class RateLimitMiddleware {
    private int $maxRequests = 60;
    private int $windowSeconds = 60;

    public function handle(Request $request, Closure $next) {
        $ip = $request->ip();
        $key = 'rate_limit:' . $ip;
        $redis = $this->getRedis();
        
        if (!$redis) return $next($request);

        $current = (int) $redis->get($key);
        if ($current >= $this->maxRequests) {
            return response()->json([
                'ok' => false,
                'error' => ['code' => 'RATE_LIMIT_EXCEEDED', 'message' => '...']
            ], 429);
        }

        $redis->incr($key);
        if ($current === 0) $redis->expire($key, $this->windowSeconds);

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', $this->maxRequests);
        $response->headers->set('X-RateLimit-Remaining', max(0, $this->maxRequests - $current - 1));
        return $response;
    }
}
```

**Регистрация в routes/web.php:**
```php
Route::middleware([RateLimitMiddleware::class])->group(function () {
    Route::get('/api/iss/last', ...);
    Route::get('/api/iss/trend', ...);
    Route::get('/api/jwst/feed', ...);
    Route::get('/api/astro/events', ...);
});
```

### Validation Objects (86a6c80)
**Расположение:** `app/Validation/`

**AstroRequestValidator:**
```php
class AstroRequestValidator {
    public float $lat;
    public float $lon;
    public int $elevation;
    public int $days;
    public string $time;

    public function __construct(Request $request) {
        $this->lat = max(-90.0, min(90.0, (float) $request->query('lat', 55.7558)));
        $this->lon = max(-180.0, min(180.0, (float) $request->query('lon', 37.6176)));
        $this->elevation = max(0, min(10000, (int) $request->query('elevation', 0)));
        $this->days = max(1, min(30, (int) $request->query('days', 7)));
        $this->time = $request->query('time') ? $request->query('time') . ':00' : now('UTC')->format('H:i:s');
    }
}
```

**OsdrRequestValidator:**
```php
class OsdrRequestValidator {
    public int $limit;

    public function __construct(Request $request) {
        $this->limit = max(1, min(100, (int) $request->query('limit', 20)));
    }
}
```

**ProxyRequestValidator:**
```php
class ProxyRequestValidator {
    private array $allowedKeys = ['from', 'to', 'limit'];
    public array $params = [];

    public function __construct(Request $request) {
        foreach ($this->allowedKeys as $key) {
            $val = $request->query($key);
            if ($val !== null && preg_match('/^[\d\-:TZ]+$/', (string) $val)) {
                $this->params[$key] = $val;
            }
        }
    }

    public function toQueryString(): string {
        return $this->params ? '?' . http_build_query($this->params) : '';
    }
}
```

---

## 9. Исправления багов

### AstroController (631f623, e4a13db)
- Переработан API: отдельные запросы для sun и moon
- Использование `getenv()` вместо `env()` для совместимости

### Навигация ISS (4b1d971)
- Убран `onclick="return false"` который блокировал переход на страницу ISS

### CMS запросы (413a511)
- Исправлены запросы: `content` → `body`, `cms_pages` → `cms_blocks`
