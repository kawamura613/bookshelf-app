<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    /**
     * マイ読書レポート（PG12）— 認証必須。
     *
     * Blade は $stats（summary / rating_distribution /
     * top_rated_books / genre_ratings）を参照する。
     */
    public function index(ReportService $reportService): View
    {
        return view('reports.index', [
            'stats' => $reportService->build(Auth::user()),
        ]);
    }
}
