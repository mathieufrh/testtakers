<?php

use Storage;
use Illuminate\Database\Seeder;

class TestTakersJsonTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('test_takers')->delete();

        $json = Storage::get('json/testtakers.json');

        $data = json_decode($json, true);

        foreach ($data as $object) {
            TestTaker::create([
                'login'     => $object->login,
                'password'  => bcrypt($object->password),
                'title'     => $object->title,
                'lastname'  => $object->lastname,
                'firstname' => $object->firstname,
                'gender'    => $object->gender,
                'email'     => $object->email,
                'picture'   => $object->picture,
                'address'   => $object->address
            ]);
        }
    }
}
