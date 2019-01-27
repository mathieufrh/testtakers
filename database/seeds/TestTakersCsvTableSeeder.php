<?php

use Illuminate\Database\Seeder;

class TestTakersCsvTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('test_takers')->delete();

        $csv = Reader::createFromPath(storage_path('app/csv/testtakers.csv'), 'r');

        $csv->setHeaderOffset(0);

        $data = $csv->getRecords();

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
