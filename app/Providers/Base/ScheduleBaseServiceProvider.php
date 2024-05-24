<?php

namespace Modules\SystemBase\app\Providers\Base;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class ScheduleBaseServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function boot()
    {
        $this->app->booted(function () {

            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            // Decide schedule is generally enabled ...
            if (config('system-base.schedule.enabled', false)) {

                // Start the overwritten method for enabled stuff ...
                $this->bootEnabledSchedule($schedule);

            } else {

                // If disabled, note it every 15 minutes to log ...
                $schedule->call(function () {
                    Log::warning("Scheduling disabled by config 'system_base.schedule.enabled'. Only disabled marked schedules should run. (Message every 15 minutes)",
                        [get_class($this), __METHOD__]);
                })->everyFifteenMinutes();

                // Start the overwritten method for disabled stuff ...
                $this->bootDisabledSchedule($schedule);

            }

        });
    }

    /**
     * Overwrite this method
     *
     * @param  Schedule  $schedule
     * @return void
     */
    protected function bootEnabledSchedule(Schedule $schedule): void
    {
    }

    /**
     * Overwrite this method
     *
     * @param  Schedule  $schedule
     * @return void
     */
    protected function bootDisabledSchedule(Schedule $schedule): void
    {
    }

    /**
     * @return void
     */
    public function register()
    {
    }
}
