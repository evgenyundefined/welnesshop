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
        Admin::query()->updateOrCreate(
            ['email' => $this->config->string('shop.admin.email')],
            [
                'name' => $this->config->string('shop.admin.name'),
                'password' => $this->config->string('shop.admin.password'),
            ],
        );
    }
}
