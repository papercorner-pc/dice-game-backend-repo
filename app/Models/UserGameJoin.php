<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserGameJoin extends Model
{
    use HasFactory;


    protected $fillable = ['user_id', 'game_id', 'joined_amount', 'user_card'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

   /* public function userGameLogs()
    {
        return $this->hasMany(UserGameLog::class, 'user_id', 'user_id');
    }*/

    public function userGameLogs()
    {
        return $this->hasMany(UserGameLog::class, 'game_join_id', 'id');
    }

}
