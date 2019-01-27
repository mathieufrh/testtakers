<?php

namespace App;

use App\TestTaker;
use League\Csv\Reader;
use App\Http\Controllers\Controller;

class TestTakerCsv
{
    /**
     * Collection of TestTaker.
     *
     * @var  Illuminate\Database\Eloquent\Collection  $collection
     */
    protected $collection;

    /**
     * Create a new TestTakerCsv instance.
     *
     * Read the csv and get every records as an array then use it create a collection.
     *
     * @return void
     */
    public function __construct()
    {
        $csv = Reader::createFromPath(storage_path('app/csv/testtakers.csv'), 'r');

        $csv->setHeaderOffset(0);

        $array = iterator_to_array($csv->getRecords());

        $this->collection = TestTaker::hydrate($array);
    }

    /**
     * Return ever test takers.
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    public function all()
    {
        return $this->collection;
    }
}
