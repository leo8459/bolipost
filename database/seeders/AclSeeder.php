<?php

namespace Database\Seeders;

use App\Support\AclRoleManager;
use Illuminate\Database\Seeder;

class AclSeeder extends Seeder
{
    public function run(): void
    {
        AclRoleManager::sync();
    }
}
