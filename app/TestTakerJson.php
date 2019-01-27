<?php

namespace App;

use Storage;

class TestTakerJson
{
    /**
     * Collection of TestTaker.
     *
     * @var  Illuminate\Database\Eloquent\Collection  $collection
     */
    protected $collection;

    /**
     * Create a new TestTakerJson instance.
     *
     * Simply get read the file, decode it as JSON and create a collection with it.
     *
     * @return void
     */
    public function __construct()
    {
        $json = Storage::get('json/testtakers.json');

        $this->collection = TestTaker::hydrate(json_decode($json, true));
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
