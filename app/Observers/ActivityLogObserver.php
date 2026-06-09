<?php

namespace App\Observers;

use App\Services\ActivityLogService;
use Illuminate\Database\Eloquent\Model;

class ActivityLogObserver
{
    public function created(Model $model): void
    {
        app(ActivityLogService::class)->created($model);
    }

    public function updated(Model $model): void
    {
        app(ActivityLogService::class)->updated($model);
    }

    public function deleted(Model $model): void
    {
        app(ActivityLogService::class)->deleted($model);
    }
}
