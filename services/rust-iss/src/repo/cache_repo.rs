use chrono::{DateTime, Utc};
use serde_json::Value;
use sqlx::{PgPool, Row};

pub struct CacheRepo;

impl CacheRepo {
    pub async fn init_table(pool: &PgPool) -> sqlx::Result<()> {
        sqlx::query(
            "CREATE TABLE IF NOT EXISTS space_cache(
                id BIGSERIAL PRIMARY KEY,
                source TEXT NOT NULL,
                fetched_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                payload JSONB NOT NULL
            )"
        ).execute(pool).await?;
        sqlx::query("CREATE INDEX IF NOT EXISTS ix_space_cache_source ON space_cache(source, fetched_at DESC)")
            .execute(pool).await?;
        Ok(())
    }

    pub async fn insert(pool: &PgPool, source: &str, payload: Value) -> sqlx::Result<()> {
        sqlx::query("INSERT INTO space_cache(source, payload) VALUES ($1, $2)")
            .bind(source).bind(payload).execute(pool).await?;
        Ok(())
    }

    pub async fn get_latest(pool: &PgPool, source: &str) -> sqlx::Result<Option<(DateTime<Utc>, Value)>> {
        let row = sqlx::query(
            "SELECT fetched_at, payload FROM space_cache WHERE source = $1 ORDER BY id DESC LIMIT 1"
        ).bind(source).fetch_optional(pool).await?;
        Ok(row.map(|r| (r.get("fetched_at"), r.get("payload"))))
    }

    pub async fn get_latest_json(pool: &PgPool, source: &str) -> Value {
        Self::get_latest(pool, source).await.ok().flatten()
            .map(|(at, payload)| serde_json::json!({"at": at, "payload": payload}))
            .unwrap_or(serde_json::json!({}))
    }
}
