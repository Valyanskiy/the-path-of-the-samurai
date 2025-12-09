//! Кастомный тип ошибки для API

use axum::{http::StatusCode, response::{IntoResponse, Response}, Json};
use thiserror::Error;

#[derive(Error, Debug)]
pub enum ApiError {
    #[error("Database error: {0}")]
    Db(#[from] sqlx::Error),
    #[error("HTTP client error: {0}")]
    Http(#[from] reqwest::Error),
    #[error("Internal error: {0}")]
    Internal(#[from] anyhow::Error),
}

impl IntoResponse for ApiError {
    fn into_response(self) -> Response {
        let (status, message) = match &self {
            ApiError::Db(e) => (StatusCode::INTERNAL_SERVER_ERROR, e.to_string()),
            ApiError::Http(e) => (StatusCode::BAD_GATEWAY, e.to_string()),
            ApiError::Internal(s) => (StatusCode::INTERNAL_SERVER_ERROR, s.to_string()),
        };
        (status, Json(serde_json::json!({"ok": false, "error": {"code": status.as_u16(), "message": message}}))).into_response()
    }
}
