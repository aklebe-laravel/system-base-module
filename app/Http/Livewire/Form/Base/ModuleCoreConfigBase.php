<?php

namespace Modules\SystemBase\app\Http\Livewire\Form\Base;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base for module specific parts of the core config form. Overwrite the methods.
 */
class ModuleCoreConfigBase
{
    /**
     * Add module specific data to the form data source.
     *
     * @param  JsonResource  $dataSource
     *
     * @return void
     */
    public function extendDataSource(JsonResource $dataSource): void
    {
    }

    /**
     * Additional tab pages for the form.
     *
     * @return array
     */
    public function getTabPages(): array
    {
        return [];
    }
}