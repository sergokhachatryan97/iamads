<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class TableActionsDropdown extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public ?string $id = null,
        public ?string $trigger = null,
    ) {
        if ($this->id === null) {
            $this->id = 'dropdown-' . uniqid();
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.table-actions-dropdown');
    }
}


