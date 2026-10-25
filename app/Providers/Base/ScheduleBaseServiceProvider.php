<?php
declare(strict_types = 1);

namespace Modules\SystemBase\app\Providers\Base;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Base provider for module schedules.
 * Extend it and overwrite bootEnabledSchedule() (and optionally bootDisabledSchedule()).
 * Scheduling can be disabled globally by config 'system-base.schedule.enabled' (env SCHEDULE_ENABLED).
 */
class ScheduleBaseServiceProvider extends ServiceProvider
{
    /**
     * True once the "scheduling disabled" warning task was added (it should exist only once,
     * no matter how many module providers extend this class).
     *
     * @var bool
     */
    protected static bool $disabledWarningRegistered = false;

    /**
     * Bootstrap any module-specific scheduled tasks.
     */
    public function boot(): void
    {
        // Schedules are only needed in console (schedule:run / schedule:work),
        // so HTTP requests and Livewire calls have no scheduling overhead.
        if (!$this->app->runningInConsole()) {
            return;
        }

        // called once the Schedule is resolved (not on every boot)
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {

            if (config('system-base.schedule.enabled', false)) {
                $this->bootEnabledSchedule($schedule);

                return;
            }

            if (!static::$disabledWarningRegistered) {
                static::$disabledWarningRegistered = true;
                $schedule->call(function () {
                    Log::warning("Scheduling globally disabled by system configuration setup.");
                })->everyTwoHours()->name('system-base-schedule-disabled-warning');
            }

            $this->bootDisabledSchedule($schedule);
        });
    }

    /**
     * Override this method in your domain module provider to append schedules.
     */
    protected function bootEnabledSchedule(Schedule $schedule): void
    {
    }

    /**
     * Override this method in your domain module provider for maintenance/fallback loops.
     */
    protected function bootDisabledSchedule(Schedule $schedule): void
    {
    }
}
