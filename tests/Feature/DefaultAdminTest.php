<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_admin_is_removed_and_registration_opens(): void
    {
        // Миграции уже прогнаны: дефолтный админ создан (000004) и удалён (000005).
        $this->assertSame(0, User::count());
        $this->get('/register')->assertOk();
    }
}
