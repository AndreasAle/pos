<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:admin {email?} {--role=super_admin}';

    protected $description = 'Buat atau perbarui akun tim Yoka yang terpisah dari akun toko';

    public function handle(): int
    {
        $email = strtolower((string) ($this->argument('email') ?: $this->ask('Email')));
        $role = (string) $this->option('role');
        $name = (string) $this->ask('Nama lengkap');
        $password = (string) $this->secret('Password minimal 12 karakter');

        $validator = Validator::make(compact('email', 'role', 'name', 'password'), [
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in(['super_admin', 'support', 'finance'])],
            'name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        PlatformAdmin::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password), 'role' => $role, 'is_active' => true]
        );

        $this->info("Akun platform {$email} siap digunakan di /platform/login.");

        return self::SUCCESS;
    }
}
