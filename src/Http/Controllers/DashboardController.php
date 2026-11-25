<?php

declare(strict_types=1);

namespace Proflujo\Analytics\Http\Controllers;

use Illuminate\Http\Response;
use Proflujo\Analytics\Http\Requests\DashboardRequest;
use Proflujo\Analytics\Services\DashboardService;
use Proflujo\Analytics\Support\InstanceIdentifier;

final class DashboardController
{
    public function __invoke(DashboardRequest $request, DashboardService $dashboard, InstanceIdentifier $instance): Response
    {
        $range = $request->range();
        $events = $request->selectedEvents();
        $category = $request->selectedCategory();

        $charts = $dashboard->charts($range, $events, $category);
        $table = $dashboard->events($range, $events, $category, $request->pageNumber());

        return response()->view('analytics::dashboard', [
            'instanceName' => $instance->name(),
            'range' => $range,
            'filters' => ['from' => $range->from->toDateString(), 'to' => $range->to->toDateString(), 'events' => $events, 'category' => $category],
            'categories' => $dashboard->categories($range),
            'charts' => $charts,
            'rows' => $table['rows'],
            'paginator' => $table['paginator']->withQueryString(),
            'timezone' => $range->from->format('e'),
        ]);
    }
}
