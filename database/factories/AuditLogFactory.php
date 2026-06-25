<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => $this->faker->randomElement(['auth.login', 'smtp.updated', 'user.created']),
            'category' => $this->faker->randomElement(['auth', 'settings', 'users', 'security', 'app']),
            'target_type' => null,
            'target_id' => null,
            'ip' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'meta' => null,
        ];
    }
}
