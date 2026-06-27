<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PlaceholderImage
{
    public static function banner(string $filename, string $label, string $background = '#ea580c'): string
    {
        $path = 'banners/'.$filename;

        Storage::disk('public')->makeDirectory('banners');

        if (! Storage::disk('public')->exists($path)) {
            $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

            Storage::disk('public')->put($path, <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="400" viewBox="0 0 1200 400">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:{$background};stop-opacity:1" />
      <stop offset="100%" style="stop-color:#111827;stop-opacity:1" />
    </linearGradient>
  </defs>
  <rect width="1200" height="400" fill="url(#bg)" />
  <text x="600" y="210" text-anchor="middle" fill="#ffffff" font-size="42" font-family="Arial, sans-serif" font-weight="700">{$safeLabel}</text>
</svg>
SVG);
        }

        return $path;
    }
}
