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
        'result_mode',
        'user_amount_limit',
        'symbol_limit',
    ];


    public function usersInGame(){
        return $this->hasMany(UserGameJoin::class);
    }


    public function users()
    {
        return $this->belongsToMany(User::class, 'user_game_joins', 'game_id', 'user_id');
    }

    public function gameLog() {
        return $this->hasOne(GameStatusLog::class, 'game_id', 'id');
    }
}
