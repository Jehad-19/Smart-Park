<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[AsCommand(name: 'make:filament-user')]
class MakeFilamentUser extends Command
{
    protected $description = 'Create a new Filament admin user (creates an Admin record)';

    protected $signature = 'make:filament-user
                            {--name= : The name of the user}
                            {--email= : A valid and unique email address}
                            {--password= : The password for the user (min. 8 characters)}';

    protected array $options;

    protected function getUserData(): array
    {
        return [
            'name' => $this->options['name'] ?? text(
                label: 'Name',
                required: true,
            ),

            'email' => $this->options['email'] ?? text(
                label: 'Email address',
                required: true,
                validate: fn(string $email): ?string => match (true) {
                    ! filter_var($email, FILTER_VALIDATE_EMAIL) => 'The email address must be valid.',
                    Admin::where('email', $email)->exists() => 'An admin with this email address already exists',
                    default => null,
                },
            ),

            'password' => Hash::make($this->options['password'] ?? password(
                label: 'Password',
                required: true,
            )),
            'employee_number' => $this->resolveEmployeeNumber(),
        ];
    }

    protected function resolveEmployeeNumber(): string
    {
        $provided = $this->options['employee_number'] ?? null;

        if ($provided) {
            if (Admin::where('employee_number', $provided)->exists()) {
                $this->error('An admin with that employee_number already exists.');
                exit(1);
            }

            return $provided;
        }

        // Generate a unique employee number
        do {
            $candidate = 'EMP' . now()->format('YmdHis') . rand(10, 99);
        } while (Admin::where('employee_number', $candidate)->exists());

        return $candidate;
    }

    protected function createUser(): Authenticatable
    {
        return Admin::create($this->getUserData());
    }

    protected function sendSuccessMessage(Authenticatable $user): void
    {
        $loginUrl = Filament::getLoginUrl();

        $this->components->info('Success! ' . ($user->getAttribute('email') ?? $user->getAttribute('username') ?? 'You') . " may now log in at {$loginUrl}");
    }

    public function handle(): int
    {
        $this->options = $this->options();

        if (! Filament::getCurrentPanel()) {
            $this->error('Filament has not been installed yet: php artisan filament:install --panels');

            return static::INVALID;
        }

        $user = $this->createUser();
        $this->sendSuccessMessage($user);

        return static::SUCCESS;
    }
}
