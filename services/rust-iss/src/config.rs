//! Конфигурация приложения и AppState

use sqlx::PgPool;

#[derive(Clone)]
pub struct AppState {
    pub pool: PgPool,
    pub nasa_url: String,
    pub nasa_key: String,
    pub fallback_url: String,
    pub every_osdr: u64,
    pub every_iss: u64,
    pub every_apod: u64,
    pub every_neo: u64,
    pub every_donki: u64,
    pub every_spacex: u64,
}

impl AppState {
    pub fn from_env(pool: PgPool) -> Self {
        Self {
            pool,
            nasa_url: std::env::var("NASA_API_URL")
                .unwrap_or_else(|_| "https://visualization.osdr.nasa.gov/biodata/api/v2/datasets/?format=json".into()),
            nasa_key: std::env::var("NASA_API_KEY").unwrap_or_default(),
            fallback_url: std::env::var("WHERE_ISS_URL")
                .unwrap_or_else(|_| "https://api.wheretheiss.at/v1/satellites/25544".into()),
            every_osdr: env_u64("FETCH_EVERY_SECONDS", 600),
            every_iss: env_u64("ISS_EVERY_SECONDS", 120),
            every_apod: env_u64("APOD_EVERY_SECONDS", 43200),
            every_neo: env_u64("NEO_EVERY_SECONDS", 7200),
            every_donki: env_u64("DONKI_EVERY_SECONDS", 3600),
            every_spacex: env_u64("SPACEX_EVERY_SECONDS", 3600),
        }
    }
}

pub fn env_u64(k: &str, d: u64) -> u64 {
    std::env::var(k).ok().and_then(|s| s.parse().ok()).unwrap_or(d)
}
