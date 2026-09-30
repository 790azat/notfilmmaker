<?php

namespace App\Livewire\Auth;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** Пока нет ни одного пользователя, регистрация открыта (первый станет админом); дальше — по настройке. */
    public static function isOpen(): bool
    {
        try {
            return User::count() === 0 || (bool) Setting::get('registration_open', false);
        } catch (\Throwable) {
            return false;
        }
    }

    public function register()
    {
        abort_unless(static::isOpen(), 403, __('site.auth.closed'));

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create($data + ['is_admin' => User::count() === 0]);

        Auth::login($user, true);
        session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function render()
    {
        return view('livewire.auth.register', ['open' => static::isOpen(), 'first' => User::count() === 0])
            ->layout('layouts.auth', ['title' => __('site.auth.register_title')]);
    }
}
