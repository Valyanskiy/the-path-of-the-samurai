<?php

namespace App\Http\Controllers;

use App\Repository\CmsRepository;
use App\Support\HtmlSanitizer;

class CmsController extends Controller
{
    private CmsRepository $repository;
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $this->repository = new CmsRepository();
        $this->sanitizer = new HtmlSanitizer();
    }

    private function getSafeBlock(string $slug): ?string
    {
        $body = $this->repository->getBodyBySlug($slug);
        return $body ? $this->sanitizer->sanitize($body) : null;
    }

    public function index()
    {
        return view('cms', [
            'cmsWelcome' => $this->getSafeBlock('welcome'),
            'cmsUnsafe' => $this->getSafeBlock('unsafe'),
        ]);
    }

    public function page(string $slug)
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
            abort(404);
        }

        $row = $this->repository->findActiveBySlug($slug);
        if (!$row) {
            abort(404);
        }

        return response()->view('cms.page', [
            'title' => e($row->title),
            'html' => $this->sanitizer->sanitize($row->body)
        ]);
    }
}
