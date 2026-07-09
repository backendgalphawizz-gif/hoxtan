<?php

namespace App\Http\Controllers;

use App\Models\StaticPage;
use App\Support\WebsiteViewData;
use Illuminate\View\View;

class WebsitePageController extends Controller
{
    public function show(string $slug): View
    {
        $allowedSlugs = collect(config('app_content.website_pages', []))
            ->pluck('slug')
            ->all();

        if (! in_array($slug, $allowedSlugs, true)) {
            abort(404);
        }

        $page = StaticPage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return view('website.page', array_merge(WebsiteViewData::shared(), [
            'page' => $page,
            'pageTitle' => $page->title,
            'metaDescription' => str($page->content)->stripTags()->squish()->limit(160)->toString(),
        ]));
    }
}
