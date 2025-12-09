//! HTTP клиенты с retry и User-Agent

use std::time::Duration;
use reqwest::Client;
use serde_json::Value;
use tokio_retry::{strategy::ExponentialBackoff, Retry};

const USER_AGENT: &str = "monolith-iss/1.0 (Cassiopeia Project)";

pub fn build_client(timeout_secs: u64) -> reqwest::Result<Client> {
    Client::builder()
        .timeout(Duration::from_secs(timeout_secs))
        .user_agent(USER_AGENT)
        .build()
}

/// GET с retry (3 попытки, exponential backoff)
pub async fn get_json_with_retry(url: &str, query: &[(&str, &str)]) -> anyhow::Result<Value> {
    let client = build_client(30)?;
    let url = url.to_string();
    let query: Vec<(String, String)> = query.iter().map(|(k, v)| (k.to_string(), v.to_string())).collect();

    let strategy = ExponentialBackoff::from_millis(200).take(3);
    
    Retry::spawn(strategy, || {
        let client = client.clone();
        let url = url.clone();
        let query = query.clone();
        async move {
            let resp = client.get(&url).query(&query).send().await?;
            let json: Value = resp.json().await?;
            Ok::<_, reqwest::Error>(json)
        }
    }).await.map_err(Into::into)
}

/// GET с API key (NASA)
pub async fn get_nasa_json(url: &str, api_key: &str, extra_query: &[(&str, &str)]) -> anyhow::Result<Value> {
    let mut query: Vec<(&str, &str)> = extra_query.to_vec();
    if !api_key.is_empty() {
        query.push(("api_key", api_key));
    }
    get_json_with_retry(url, &query).await
}
