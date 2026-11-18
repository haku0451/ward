<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class MakeAdminCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'make:admin
                            {--name= : 管理者の表示名}
                            {--email= : 管理者のメールアドレス}
                            {--password= : 管理者のパスワード}';

    /**
     * @var string
     */
    protected $description = '管理者ユーザーを作成します';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->stringOption('name') ?? $this->ask('Name');
        $email = $this->stringOption('email') ?? $this->ask('Email');
        $password = $this->stringOption('password') ?? $this->secret('Password');

        if ($this->stringOption('password') === null && $this->input->isInteractive()) {
            $confirmation = $this->secret('Confirm password');

            if ($password !== $confirmation) {
                $this->error('Passwords do not match.');

                return self::FAILURE;
            }
        }

        try {
            $validated = Validator::make(
                [
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                ],
                [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email', 'max:255'],
                    'password' => array_values(array_filter([
                        'required',
                        'string',
                        Password::defaults(),
                    ])),
                ],
            )->validate();
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $validated['email'])->first();

        if ($existing !== null) {
            if ($existing->isAdmin()) {
                $this->error('An administrator with this email already exists.');

                return self::FAILURE;
            }

            $existing->forceFill([
                'name' => $validated['name'],
                'password' => $validated['password'],
                'role' => UserRole::Admin,
            ])->save();

            AuditLog::record('user.promoted_to_admin', $existing, [
                'email' => $existing->email,
            ]);

            $this->info("Promoted {$existing->email} to administrator.");

            return self::SUCCESS;
        }

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::Admin,
        ]);

        AuditLog::record('user.admin_created', $user, [
            'email' => $user->email,
        ]);

        $this->info("Administrator created: {$user->email}");

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
