use chrono::{DateTime, Utc};
use serde_json::Value;
use sqlx::{PgPool, Row};

pub struct IssRepo;

impl IssRepo {
    pub async fn init_table(pool: &PgPool) -> sqlx::Result<()> {
        sqlx::query(
            "CREATE TABLE IF NOT EXISTS iss_fetch_log(
                id BIGSERIAL PRIMARY KEY,
                fetched_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                source_url TEXT NOT NULL,
                payload JSONB NOT NULL
            )"
        ).execute(pool).await?;
        Ok(())
    }

    pub async fn insert(pool: &PgPool, url: &str, payload: Value) -> sqlx::Result<()> {
        sqlx::query("INSERT INTO iss_fetch_log (source_url, payload) VALUES ($1, $2)")
            .bind(url).bind(payload).execute(pool).await?;
        Ok(())
    }

    pub async fn get_last(pool: &PgPool) -> sqlx::Result<Option<(i64, DateTime<Utc>, String, Value)>> {
        let row = sqlx::query(
            "SELECT id, fetched_at, source_url, payload FROM iss_fetch_log ORDER BY id DESC LIMIT 1"
        ).fetch_optional(pool).await?;

        Ok(row.map(|r| (
            r.get("id"),
            r.get::<DateTime<Utc>, _>("fetched_at"),
            r.get("source_url"),
            r.try_get("payload").unwrap_or(serde_json::json!({}))
        )))
    }

    pub async fn get_last_n(pool: &PgPool, n: i32) -> sqlx::Result<Vec<(DateTime<Utc>, Value)>> {
        let rows = sqlx::query("SELECT fetched_at, payload FROM iss_fetch_log ORDER BY id DESC LIMIT $1")
            .bind(n).fetch_all(pool).await?;
        Ok(rows.into_iter().map(|r| (r.get("fetched_at"), r.get("payload"))).collect())
    }
}
