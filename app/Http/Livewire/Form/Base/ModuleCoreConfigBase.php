<?php

namespace Modules\SystemBase\app\Http\Livewire\Form\Base;

use Illuminate\Http\Resources\Json\JsonResource;

class ModuleCoreConfigBase
{
    /**
     * @param  JsonResource  $dataSource
     *
     * @return void
     */
    public function extendDataSource(JsonResource $dataSource): void
    {
    }

    /**
     * @return array
     */
    public function getTabPages(): array
    {
        return [];
    }
}