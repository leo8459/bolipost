<?php

namespace App\Livewire;

use Livewire\Component;

class MapTracker extends Component
{
    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'recepcion', 'conductor'], true), 403);
    }

    public function render()
    {
        return view('livewire.map-tracker');
    }
}

