<?php

namespace App\Livewire;

use Livewire\Component;

class MapTracker extends Component
{
    public function mount(): void
    {
        $user = auth()->user();

        abort_unless(
            in_array($user?->role, ['admin', 'recepcion', 'conductor'], true)
                || (method_exists($user, 'can') && $user->can('livewire.map')),
            403
        );
    }

    public function render()
    {
        return view('livewire.map-tracker');
    }
}

