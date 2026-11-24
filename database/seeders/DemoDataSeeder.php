<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Seeder;

/**
 * Generates made-up students for local development.
 * Never real data, and it refuses to run anywhere except a local environment.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            $this->command->error('Demo data only runs when APP_ENV=local.');

            return;
        }

        if (Student::query()->exists()) {
            $this->command->warn('Students already exist; skipping demo data.');

            return;
        }

        foreach (['Grade 6A', 'Grade 6B'] as $name) {
            $class = SchoolClass::firstOrCreate(['name' => $name, 'school_year' => '2026-2027']);

            for ($i = 0; $i < 8; $i++) {
                Student::create([
                    'first_name' => fake()->firstName(),
                    'last_name' => fake()->lastName(),
                    'date_of_birth' => fake()->dateTimeBetween('-12 years', '-10 years')->format('Y-m-d'),
                    'school_class_id' => $class->id,
                ]);
            }
        }

        $this->command->info('Created 2 classes with 8 randomly generated students each.');
    }
}
