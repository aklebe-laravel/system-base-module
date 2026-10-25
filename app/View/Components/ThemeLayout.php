<?php

namespace Modules\SystemBase\app\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Blade component rendering the theme layout 'layouts.master'.
 */
class ThemeLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     *
     * @return View
     */
    public function render()
    {
        return view('layouts.master');
    }
}
