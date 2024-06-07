<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserGameLog extends Model
{
    use HasFactory;

    protected $fillable = ['user_id','game_id', 'game_status', 'game_earning', 'result_dice'];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }
}

