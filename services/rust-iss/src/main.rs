//! rust_iss — сервис сбора космических данных
//! 
//! Архитектура:
//! - config/   — AppState и конфигурация
//! - error/    — ApiError для единообразных ответов
//! - handlers/ — HTTP хендлеры (ISS, OSDR, Space)
//! - repo/     — Repository layer (IssRepo, OsdrRepo, CacheRepo)
//! - services/ — бизнес-логика и фоновые задачи
//! - clients/  — HTTP клиенты с retry и User-Agent

mod config;
mod error;
mod handlers;
mod repo;
mod services;
mod clients;

use std::time::Duration;
use axum::{routing::get, Json, Router};
use chrono::{DateTime, Utc};
use serde::Serialize;
use sqlx::postgres::PgPoolOptions;
use tracing::{error, info};
use tracing_subscriber::{EnvFilter, FmtSubscriber};

use config::AppState;
use repo::{IssRepo, OsdrRepo, CacheRepo};

#[derive(Serialize)]
struct Health { status: &'static str, now: DateTime<Utc> }

#[tokio::main]
async fn main() -> anyhow::Result<()> {
    let subscriber = FmtSubscriber::builder().with_env_filter(EnvFilter::from_default_env()).finish();
    let _ = tracing::subscriber::set_global_default(subscriber);

    dotenvy::dotenv().ok();

    let db_url = std::env::var("DATABASE_URL").expect("DATABASE_URL is required");
    let pool = PgPoolOptions::new().max_connections(5).connect(&db_url).await?;

    // Инициализация таблиц через репозитории
    IssRepo::init_table(&pool).await?;
    OsdrRepo::init_table(&pool).await?;
    CacheRepo::init_table(&pool).await?;

    let state = AppState::from_env(pool);

    // Фоновые задачи с защитой от наложения (mutex в services)
    spawn_background_task(state.clone(), state.every_osdr, |st| async move {
        if let Err(e) = services::fetch_and_store_osdr(&st).await { error!("osdr err {e:?}") }
    });
    spawn_background_task(state.clone(), state.every_iss, |st| async move {
        if let Err(e) = services::fetch_and_store_iss(&st.pool, &st.fallback_url).await { error!("iss err {e:?}") }
    });
    spawn_background_task(state.clone(), state.every_apod, |st| async move {
        if let Err(e) = services::fetch_apod(&st).await { error!("apod err {e:?}") }
    });
    spawn_background_task(state.clone(), state.every_neo, |st| async move {
        if let Err(e) = services::fetch_neo_feed(&st).await { error!("neo err {e:?}") }
    });
    spawn_background_task(state.clone(), state.every_donki, |st| async move {
        if let Err(e) = services::fetch_donki(&st).await { error!("donki err {e:?}") }
    });
    spawn_background_task(state.clone(), state.every_spacex, |st| async move {
        if let Err(e) = services::fetch_spacex_next(&st).await { error!("spacex err {e:?}") }
    });

    let app = Router::new()
        .route("/health", get(|| async { Json(Health { status: "ok", now: Utc::now() }) }))
        // ISS
        .route("/last", get(handlers::last_iss))
        .route("/fetch", get(handlers::trigger_iss))
        .route("/iss/trend", get(handlers::iss_trend))
        // OSDR
        .route("/osdr/sync", get(handlers::osdr_sync))
        .route("/osdr/list", get(handlers::osdr_list))
        // Space cache
        .route("/space/:src/latest", get(handlers::space_latest))
        .route("/space/refresh", get(handlers::space_refresh))
        .route("/space/summary", get(handlers::space_summary))
        .with_state(state);

    let listener = tokio::net::TcpListener::bind(("0.0.0.0", 3000)).await?;
    info!("rust_iss listening on 0.0.0.0:3000");
    axum::serve(listener, app.into_make_service()).await?;
    Ok(())
}

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
