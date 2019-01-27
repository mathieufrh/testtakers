<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TestTaker extends Model
{
    /**
     * The primary key for this table.
     *
     * @var string
     */
    protected $primaryKey = 'login';

    /**
     * The primary key is not a numeric key.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = ['password'];

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = ['login'];
}
