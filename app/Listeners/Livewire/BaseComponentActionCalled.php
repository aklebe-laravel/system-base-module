<?php

namespace Modules\SystemBase\app\Listeners\Livewire;

use Modules\SystemBase\app\Events\Livewire\BaseComponentActionCalled as SystemBaseBaseComponentActionCalled;

/**
 * Default listener, does nothing. Modules register their own listeners for this event.
 */
class BaseComponentActionCalled
{
    /**
     * @param  SystemBaseBaseComponentActionCalled  $event
     *
     * @return void
     */
    public function handle(SystemBaseBaseComponentActionCalled $event): void
    {
    }
}