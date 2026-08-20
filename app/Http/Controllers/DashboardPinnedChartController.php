<?php

namespace App\Http\Controllers;

use App\Services\ReportChartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardPinnedChartController extends Controller
{
    public function update(Request $request, ReportChartService $charts): RedirectResponse
    {
        $validated = $request->validate([
            'chart_id' => ['nullable', 'string', Rule::in(ReportChartService::CHART_IDS)],
        ]);

        $chartId = $validated['chart_id'] ?? null;
        if ($chartId !== null && ! $charts->isValidChartId($chartId)) {
            $chartId = null;
        }

        $request->user()->forceFill([
            'pinned_dashboard_chart' => $chartId,
        ])->save();

        return back();
    }
}
