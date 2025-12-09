<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AstroController;
use App\Http\Controllers\CmsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IssController;
use App\Http\Controllers\OsdrController;
use App\Http\Controllers\ProxyController;

Route::get('/', fn() => redirect('/dashboard'));

// Панели
Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/osdr',      [OsdrController::class,      'index']);
Route::get('/iss',       [IssController::class,       'index']);
Route::get('/astro',     [AstroController::class,     'index']);
Route::get('/cms',       [CmsController::class,       'index']);

// Прокси к rust_iss
Route::get('/api/iss/last',  [ProxyController::class, 'last']);
Route::get('/api/iss/trend', [ProxyController::class, 'trend']);

// JWST галерея (JSON)
Route::get('/api/jwst/feed', [DashboardController::class, 'jwstFeed']);

// Astro API
Route::get('/api/astro/events', [AstroController::class, 'events']);

// CMS страницы
Route::get('/page/{slug}', [CmsController::class, 'page'])
    ->where('slug', '[a-zA-Z0-9_-]+');
