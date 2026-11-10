<?php

namespace Database\Factories;

use App\Models\Newsletter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Newsletter> */
class NewsletterFactory extends Factory
{
    /** @return array{email: string} */
    public function definition(): array
    {
        return ['email' => fake()->unique()->safeEmail()];
    }
}
