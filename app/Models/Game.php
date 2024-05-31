<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    use HasFactory;


    /**
     * @var array
     */
    protected $fillable = [
        'match_name',
        'min_fee',
        'start_time',
        'start_date',
        'end_time',
        'end_date',
        'created_by',
        'entry_limit',
        'result_mode'
    ];
}
