<?php

use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;
class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create();
        foreach (range(1,10) as $index) {
            DB::table('permissions')->insert([
                'id' => $index,
                'name'=> $faker->streetName,
                'description' => $faker->address
            ]);
        }
        $this->call(PermissionSeeder::class);
    }
}
