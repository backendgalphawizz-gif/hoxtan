<?php

namespace App\Http\Controllers;

use App\Services\MetalRateService;
use App\Support\WebsiteViewData;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __invoke(MetalRateService $rates): View
    {
        $gold = $rates->getCurrentRatePerGram('gold');
        $silver = $rates->getCurrentRatePerGram('silver');

        return view('landing', array_merge(WebsiteViewData::shared(), [
            'goldRate' => round($gold, 2),
            'silverRate' => round($silver, 2),
        ]));
    }
}
