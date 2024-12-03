## SystemBase Module

A **required** module for [Mercy Scaffold Application](https://github.com/aklebe-laravel/mercy-scaffold.git)
(or any based on it like [Jumble Sale](https://github.com/aklebe-laravel/jumble-sale.git)).

This module provide the most basic operations for modules and themes.

### Config
See ```Config/config.php``` for supported env vars.

```Modules/*/Config/message-boxes.php``` can created by any module and provides content for messages boxes in php and/or javascript. All model configs will be merged.

```Modules/*/Config/seeders.php``` can created by any module and provides adjustments for seeding. All model configs will be merged.

```Modules/*/Config/module-deploy-env.php```can created by any module and provides tasks to deploy any data in db or files. 

### Providers

#### ModuleBaseServiceProvider
Provides a base provider all module providers can extend from. Prepares module for configs, views and translations.
Note for all modules:
- all configs ```Modules/*/Config/module-deploy-env.php``` will be merged
- all configs ```Modules/*/Config/message-boxes.php``` will be merged
- all configs ```Modules/*/Config/seeders.php``` will be merged

#### ScheduleBaseServiceProvider
Provides modules scheduler methods like ```bootEnabledSchedule``` and ```bootDisabledSchedule```.
The whole scheduling process can be disabled by config ```system-base.schedule.enabled```  / env ```SCHEDULE_ENABLED```.



### Livewire

#### \Modules\SystemBase\app\Http\Livewire\BaseComponent
This base component can be extended by all livewire components.

