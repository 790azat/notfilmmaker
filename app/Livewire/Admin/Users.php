<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Component;

class Users extends Component
{
    public function toggleAdmin(int $id): void
    {
        abort_if($id === auth()->id(), 400);
        $user = User::findOrFail($id);
        $user->update(['is_admin' => ! $user->is_admin]);
    }

    public function delete(int $id): void
    {
        abort_if($id === auth()->id(), 400);
        User::whereKey($id)->delete();
        $this->dispatch('toast', text: __('admin.users.deleted'));
    }

    public function render()
    {
        return view('livewire.admin.users', ['users' => User::orderByDesc('is_admin')->orderBy('name')->get()])
            ->layout('layouts.admin', ['title' => __('admin.nav.users')]);
    }
}
