<?php

namespace App\Http\Controllers;

use App\Services\DashboardDataService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardDataService $dashboardData,
    ): View {
        return view(
            'dashboard',
            $dashboardData->for($request->user()),
        );
    }
}
