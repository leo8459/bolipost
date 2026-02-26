<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;

class Users extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $newPassword = '';
    public $userIdBeingUpdated = null;

    protected $paginationTheme = 'bootstrap';

    public function searchUsers()
    {
        $this->searchQuery = $this->search;
        $this->resetPage();
    }

    public function setPasswordUser($userId)
    {
        $this->userIdBeingUpdated = $userId;
        $this->dispatch('openModal');
    }

    public function updatePassword()
    {
        $this->validate([
            'newPassword' => 'required|min:6',
        ]);

        $user = User::find($this->userIdBeingUpdated);

        if ($user) {
            $user->password = Hash::make($this->newPassword);
            $user->save();
            session()->flash('success', 'Contrasena actualizada correctamente.');
        } else {
            session()->flash('error', 'Usuario no encontrado.');
        }

        $this->newPassword = '';
        $this->userIdBeingUpdated = null;

        $this->dispatch('closeModal');
    }

    public function render()
    {
        $users = User::withTrashed()
            ->with(['empresa', 'roles'])
            ->where(function ($query) {
                $query->where('name', 'like', '%' . $this->searchQuery . '%')
                    ->orWhere('email', 'like', '%' . $this->searchQuery . '%')
                    ->orWhereHas('empresa', function ($empresaQuery) {
                        $empresaQuery->where('nombre', 'like', '%' . $this->searchQuery . '%')
                            ->orWhere('sigla', 'like', '%' . $this->searchQuery . '%')
                            ->orWhere('codigo_cliente', 'like', '%' . $this->searchQuery . '%');
                    });
            })
            ->paginate(10);

        return view('livewire.users', ['users' => $users]);
    }
}
