<?php

namespace Modules\SystemBase\app\Events\Livewire;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\SystemBase\app\Http\Livewire\BaseComponent;

class BaseComponentActionCalled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var BaseComponent|null
     */
    public ?BaseComponent $baseComponent = null;

    /**
     * @var string
     */
    public string $action;

    /**
     * @var mixed also array is allowed
     */
    public mixed $itemId;

    /**
     * @param  BaseComponent  $baseComponent
     * @param  string         $action
     * @param  mixed          $itemId  also array is allowed
     */
    public function __construct(BaseComponent $baseComponent, string $action, mixed $itemId)
    {
        $this->baseComponent = $baseComponent;
        $this->action = $action;
        $this->itemId = $itemId;
    }
}
