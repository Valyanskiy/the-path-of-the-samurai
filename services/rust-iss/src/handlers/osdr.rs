use axum::{extract::State, Json};
use serde_json::Value;

use crate::{config::AppState, error::ApiError, repo::OsdrRepo, services};

pub async fn osdr_sync(State(st): State<AppState>) -> Result<Json<Value>, ApiError> {
    let written = services::fetch_and_store_osdr(&st).await?;
    Ok(Json(serde_json::json!({ "written": written })))
}

pub async fn osdr_list(State(st): State<AppState>) -> Result<Json<Value>, ApiError> {
    let limit = std::env::var("OSDR_LIST_LIMIT").ok().and_then(|s| s.parse().ok()).unwrap_or(20);
    let items = OsdrRepo::list(&st.pool, limit).await?;
    Ok(Json(serde_json::json!({ "items": items })))
}
