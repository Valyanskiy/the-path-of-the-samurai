use chrono::{DateTime, Utc};
use serde_json::Value;
use sqlx::{PgPool, Row};

pub struct OsdrRepo;

impl OsdrRepo {
    pub async fn init_table(pool: &PgPool) -> sqlx::Result<()> {
        sqlx::query(
            "CREATE TABLE IF NOT EXISTS osdr_items(
                id BIGSERIAL PRIMARY KEY,
                dataset_id TEXT,
                title TEXT,
                status TEXT,
                updated_at TIMESTAMPTZ,
                inserted_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                raw JSONB NOT NULL
            )"
        ).execute(pool).await?;
        sqlx::query(
            "CREATE UNIQUE INDEX IF NOT EXISTS ux_osdr_dataset_id ON osdr_items(dataset_id) WHERE dataset_id IS NOT NULL"
        ).execute(pool).await?;
        Ok(())
    }

    /// Upsert по бизнес-ключу dataset_id
    pub async fn upsert(pool: &PgPool, dataset_id: Option<&str>, title: Option<&str>, status: Option<&str>, updated_at: Option<DateTime<Utc>>, raw: Value) -> sqlx::Result<()> {
        if let Some(ds) = dataset_id {
            sqlx::query(
                "INSERT INTO osdr_items(dataset_id, title, status, updated_at, raw) VALUES($1,$2,$3,$4,$5)
                 ON CONFLICT (dataset_id) DO UPDATE SET title=EXCLUDED.title, status=EXCLUDED.status, updated_at=EXCLUDED.updated_at, raw=EXCLUDED.raw"
            ).bind(ds).bind(title).bind(status).bind(updated_at).bind(raw).execute(pool).await?;
        } else {
            sqlx::query(
                "INSERT INTO osdr_items(dataset_id, title, status, updated_at, raw) VALUES($1,$2,$3,$4,$5)"
            ).bind::<Option<&str>>(None).bind(title).bind(status).bind(updated_at).bind(raw).execute(pool).await?;
        }
        Ok(())
    }

    pub async fn list(pool: &PgPool, limit: i64) -> sqlx::Result<Vec<Value>> {
        let rows = sqlx::query(
            "SELECT id, dataset_id, title, status, updated_at, inserted_at, raw FROM osdr_items ORDER BY inserted_at DESC LIMIT $1"
        ).bind(limit).fetch_all(pool).await?;

        Ok(rows.into_iter().map(|r| serde_json::json!({
            "id": r.get::<i64, _>("id"),
            "dataset_id": r.get::<Option<String>, _>("dataset_id"),
            "title": r.get::<Option<String>, _>("title"),
            "status": r.get::<Option<String>, _>("status"),
            "updated_at": r.get::<Option<DateTime<Utc>>, _>("updated_at"),
            "inserted_at": r.get::<DateTime<Utc>, _>("inserted_at"),
            "raw": r.get::<Value, _>("raw"),
        })).collect())
    }

    pub async fn count(pool: &PgPool) -> sqlx::Result<i64> {
        let row = sqlx::query("SELECT count(*) AS c FROM osdr_items").fetch_one(pool).await?;
        Ok(row.get("c"))
    }
}
