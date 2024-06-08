<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminEarningLog;
use App\Models\Game;
use App\Models\GameStatusLog;
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

                $gameStatus = GameStatusLog::create([
                    'game_id' => $game->id,
                    'game_status' => 0
                ]);
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
                'joined_amount' => 'required',
                'user_card' => 'required'
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
                'joined_amount' => $data['joined_amount'],
                'user_card' => $data['user_card']
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
        $currentDateTime = Carbon::now($timeZone);
        $currentDate = $currentDateTime->format('Y-m-d');
        $currentTime = $currentDateTime->format('H:i:s');

        $userGame = UserGameJoin::where('user_id', $user->id)->get();
        $userGameLogs = UserGameLog::where('user_id', $user->id)->get();
        $userGameEarning = $userGameLogs->sum('game_earning');

        $userGameDetails = [
            'total_joined_game' => $userGame->count(),
            'total_win' => $userGameLogs->where('game_status', 1)->count(),
            'total_loss' => $userGameLogs->where('game_status', 0)->count(),
            'user_name' => $user->name,
            'total_earning' => $userGameEarning,
        ];

        if ($user->is_super_admin == 1) {
            if ($validatedData['type'] == 'upcoming') {
                $upcomingGames = Game::with('gameLog')
                    ->whereHas('gameLog', function ($query) {
                        $query->where('game_status', 0);
                    })
                    ->orderBy('created_at', 'desc')
                    ->get();
                return response()->json(['games' => $upcomingGames->toArray(), 'message' => 'success', 'user_details' => $userGameDetails], 200);
            }

            if ($validatedData['type'] == 'completed') {
                $completedGames = Game::with('gameLog')
                    ->whereHas('gameLog', function ($query) {
                        $query->where('game_status', 1);
                    })
                    ->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['games' => $completedGames->toArray(), 'message' => 'success', 'user_details' => $userGameDetails], 200);
            }
        }

        if ($validatedData['type'] == 'upcoming') {
            $joinedGameIds = $user->games()->pluck('game_id')->toArray();

            $games = Game::withCount('usersInGame')
                ->where(function ($query) use ($currentDate, $currentTime) {
                    $query->whereDate('start_date', '>', $currentDate)
                        ->orWhere(function ($query) use ($currentDate, $currentTime) {
                            $query->whereDate('start_date', '=', $currentDate)
                                ->whereTime('start_time', '>', $currentTime);
                        });
                })
                ->whereNotIn('id', $joinedGameIds)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($games->isEmpty()) {
                return response()->json(['games' => [], 'message' => 'No upcoming games found', 'user_details' => $userGameDetails], 200);
            }

            return response()->json(['games' => $games->toArray(), 'message' => 'success', 'user_details' => $userGameDetails], 200);
        }

        if ($validatedData['type'] == 'live') {
            $userJoinedGames = UserGameJoin::where('user_id', $user->id)->pluck('game_id');

            $liveGameLogs = UserGameLog::whereIn('game_id', $userJoinedGames)
                ->whereNull('game_status')
                ->get();

            $liveGameIds = $userJoinedGames->filter(function ($gameId) {
                return !UserGameLog::where('game_id', $gameId)
                    ->whereNotNull('game_status')
                    ->exists();
            });

            if ($liveGameIds->isEmpty()) {
                return response()->json(['games' => [], 'message' => 'No live games found for user', 'user_details' => $userGameDetails], 200);
            }

            $games = Game::withCount('usersInGame')
                ->whereIn('id', $liveGameIds)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($games->isEmpty()) {
                return response()->json(['games' => [], 'message' => 'No live games found for user', 'user_details' => $userGameDetails], 200);
            }

            return response()->json(['games' => $games->toArray(), 'message' => 'success', 'user_details' => $userGameDetails], 200);
        }

        if ($validatedData['type'] == 'completed') {
            $userGameLogs = UserGameLog::where('user_id', $user->id)
                ->whereNotNull('game_status')
                ->get();

            if ($userGameLogs->isEmpty()) {
                return response()->json(['games' => [], 'message' => 'No completed games found for user', 'user_details' => $userGameDetails], 200);
            }

            $games = [];
            foreach ($userGameLogs as $userGameLog) {
                $game = Game::withCount('usersInGame')->where('id', $userGameLog->game_id)->first();
                if ($game) {
                    $games[] = $game;
                }
            }

            if (empty($games)) {
                return response()->json(['games' => [], 'message' => 'No completed games found for user', 'user_details' => $userGameDetails], 200);
            }

            return response()->json(['games' => array_map(function ($game) {
                return $game->toArray();
            }, $games), 'message' => 'success', 'user_details' => $userGameDetails], 200);
        }

        return response()->json(['message' => 'Invalid request type', 'user_details' => $userGameDetails], 400);
    }




public function gameDetail(Request $request)
{
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


    public function userGameList(Request $request)
    {
        $validatedData = $request->validate([
            'game_id' => 'required'
        ]);

        $usersJoinedGames = UserGameJoin::with(['user', 'userGameLogs'])
            ->where('game_id', $validatedData['game_id'])
            ->get();

        if ($usersJoinedGames->isEmpty()) {
            return response()->json(['message' => 'No users found for this game'], 200);
        }

        $adminEarnings = AdminEarningLog::where('game_id', $validatedData['game_id'])->get();

        $userDetails = $usersJoinedGames->map(function ($usersJoinedGame) {
            $user = $usersJoinedGame->user;
            $userGameLogs = $usersJoinedGame->userGameLogs;

            return [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_profile' => 'https://fastly.picsum.photos/id/22/367/267.jpg?hmac=YbcBwpRX0XOz9EWoQod59ulBNUEf18kkyqFq0Mikv6c',
                'user_phone' => $user->phone_number,
                'user_investment' => $usersJoinedGame->joined_amount,
                'game_earning' => $userGameLogs->sum('game_earning'),
            ];

        });

        return response()->json(['users' => $userDetails->toArray(), 'admin_earnings' => $adminEarnings, 'message' => 'success'], 200);
    }

    public function announceResult(Request $request)
    {
        $validatedData = $request->validate([
            'game_id' => 'required|integer',
            'dice_1' => 'required|integer',
            'dice_2' => 'required|integer',
            'dice_3' => 'required|integer',
        ]);

        $user = Auth::user();

        $game = Game::find($validatedData['game_id']);
        if (!$game) {
            return response()->json(['message' => 'Game not found'], 404);
        }

        $existingResult = UserGameLog::where('game_id', $validatedData['game_id'])->exists();
        if ($existingResult) {
            return response()->json(['message' => 'Results have already been announced for this game'], 400);
        }

        $joinedUsers = UserGameJoin::where('game_id', $validatedData['game_id'])->get();

        $totalGameInvestment = 0;
        $totalResultAmount = 0;

        foreach ($joinedUsers as $joinedUser) {
            $userGameLog = new UserGameLog();
            $userCard = $joinedUser->user_card;
            $investment = $joinedUser->joined_amount;
            $totalGameInvestment += $investment;

            $matches = 0;
            $dices = [$validatedData['dice_1'], $validatedData['dice_2'], $validatedData['dice_3']];

            foreach ($dices as $dice) {
                if ($userCard == $dice) {
                    $matches++;
                }
            }

            if ($matches == 3) {
                $earnings = 3 * $investment + $investment;
            } elseif ($matches == 2) {
                $earnings = 2 * $investment + $investment;
            } elseif ($matches == 1) {
                $earnings = 1 * $investment + $investment;
            } else {
                $earnings = 0;
            }

            $totalResultAmount += $earnings;

            $userGameLog->user_id = $joinedUser->user_id;
            $userGameLog->game_id = $validatedData['game_id'];
            $userGameLog->game_earning = $earnings;
            $userGameLog->game_status = $matches > 0 ? 1 : 0;
            $userGameLog->result_dice = json_encode($dices);
            $userGameLog->save();
        }

        $adminEarningsOrLoss = $totalGameInvestment - $totalResultAmount;

        $adminLog = new AdminEarningLog();
        $adminLog->user_id = $user->id;
        $adminLog->game_id = $validatedData['game_id'];
        $adminLog->game_investment = $totalGameInvestment;

        if ($adminEarningsOrLoss >= 0) {
            $adminLog->game_total_earnings = $adminEarningsOrLoss;
            $adminLog->game_total_loss = 0;
        } else {
            $adminLog->game_total_earnings = 0;
            $adminLog->game_total_loss = abs($adminEarningsOrLoss);
        }

        $adminLog->save();

        $gameLog = GameStatusLog::where('game_id', $validatedData['game_id'])->first();
        if ($gameLog) {
            $gameLog->update(['game_status' => 1]);
        }

        return response()->json(['message' => 'Results announced successfully', 'admin_earnings_or_loss' => $adminEarningsOrLoss], 200);
    }

}
