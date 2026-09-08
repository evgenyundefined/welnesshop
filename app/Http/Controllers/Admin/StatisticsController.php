<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Statistics\BuildStatistics;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StatisticsRequest;
use App\Http\Resources\Admin\StatisticsResource;

class StatisticsController extends Controller
{
    public function __invoke(StatisticsRequest $request, BuildStatistics $buildStatistics): StatisticsResource
    {
        return new StatisticsResource($buildStatistics($request->period()));
    }
}
