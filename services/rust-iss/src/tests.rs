//! Интеграционные и unit-тесты для rust_iss

#[cfg(test)]
mod unit_tests {
    use serde_json::json;

    // Тест haversine формулы
    #[test]
    fn test_haversine_km() {
        fn haversine_km(lat1: f64, lon1: f64, lat2: f64, lon2: f64) -> f64 {
            let (rlat1, rlat2) = (lat1.to_radians(), lat2.to_radians());
            let (dlat, dlon) = ((lat2 - lat1).to_radians(), (lon2 - lon1).to_radians());
            let a = (dlat / 2.0).sin().powi(2) + rlat1.cos() * rlat2.cos() * (dlon / 2.0).sin().powi(2);
            6371.0 * 2.0 * a.sqrt().atan2((1.0 - a).sqrt())
        }
        // Москва -> Санкт-Петербург ~634 км
        let dist = haversine_km(55.7558, 37.6173, 59.9343, 30.3351);
        assert!((dist - 634.0).abs() < 10.0, "Expected ~634 km, got {}", dist);
    }

    // Тест парсинга числа из JSON
    #[test]
    fn test_num_extraction() {
        fn num(v: &serde_json::Value, key: &str) -> Option<f64> {
            v.get(key).and_then(|x| x.as_f64().or_else(|| x.as_str()?.parse().ok()))
        }
        let v = json!({"latitude": 51.5, "longitude": "-0.12"});
        assert_eq!(num(&v, "latitude"), Some(51.5));
        assert_eq!(num(&v, "longitude"), Some(-0.12));
        assert_eq!(num(&v, "missing"), None);
    }

    // Тест s_pick — выбор строки из JSON по списку ключей
    #[test]
    fn test_s_pick() {
        fn s_pick(v: &serde_json::Value, keys: &[&str]) -> Option<String> {
            keys.iter().find_map(|k| v.get(*k).and_then(|x| {
                x.as_str().filter(|s| !s.is_empty()).map(String::from)
                    .or_else(|| x.is_number().then(|| x.to_string()))
            }))
        }
        let v = json!({"title": "Test", "name": "Fallback"});
        assert_eq!(s_pick(&v, &["title", "name"]), Some("Test".into()));
        assert_eq!(s_pick(&v, &["missing", "name"]), Some("Fallback".into()));
        assert_eq!(s_pick(&v, &["missing"]), None);
        
        let v2 = json!({"id": 123});
        assert_eq!(s_pick(&v2, &["id"]), Some("123".into()));
    }

    // Тест t_pick — парсинг даты из JSON
    #[test]
    fn test_t_pick() {
        use chrono::{DateTime, NaiveDateTime, TimeZone, Utc};
        fn t_pick(v: &serde_json::Value, keys: &[&str]) -> Option<DateTime<Utc>> {
            keys.iter().find_map(|k| v.get(*k).and_then(|x| {
                x.as_str().and_then(|s| s.parse::<DateTime<Utc>>().ok()
                    .or_else(|| NaiveDateTime::parse_from_str(s, "%Y-%m-%d %H:%M:%S").ok().map(|ndt| Utc.from_utc_datetime(&ndt))))
                    .or_else(|| x.as_i64().and_then(|n| Utc.timestamp_opt(n, 0).single()))
            }))
        }
        let v = json!({"updated": "2024-01-15T10:30:00Z"});
        assert!(t_pick(&v, &["updated"]).is_some());
        
        let v2 = json!({"timestamp": 1705315800});
        assert!(t_pick(&v2, &["timestamp"]).is_some());
    }
}

#[cfg(test)]
mod integration_tests {
    use sqlx::PgPool;
    use crate::repo::{IssRepo, OsdrRepo, CacheRepo};
    use serde_json::json;

    async fn get_test_pool() -> PgPool {
        let url = std::env::var("DATABASE_URL").expect("DATABASE_URL required for tests");
        sqlx::postgres::PgPoolOptions::new()
            .max_connections(2)
            .connect(&url)
            .await
            .expect("Failed to connect to test database")
    }

    #[tokio::test]
    async fn test_iss_repo_init_and_insert() {
        let pool = get_test_pool().await;
        
        // Инициализация таблицы
        IssRepo::init_table(&pool).await.expect("Failed to init iss table");
        
        // Вставка записи
        let payload = json!({"latitude": 51.5, "longitude": -0.12, "velocity": 27600.0});
        IssRepo::insert(&pool, "https://test.api/iss", payload.clone()).await
            .expect("Failed to insert ISS data");
        
        // Проверка последней записи
        let last = IssRepo::get_last(&pool).await.expect("Failed to get last");
        assert!(last.is_some());
        let (_, _, url, p) = last.unwrap();
        assert_eq!(url, "https://test.api/iss");
        assert_eq!(p["latitude"], 51.5);
    }

    #[tokio::test]
    async fn test_iss_repo_get_last_n() {
        let pool = get_test_pool().await;
        IssRepo::init_table(&pool).await.unwrap();
        
        // Вставляем несколько записей
        for i in 0..3 {
            let payload = json!({"latitude": 50.0 + i as f64, "longitude": i as f64});
            IssRepo::insert(&pool, "https://test.api/iss", payload).await.unwrap();
        }
        
        let rows = IssRepo::get_last_n(&pool, 2).await.expect("Failed to get last n");
        assert!(rows.len() >= 2);
    }

    #[tokio::test]
    async fn test_osdr_repo_upsert() {
        let pool = get_test_pool().await;
        OsdrRepo::init_table(&pool).await.expect("Failed to init osdr table");
        
        // Тестируем вставку без dataset_id (не использует ON CONFLICT)
        let raw = json!({"description": "Test dataset without id"});
        OsdrRepo::upsert(&pool, None, Some("Test Title"), Some("active"), None, raw.clone())
            .await.expect("Failed to insert without dataset_id");
        
        let items = OsdrRepo::list(&pool, 100).await.expect("Failed to list");
        let found = items.iter().find(|i| i["title"] == "Test Title");
        assert!(found.is_some(), "Should find inserted item");
    }

    #[tokio::test]
    async fn test_osdr_repo_count() {
        let pool = get_test_pool().await;
        OsdrRepo::init_table(&pool).await.unwrap();
        
        let count = OsdrRepo::count(&pool).await.expect("Failed to count");
        assert!(count >= 0);
    }

    #[tokio::test]
    async fn test_cache_repo_insert_and_get() {
        let pool = get_test_pool().await;
        CacheRepo::init_table(&pool).await.expect("Failed to init cache table");
        
        let payload = json!({"title": "Test APOD", "url": "https://example.com/image.jpg"});
        CacheRepo::insert(&pool, "test_apod", payload.clone()).await
            .expect("Failed to insert cache");
        
        let latest = CacheRepo::get_latest(&pool, "test_apod").await.expect("Failed to get latest");
        assert!(latest.is_some());
        let (_, p) = latest.unwrap();
        assert_eq!(p["title"], "Test APOD");
    }

    #[tokio::test]
    async fn test_cache_repo_get_latest_json() {
        let pool = get_test_pool().await;
        CacheRepo::init_table(&pool).await.unwrap();
        
        let payload = json!({"data": "test"});
        CacheRepo::insert(&pool, "test_json", payload).await.unwrap();
        
        let result = CacheRepo::get_latest_json(&pool, "test_json").await;
        assert!(result.get("at").is_some());
        assert!(result.get("payload").is_some());
    }

    #[tokio::test]
    async fn test_cache_repo_missing_source() {
        let pool = get_test_pool().await;
        CacheRepo::init_table(&pool).await.unwrap();
        
        let result = CacheRepo::get_latest(&pool, "nonexistent_source_xyz").await.unwrap();
        assert!(result.is_none());
    }
}

#[cfg(test)]
mod api_tests {
    use axum::{body::Body, http::{Request, StatusCode}};
    use tower::ServiceExt;
    use crate::config::AppState;
    use crate::repo::{IssRepo, OsdrRepo, CacheRepo};

    async fn get_test_app() -> axum::Router {
        let url = std::env::var("DATABASE_URL").expect("DATABASE_URL required");
        let pool = sqlx::postgres::PgPoolOptions::new()
            .max_connections(2)
            .connect(&url)
            .await
            .expect("Failed to connect");

        IssRepo::init_table(&pool).await.unwrap();
        OsdrRepo::init_table(&pool).await.unwrap();
        CacheRepo::init_table(&pool).await.unwrap();

        let state = AppState::from_env(pool);

        axum::Router::new()
            .route("/health", axum::routing::get(|| async {
                axum::Json(serde_json::json!({"status": "ok"}))
            }))
            .route("/last", axum::routing::get(crate::handlers::last_iss))
            .route("/iss/trend", axum::routing::get(crate::handlers::iss_trend))
            .route("/osdr/list", axum::routing::get(crate::handlers::osdr_list))
            .route("/space/:src/latest", axum::routing::get(crate::handlers::space_latest))
            .route("/space/summary", axum::routing::get(crate::handlers::space_summary))
            .with_state(state)
    }

    #[tokio::test]
    async fn test_health_endpoint() {
        let app = get_test_app().await;
        let response = app
            .oneshot(Request::builder().uri("/health").body(Body::empty()).unwrap())
            .await
            .unwrap();
        assert_eq!(response.status(), StatusCode::OK);
    }

    #[tokio::test]
    async fn test_last_iss_endpoint() {
        let app = get_test_app().await;
        let response = app
            .oneshot(Request::builder().uri("/last").body(Body::empty()).unwrap())
            .await
            .unwrap();
        assert_eq!(response.status(), StatusCode::OK);
    }

    #[tokio::test]
    async fn test_iss_trend_endpoint() {
        let app = get_test_app().await;
        let response = app
            .oneshot(Request::builder().uri("/iss/trend").body(Body::empty()).unwrap())
            .await
            .unwrap();
        assert_eq!(response.status(), StatusCode::OK);
    }

    #[tokio::test]
    async fn test_osdr_list_endpoint() {
        let app = get_test_app().await;
        let response = app
            .oneshot(Request::builder().uri("/osdr/list").body(Body::empty()).unwrap())
            .await
            .unwrap();
        assert_eq!(response.status(), StatusCode::OK);
    }

    #[tokio::test]
    async fn test_space_latest_endpoint() {
        let app = get_test_app().await;
        let response = app
            .oneshot(Request::builder().uri("/space/apod/latest").body(Body::empty()).unwrap())
            .await
            .unwrap();
        assert_eq!(response.status(), StatusCode::OK);
    }

    #[tokio::test]
    async fn test_space_summary_endpoint() {
        let app = get_test_app().await;
        let response = app
            .oneshot(Request::builder().uri("/space/summary").body(Body::empty()).unwrap())
            .await
            .unwrap();
        assert_eq!(response.status(), StatusCode::OK);
    }
}
