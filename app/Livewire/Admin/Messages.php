<?php

namespace App\Livewire\Admin;

use App\Models\Message;
use Livewire\Component;
use Livewire\WithPagination;

class Messages extends Component
{
    use WithPagination;

    public ?int $openId = null;

    public function open(int $id): void
    {
        $this->openId = $this->openId === $id ? null : $id;
        Message::whereKey($id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function toggleRead(int $id): void
    {
        $m = Message::findOrFail($id);
        $m->update(['read_at' => $m->read_at ? null : now()]);
    }

    public function delete(int $id): void
    {
        Message::whereKey($id)->delete();
        $this->dispatch('toast', text: __('admin.messages.deleted'));
    }

    public function render()
    {
        return view('livewire.admin.messages', ['messages' => Message::latest()->paginate(20)])
            ->layout('layouts.admin', ['title' => __('admin.nav.messages')]);
    }
}
