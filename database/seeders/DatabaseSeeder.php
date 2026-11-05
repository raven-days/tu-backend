<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
public function run(): void
{
    User::updateOrCreate(
        ['email' => 'tu@sman104.test'],
        ['name' => 'Petugas TU', 'username' => 'tu', 'password' => 'initu', 'role' => 'tu']
    );
}
}