<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Proflujo\Analytics\Http\Requests\DashboardRequest;
use Proflujo\Analytics\Services\DashboardService;

final class ChartDataController
{
    public function __invoke(DashboardRequest $request, DashboardService $dashboard): JsonResponse
    {
        return response()->json($dashboard->charts($request->range(), $request->selectedEvents(), $request->selectedCategory()));
    }
}
