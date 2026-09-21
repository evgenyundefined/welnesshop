<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Config\Repository as Config;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function __construct(private readonly Config $config) {}

    public function run(): void
    {
        // Создаётся, но не переписывается: пароль, сменённый в панели, не
        // должен откатываться к тому, что лежит в переменных окружения, —
        // иначе смена пароля после утечки отменяется ближайшим деплоем.
        // Забытый пароль восстанавливается командой admin:password.
        Admin::query()->firstOrCreate(
            ['email' => $this->config->string('shop.admin.email')],
            [
                'name' => $this->config->string('shop.admin.name'),
                'password' => $this->config->string('shop.admin.password'),
            ],
        );
    }
}
