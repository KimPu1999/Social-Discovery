<?php

namespace Database\Seeders;

use App\Models\Interest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InterestSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Technology' => 'Learning', 'Music' => 'Creative', 'Travel' => 'Lifestyle', 'Gaming' => 'Hobbies', 'Photography' => 'Creative', 'Art' => 'Creative', 'Business' => 'Career', 'Education' => 'Learning', 'Sports' => 'Wellness', 'Food' => 'Lifestyle'] as $name => $category) {
            Interest::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'category' => $category, 'is_active' => true]);
        }
    }
}
