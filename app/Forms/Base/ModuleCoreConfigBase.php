<?php

namespace Modules\SystemBase\app\Forms\Base;

use Illuminate\Http\Resources\Json\JsonResource;

class ModuleCoreConfigBase
{
    /**
     * @param  JsonResource  $jsonResource
     *
     * @return void
     */
    public function extendJsonResource(JsonResource $jsonResource): void
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