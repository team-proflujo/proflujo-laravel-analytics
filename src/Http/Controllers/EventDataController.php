<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Proflujo\Analytics\Http\Requests\DashboardRequest;
use Proflujo\Analytics\Services\DashboardService;

final class EventDataController
{
    public function __invoke(DashboardRequest $request, DashboardService $dashboard): JsonResponse
    {
        $table = $dashboard->events($request->range(), $request->selectedEvents(), $request->selectedCategory(), $request->pageNumber());
        $paginator = $table['paginator'];

        return response()->json([
            'data' => $table['rows'],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
