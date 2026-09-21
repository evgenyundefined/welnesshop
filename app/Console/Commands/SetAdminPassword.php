<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;

/**
 * Пароль администратора больше не переписывается при деплое, поэтому забытый
 * пароль нужно чем-то восстанавливать — вот этим.
 */
class SetAdminPassword extends Command
{
    protected $signature = 'admin:password {email} {password}';

    protected $description = 'Задать пароль администратору по адресу почты';

    public function handle(): int
    {
        $email = $this->argument('email');
        $admin = Admin::query()->where('email', $email)->first();

        if ($admin === null) {
            $this->error("Администратор {$email} не найден.");

            return self::FAILURE;
        }

        $admin->forceFill(['password' => $this->argument('password')])->save();

        $this->info("Пароль администратора {$email} изменён.");

        return self::SUCCESS;
    }
}
