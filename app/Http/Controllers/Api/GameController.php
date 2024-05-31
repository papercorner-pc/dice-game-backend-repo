<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\UserGameJoin;
use App\Models\UserJoinedGame;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class GameController extends Controller
{

    public function createGame(Request $request)
    {
        try {
            $data = $request->validate([
                'match_name' => 'required|string',
                'min_fee' => 'required|numeric',
                'start_time' => 'required|date_format:H:i:s',
                'start_date' => 'required|date_format:d/m/Y',
                'end_time' => 'nullable|date_format:H:i:s',
                'end_date' => 'nullable|date_format:d/m/Y',
                'entry_limit' => 'nullable|integer',
                'result_mode' => 'required|string'
            ]);

            $user = Auth::user();

            $game = Game::create([
                'match_name' => $data['match_name'],
                'min_fee' => $data['min_fee'],
                'start_time' => $data['start_time'],
                'start_date' => Carbon::createFromFormat('d/m/Y', $data['start_date'])->format('Y-m-d'),
                'end_time' => $data['end_time'] ?? null,
                'end_date' => isset($data['end_date']) ? Carbon::createFromFormat('d/m/Y', $data['end_date'])->format('Y-m-d') : null,
                'created_by' => $user->id,
                'entry_limit' => $data['entry_limit'],
                'result_mode' => $data['result_mode']
            ]);

            if ($game) {
                return response()->json(['message' => 'New game created successfully'], 200);
            } else {
                return response()->json(['message' => 'Something went wrong, please try again later'], 500);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 400);
        }
    }


    public function joinGame(Request $request)
    {
        try {
            $data = $request->validate([
                'game_id' => 'required|exists:games,id'
            ]);

            $user = Auth::user();
            $existingJoin = UserGameJoin::where('user_id', $user->id)
                ->where('game_id', $data['game_id'])
                ->first();

            if ($existingJoin) {
                return response()->json(['message' => 'You have already joined this game.'], 400);
            }

            $gameJoin = UserGameJoin::create([
                'user_id' => $user->id,
                'game_id' => $data['game_id']
            ]);

            if ($gameJoin) {
                return response()->json(['message' => 'Successfully joined the game.'], 200);
            } else {
                return response()->json(['message' => 'Something went wrong, please try again later.'], 500);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 400);
        }
    }



}
