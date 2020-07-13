<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Lecture extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'lectures';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'class','teacher_id','subject_id','day','time_from','time_to','type','group'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at', 'updated_at',
    ];
}
