<?php

namespace App\Http\Controllers;

use App\Services\ReportChartService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(Request $request, ReportChartService $charts): Response
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $catalog = array_values($charts->catalog());
        $built = [];
        foreach (ReportChartService::CHART_IDS as $chartId) {
            $built[$chartId] = $charts->build($chartId, $month, $year);
        }

        return Inertia::render('Reports/Index', [
            'charts' => $built,
            'catalog' => $catalog,
            'pinnedChartId' => $request->user()->pinned_dashboard_chart,
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
        ]);
    }
}
