<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminEarningLog extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'game_id', 'game_total_earnings', 'game_total_loss', 'game_investment'];

    public function game(){
        $this->hasOne(Game::class);
    }

    public function user(){
        $this->hasOne(User::class);
    }

}
