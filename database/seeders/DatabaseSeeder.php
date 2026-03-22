<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        User::create(['name' => 'User 1', 'email' => 'user1@example.com']);
        User::create(['name' => 'User 2', 'email' => 'user2@example.com']);
        User::create(['name' => 'User 3', 'email' => 'user3@example.com']);
    }
}
