<?php

namespace Database\Seeders;

use App\Models\File;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@admin.com',
        ]);

        $file = new File();
        $file->name = $user->email;
        $file->is_folder = true;
        $file->created_by = $user->id;
        $file->updated_by = $user->id;
        $file->makeRoot()->save();

        $users = User::factory(10)->create();
        $users->each(function ($user) {
            $file = new File();
            $file->name = $user->email;
            $file->is_folder = true;
            $file->created_by = $user->id;
            $file->updated_by = $user->id;
            $file->makeRoot()->save();
        });
    }
}
