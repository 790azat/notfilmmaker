<?php

namespace App\Livewire;

use App\Models\Message;
use App\Models\Setting;
use App\Models\User;
use App\Support\Telegram;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Throwable;

class Contact extends Component
{
    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('required|email|max:190')]
    public string $email = '';

    #[Validate('nullable|string|max:40')]
    public string $phone = '';

    #[Validate('nullable|string|max:60')]
    public string $project_type = '';

    #[Validate('required|string|min:10|max:5000')]
    public string $body = '';

    /** Ловушка для ботов: люди это поле не видят. */
    public string $website = '';

    public bool $sent = false;

    public function send(): void
    {
        $this->validate();

        if ($this->website !== '') {
            $this->sent = true;

            return;
        }

        $key = 'contact:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('body', __('site.auth.throttle', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }
        RateLimiter::hit($key, 3600);

        $message = Message::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'project_type' => $this->project_type ?: null,
            'body' => $this->body,
            'locale' => app()->getLocale(),
        ]);

        // Письмо владельцу, если настроена почта. Сообщение в любом случае лежит в админке.
        $to = Setting::get('email') ?: User::where('is_admin', true)->value('email');
        if ($to && config('mail.default') !== 'log') {
            try {
                Mail::raw("{$message->name} <{$message->email}> {$message->phone}\n{$message->project_type}\n\n{$message->body}", function ($m) use ($to, $message) {
                    $m->to($to)->replyTo($message->email, $message->name)->subject('New inquiry: '.$message->name);
                });
            } catch (Throwable $e) {
                report($e);
            }
        }

        // И в Telegram владельцу, если бот подключён.
        Telegram::notify("✉️ Заявка с формы на сайте\n{$message->name}, {$message->email}".($message->phone ? ", {$message->phone}" : '').($message->project_type ? "\nТип: {$message->project_type}" : '')."\n\n{$message->body}", 'mail:'.$message->id);

        $this->reset(['name', 'email', 'phone', 'project_type', 'body']);
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.contact')->layout('layouts.site', ['title' => __('site.contact.title')]);
    }
}
