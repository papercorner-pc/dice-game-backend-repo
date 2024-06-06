<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\UserGameJoin;
use App\Models\UserGameLog;
use App\Models\UserJoinedGame;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Mockery\Exception;
use function Monolog\error;

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
                'game_id' => 'required|exists:games,id',
                'joined_amount' => 'required'
            ]);

            $game = Game::where('id', $data['game_id'])->first();
            $gameLimit = $game->entry_limit;
            $totelJoins = 0;
            $allUserJoinedGames = UserGameJoin::where('game_id', $data['game_id'])->get();

            foreach ($allUserJoinedGames as $allUserJoinedGame) {
                $totelJoins++;
            }

            if ($totelJoins >= $gameLimit) {
                return response()->json(['message' => 'Limit Exceeds , please join another game'], 400);
            }

            $user = Auth::user();
            $existingJoin = UserGameJoin::where('user_id', $user->id)
                ->where('game_id', $data['game_id'])
                ->first();

            if ($existingJoin) {
                return response()->json(['message' => 'You have already joined this game.'], 400);
            }

            $gameJoin = UserGameJoin::create([
                'user_id' => $user->id,
                'game_id' => $data['game_id'],
                'joined_amount' => $data['joined_amount']
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

    public function gameList(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validatedData = $request->validate([
            'type' => 'required',
        ]);
        $timeZone = 'Asia/Kolkata';
        $currentTime = Carbon::now($timeZone)->format('H:i:s');
        $currentDate = Carbon::now($timeZone)->format('Y-m-d');

        if ($validatedData['type'] == 'upcoming') {
            $games = Game::withCount('usersInGame')
                ->whereTime('start_time', '>=', $currentTime)
                ->whereDate('start_date', '>=', $currentDate)
                ->get();
            if ($games->isEmpty()) {
                return response()->json(['games' => [], 'message' => 'No upcoming games found'], 200);
            }
            return response()->json(['games' => $games->toArray(), 'message' => 'success'], 200);
        }

        if ($validatedData['type'] == 'live') {
            $userGameLogs = UserGameLog::where('user_id', $user->id)
                ->whereNull('game_status')
                ->get();

            if ($userGameLogs->isEmpty()) {
                return response()->json(['games' => [], 'message' => 'No live games found for user'], 200);
            }

            $games = [];
            foreach ($userGameLogs as $userGameLog) {
                $game = Game::withCount('usersInGame')->where('id', $userGameLog->game_id)->first();
                if ($game) {
                    $games[] = $game;
                }
            }

            if (empty($games)) {
                return response()->json(['games' => [], 'message' => 'No live games found for user'], 200);
            }

            return response()->json(['games' => array_map(function ($game) { return $game->toArray(); }, $games), 'message' => 'success'], 200);
        }

        if ($validatedData['type'] == 'completed') {
            $userGameLogs = UserGameLog::where('user_id', $user->id)
                ->whereNotNull('game_status')
                ->get();

            if ($userGameLogs->isEmpty()) {
                return response()->json(['games' => [], 'message' => 'No completed games found for user'], 200);
            }

            $games = [];
            foreach ($userGameLogs as $userGameLog) {
                $game = Game::withCount('usersInGame')->where('id', $userGameLog->game_id)->first();
                if ($game) {
                    $games[] = $game;
                }
            }

            if (empty($games)) {
                return response()->json(['games' => [], 'message' => 'No completed games found for user'], 200);
            }

            return response()->json(['games' => array_map(function ($game) { return $game->toArray(); }, $games), 'message' => 'success'], 200);
        }

        return response()->json(['message' => 'Invalid request type'], 400);
    }



    public function gameDetail(Request $request){
        $gameId = $request->game_id;
        if($gameId){
            $game = Game::where('id', $gameId)->first();
            if($game){
                return response()->json(['game' => $game->toArray(), 'message' => 'success'],200);
            }else{
                return response()->json(['game' => [], 'message' => 'error'],401);
            }
        }
    }


    public function filterGames(Request $request)
    {
        $validatedData = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);
        $query = Game::query();
        if (isset($validatedData['start_date'])) {
            $query->whereDate('start_date', '>=', Carbon::parse($validatedData['start_date']));
        }
        if (isset($validatedData['end_date'])) {
            $query->whereDate('start_date', '<=', Carbon::parse($validatedData['end_date']));
        }
        $games = $query->get();
        if ($games->isEmpty()) {
            return response()->json(['games' => [], 'message' => 'No games found'], 200);
        }
        return response()->json(['games' => $games->toArray(), 'message' => 'success'], 200);
    }

    public function searchGames(Request $request)
    {
        $validatedData = $request->validate([
            'match_name' => 'nullable|string',
            'min_fee' => 'nullable|numeric',
        ]);
        $query = Game::query();
        if (isset($validatedData['match_name'])) {
            $query->where('match_name', 'LIKE', '%' . $validatedData['match_name'] . '%');
        }
        if (isset($validatedData['min_fee'])) {
            $query->where('min_fee', $validatedData['min_fee']);
        }
        $games = $query->get();
        if ($games->isEmpty()) {
            return response()->json(['games' => [], 'message' => 'No games found'], 200);
        }
        return response()->json(['games' => $games->toArray(), 'message' => 'success'], 200);
    }


}
