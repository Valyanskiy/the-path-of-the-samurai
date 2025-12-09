use std::collections::HashMap;
use axum::{extract::{Path, Query, State}, Json};
use serde_json::Value;

use crate::{config::AppState, error::ApiError, repo::{CacheRepo, IssRepo, OsdrRepo}, services};

pub async fn space_latest(Path(src): Path<String>, State(st): State<AppState>) -> Result<Json<Value>, ApiError> {
    if let Some((fetched_at, payload)) = CacheRepo::get_latest(&st.pool, &src).await? {
        return Ok(Json(serde_json::json!({ "source": src, "fetched_at": fetched_at, "payload": payload })));
    }
    Ok(Json(serde_json::json!({ "source": src, "message": "no data" })))
}

pub async fn space_refresh(Query(q): Query<HashMap<String, String>>, State(st): State<AppState>) -> Result<Json<Value>, ApiError> {
    let list = q.get("src").cloned().unwrap_or_else(|| "apod,neo,flr,cme,spacex".into());
    let mut done = Vec::new();
    for s in list.split(',').map(|x| x.trim().to_lowercase()) {
        match s.as_str() {
            "apod"   => { let _ = services::fetch_apod(&st).await; done.push("apod"); }
            "neo"    => { let _ = services::fetch_neo_feed(&st).await; done.push("neo"); }
            "flr"    => { let _ = services::fetch_donki_flr(&st).await; done.push("flr"); }
            "cme"    => { let _ = services::fetch_donki_cme(&st).await; done.push("cme"); }
            "spacex" => { let _ = services::fetch_spacex_next(&st).await; done.push("spacex"); }
            _ => {}
        }
    }
    Ok(Json(serde_json::json!({ "refreshed": done })))
}

pub async fn space_summary(State(st): State<AppState>) -> Result<Json<Value>, ApiError> {
    let apod = CacheRepo::get_latest_json(&st.pool, "apod").await;
    let neo = CacheRepo::get_latest_json(&st.pool, "neo").await;
    let flr = CacheRepo::get_latest_json(&st.pool, "flr").await;
    let cme = CacheRepo::get_latest_json(&st.pool, "cme").await;
    let spacex = CacheRepo::get_latest_json(&st.pool, "spacex").await;

    let iss_last = IssRepo::get_last(&st.pool).await?.map(|(_, at, _, p)| serde_json::json!({"at": at, "payload": p})).unwrap_or(serde_json::json!({}));
    let osdr_count = OsdrRepo::count(&st.pool).await.unwrap_or(0);

    Ok(Json(serde_json::json!({
        "apod": apod, "neo": neo, "flr": flr, "cme": cme, "spacex": spacex,
        "iss": iss_last, "osdr_count": osdr_count
    })))
}
