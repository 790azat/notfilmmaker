<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Разговор из чата на сайте: посетителя узнаём по случайному sid из его браузера.
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->string('sid', 40)->unique();
            $table->string('page', 200)->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        // f: 'v' — посетитель, 'a' — владелец (ответ из Telegram).
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
            $table->char('f', 1);
            $table->text('body');
            $table->boolean('delivered')->default(false);
            $table->timestamps();
        });

        // Какое сообщение бота в Telegram к чему относится: reply на него уходит этому адресату.
        Schema::create('telegram_links', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('tg_chat_id');
            $table->bigInteger('tg_message_id');
            $table->string('target', 60);
            $table->timestamps();
            $table->index(['tg_chat_id', 'tg_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_links');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chats');
    }
};
