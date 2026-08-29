<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdmin extends Command
{
    protected $signature = 'kuysender:admin {--username=} {--name=}';
    protected $description = 'Create or reset a KuySender administrator without insecure default credentials.';

    public function handle(): int
    {
        $username = trim((string) ($this->option('username') ?: $this->ask('Username', 'admin')));
        $name = trim((string) ($this->option('name') ?: $this->ask('Display name', 'Administrator')));
        if (!preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username)) {
            $this->error('Username must be 3-64 characters and use letters, numbers, dot, underscore or hyphen.');
            return self::FAILURE;
        }
        $password = (string) $this->secret('Password (minimum 12 characters)');
        $confirm = (string) $this->secret('Confirm password');
        if (strlen($password) < 12 || !hash_equals($password, $confirm)) {
            $this->error('Passwords do not match or are shorter than 12 characters.');
            return self::FAILURE;
        }
        User::updateOrCreate(['username' => $username], [
            'name' => $name ?: Str::headline($username),
            'role' => 'admin',
            'password' => Hash::make($password),
        ]);
        $this->info('Administrator account is ready.');
        return self::SUCCESS;
    }
}
