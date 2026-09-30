<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Login extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = true;

    public function login()
    {
        $this->validate();

        $key = Str::lower($this->email).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', __('site.auth.throttle', ['seconds' => RateLimiter::availableIn($key)]));

            return null;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key, 60);
            $this->addError('email', __('site.auth.failed'));

            return null;
        }

        RateLimiter::clear($key);
        session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.login', ['canRegister' => Register::isOpen()])
            ->layout('layouts.auth', ['title' => __('site.nav.login')]);
    }
}
