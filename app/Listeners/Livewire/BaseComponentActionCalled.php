<?php

namespace Modules\SystemBase\app\Listeners\Livewire;

use Modules\SystemBase\app\Events\Livewire\BaseComponentActionCalled as SystemBAseBaseComponentActionCalled;

class BaseComponentActionCalled
{
    public function handle(SystemBAseBaseComponentActionCalled $event): void
    {
    }
}