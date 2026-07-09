<?php

namespace App\Http\Controllers;

use App\Services\AppSettingService;
use App\Services\MetalRateService;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __invoke(MetalRateService $rates, AppSettingService $settings): View
    {
        $gold = $rates->getCurrentRatePerGram('gold');
        $silver = $rates->getCurrentRatePerGram('silver');

        return view('landing', [
            'appName' => $settings->get('app_name', config('app_content.app_name', 'HOXTAN')),
            'supportEmail' => $settings->get('support_email', 'support@hoxtan.com'),
            'supportPhone' => $settings->get('support_phone', ''),
            'goldRate' => round($gold, 2),
            'silverRate' => round($silver, 2),
            'socialLinks' => [
                ['name' => 'Facebook', 'url' => 'https://facebook.com/hoxtan', 'icon' => 'facebook'],
                ['name' => 'Instagram', 'url' => 'https://instagram.com/hoxtan', 'icon' => 'instagram'],
                ['name' => 'X', 'url' => 'https://x.com/hoxtan', 'icon' => 'x'],
                ['name' => 'LinkedIn', 'url' => 'https://linkedin.com/company/hoxtan', 'icon' => 'linkedin'],
                ['name' => 'YouTube', 'url' => 'https://youtube.com/@hoxtan', 'icon' => 'youtube'],
            ],
        ]);
    }
}
