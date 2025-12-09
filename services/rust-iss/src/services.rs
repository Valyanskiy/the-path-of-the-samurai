//! Сервисы для фоновых задач с защитой от наложения

use chrono::{DateTime, NaiveDateTime, TimeZone, Utc};
use serde_json::Value;
use sqlx::PgPool;
use tokio::sync::Mutex;

use crate::{clients, config::AppState, repo::{CacheRepo, IssRepo, OsdrRepo}};

// Mutex для защиты от наложения фоновых задач
static ISS_LOCK: Mutex<()> = Mutex::const_new(());
static OSDR_LOCK: Mutex<()> = Mutex::const_new(());
static APOD_LOCK: Mutex<()> = Mutex::const_new(());
static NEO_LOCK: Mutex<()> = Mutex::const_new(());
static DONKI_LOCK: Mutex<()> = Mutex::const_new(());
static SPACEX_LOCK: Mutex<()> = Mutex::const_new(());

pub async fn fetch_and_store_iss(pool: &PgPool, url: &str) -> anyhow::Result<()> {
    let _guard = ISS_LOCK.lock().await;
    let json = clients::get_json_with_retry(url, &[]).await?;
    IssRepo::insert(pool, url, json).await?;
    Ok(())
}

pub async fn fetch_and_store_osdr(st: &AppState) -> anyhow::Result<usize> {
    let _guard = OSDR_LOCK.lock().await;
    let json = clients::get_json_with_retry(&st.nasa_url, &[]).await?;
    
    let items = if let Some(a) = json.as_array() { a.clone() }
        else if let Some(v) = json.get("items").or(json.get("results")).and_then(|x| x.as_array()) { v.clone() }
        else { vec![json.clone()] };

    let mut written = 0;
    for item in items {
        let id = s_pick(&item, &["dataset_id", "id", "uuid", "studyId", "accession", "osdr_id"]);
        let title = s_pick(&item, &["title", "name", "label"]);
        let status = s_pick(&item, &["status", "state", "lifecycle"]);
        let updated = t_pick(&item, &["updated", "updated_at", "modified", "lastUpdated", "timestamp"]);
        OsdrRepo::upsert(&st.pool, id.as_deref(), title.as_deref(), status.as_deref(), updated, item).await?;
        written += 1;
    }
    Ok(written)
}

pub async fn fetch_apod(st: &AppState) -> anyhow::Result<()> {
    let _guard = APOD_LOCK.lock().await;
    let json = clients::get_nasa_json("https://api.nasa.gov/planetary/apod", &st.nasa_key, &[("thumbs", "true")]).await?;
    CacheRepo::insert(&st.pool, "apod", json).await?;
    Ok(())
}

pub async fn fetch_neo_feed(st: &AppState) -> anyhow::Result<()> {
    let _guard = NEO_LOCK.lock().await;
    let today = Utc::now().date_naive();
    let start = today - chrono::Days::new(2);
    let json = clients::get_nasa_json(
        "https://api.nasa.gov/neo/rest/v1/feed", &st.nasa_key,
        &[("start_date", &start.to_string()), ("end_date", &today.to_string())]
    ).await?;
    CacheRepo::insert(&st.pool, "neo", json).await?;
    Ok(())
}

pub async fn fetch_donki(st: &AppState) -> anyhow::Result<()> {
    let _ = fetch_donki_flr(st).await;
    let _ = fetch_donki_cme(st).await;
    Ok(())
}

pub async fn fetch_donki_flr(st: &AppState) -> anyhow::Result<()> {
    let _guard = DONKI_LOCK.lock().await;
    let (from, to) = last_days(5);
    let json = clients::get_nasa_json("https://api.nasa.gov/DONKI/FLR", &st.nasa_key, &[("startDate", &from), ("endDate", &to)]).await?;
    CacheRepo::insert(&st.pool, "flr", json).await?;
    Ok(())
}

pub async fn fetch_donki_cme(st: &AppState) -> anyhow::Result<()> {
    let (from, to) = last_days(5);
    let json = clients::get_nasa_json("https://api.nasa.gov/DONKI/CME", &st.nasa_key, &[("startDate", &from), ("endDate", &to)]).await?;
    CacheRepo::insert(&st.pool, "cme", json).await?;
    Ok(())
}

pub async fn fetch_spacex_next(st: &AppState) -> anyhow::Result<()> {
    let _guard = SPACEX_LOCK.lock().await;
    let json = clients::get_json_with_retry("https://api.spacexdata.com/v4/launches/next", &[]).await?;
    CacheRepo::insert(&st.pool, "spacex", json).await?;
    Ok(())
}

fn last_days(n: u64) -> (String, String) {
    let to = Utc::now().date_naive();
    let from = to - chrono::Days::new(n);
    (from.to_string(), to.to_string())
}

fn s_pick(v: &Value, keys: &[&str]) -> Option<String> {
    keys.iter().find_map(|k| v.get(*k).and_then(|x| {
        x.as_str().filter(|s| !s.is_empty()).map(String::from).or_else(|| x.is_number().then(|| x.to_string()))
    }))
}

fn t_pick(v: &Value, keys: &[&str]) -> Option<DateTime<Utc>> {
    keys.iter().find_map(|k| v.get(*k).and_then(|x| {
        x.as_str().and_then(|s| s.parse::<DateTime<Utc>>().ok()
            .or_else(|| NaiveDateTime::parse_from_str(s, "%Y-%m-%d %H:%M:%S").ok().map(|ndt| Utc.from_utc_datetime(&ndt))))
            .or_else(|| x.as_i64().and_then(|n| Utc.timestamp_opt(n, 0).single()))
    }))
}
