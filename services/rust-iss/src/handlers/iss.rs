use axum::{extract::State, Json};
use chrono::{DateTime, Utc};
use serde::Serialize;
use serde_json::Value;

use crate::{config::AppState, error::ApiError, repo::IssRepo, services};

pub async fn last_iss(State(st): State<AppState>) -> Result<Json<Value>, ApiError> {
    if let Some((id, fetched_at, source_url, payload)) = IssRepo::get_last(&st.pool).await? {
        return Ok(Json(serde_json::json!({
            "id": id, "fetched_at": fetched_at, "source_url": source_url, "payload": payload
        })));
    }
    Ok(Json(serde_json::json!({"message": "no data"})))
}

pub async fn trigger_iss(State(st): State<AppState>) -> Result<Json<Value>, ApiError> {
    services::fetch_and_store_iss(&st.pool, &st.fallback_url).await?;
    last_iss(State(st)).await
}

#[derive(Serialize)]
pub struct Trend {
    pub movement: bool,
    pub delta_km: f64,
    pub dt_sec: f64,
    pub velocity_kmh: Option<f64>,
    pub from_time: Option<DateTime<Utc>>,
    pub to_time: Option<DateTime<Utc>>,
    pub from_lat: Option<f64>,
    pub from_lon: Option<f64>,
    pub to_lat: Option<f64>,
    pub to_lon: Option<f64>,
}

pub async fn iss_trend(State(st): State<AppState>) -> Result<Json<Trend>, ApiError> {
    let rows = IssRepo::get_last_n(&st.pool, 2).await?;

    if rows.len() < 2 {
        return Ok(Json(Trend {
            movement: false, delta_km: 0.0, dt_sec: 0.0, velocity_kmh: None,
            from_time: None, to_time: None, from_lat: None, from_lon: None, to_lat: None, to_lon: None
        }));
    }

    let (t2, p2) = &rows[0];
    let (t1, p1) = &rows[1];

    let lat1 = num(p1, "latitude");
    let lon1 = num(p1, "longitude");
    let lat2 = num(p2, "latitude");
    let lon2 = num(p2, "longitude");
    let v2 = num(p2, "velocity");

    let mut delta_km = 0.0;
    let mut movement = false;
    if let (Some(a1), Some(o1), Some(a2), Some(o2)) = (lat1, lon1, lat2, lon2) {
        delta_km = haversine_km(a1, o1, a2, o2);
        movement = delta_km > 0.1;
    }
    let dt_sec = (*t2 - *t1).num_milliseconds() as f64 / 1000.0;

    Ok(Json(Trend {
        movement, delta_km, dt_sec, velocity_kmh: v2,
        from_time: Some(*t1), to_time: Some(*t2),
        from_lat: lat1, from_lon: lon1, to_lat: lat2, to_lon: lon2,
    }))
}

fn num(v: &Value, key: &str) -> Option<f64> {
    v.get(key).and_then(|x| x.as_f64().or_else(|| x.as_str()?.parse().ok()))
}

fn haversine_km(lat1: f64, lon1: f64, lat2: f64, lon2: f64) -> f64 {
    let (rlat1, rlat2) = (lat1.to_radians(), lat2.to_radians());
    let (dlat, dlon) = ((lat2 - lat1).to_radians(), (lon2 - lon1).to_radians());
    let a = (dlat / 2.0).sin().powi(2) + rlat1.cos() * rlat2.cos() * (dlon / 2.0).sin().powi(2);
    6371.0 * 2.0 * a.sqrt().atan2((1.0 - a).sqrt())
}
