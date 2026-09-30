<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class Profile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->name = auth()->user()->name;
        $this->email = auth()->user()->email;
    }

    public function saveProfile(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore(auth()->id())],
        ]);
        auth()->user()->update($data);
        $this->dispatch('toast', text: __('admin.saved'));
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        auth()->user()->update(['password' => Hash::make($this->password)]);
        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->dispatch('toast', text: __('admin.profile.password_changed'));
    }

    public function render()
    {
        return view('livewire.admin.profile')->layout('layouts.admin', ['title' => __('admin.nav.profile')]);
    }
}
