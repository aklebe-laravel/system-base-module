<?php

namespace Modules\SystemBase\app\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\SystemBase\app\Providers\Base\ScheduleBaseServiceProvider;

/**
 * SystemBase has no own schedules, see ScheduleBaseServiceProvider.
 */
class ScheduleServiceProvider extends ScheduleBaseServiceProvider
{
    protected function bootEnabledSchedule(Schedule $schedule): void
    {
    }

    protected function bootDisabledSchedule(Schedule $schedule): void
    {
    }

}
