<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminEarningLog;
use App\Models\Game;
use App\Models\GameStatusLog;
use App\Models\User;
use App\Models\UserGameJoin;
use App\Models\UserGameLog;
use App\Services\SendNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery\Exception;
use function Monolog\error;
use function Nette\Utils\data;

class GameController extends Controller
{

    public function createGame(Request $request)
    {
        try {
            $data = $request->validate([
                'match_name' => 'required|string',
                'min_fee' => 'required|numeric',
                'entry_limit' => 'nullable|integer',
                'user_limit' => 'nullable|integer',
                'symbol_limit' => 'nullable|integer',
            ]);


            $user = Auth::user();

            $game = Game::create([
                'match_name' => $data['match_name'],
                'min_fee' => $data['min_fee'],
                'user_amount_limit' => $data['user_limit'],
                'symbol_limit' => $data['symbol_limit'],
                'created_by' => $user->id,
                'entry_limit' => $data['entry_limit'],
            ]);


            $tempAgentTokenData = [];
            $gameUsers = User::all();
            foreach ($gameUsers as $gameUser){
                if($gameUser->fcm_token){
                    $tempAgentTokenData['device_token'] = $gameUser->fcm_token;
                    $tempAgentTokenData['game_id'] = $game->id;
                    $tempAgentTokenData['game_type'] = 'game_created';
                }
            }

            if ($game) {

                $gameStatus = GameStatusLog::create([
                    'game_id' => $game->id,
                    'game_status' => 0
                ]);

                $notificationConfigs = [
                    'title' => 'New contest available now !!',
                    'body' => 'Check All Details For This Request In App',
                    'soundPlay' => true,
                    'show_in_foreground' => true,
                ];
                $fcmServiceObj = new SendNotification();
                if(isset($tempAgentTokenData['device_token'])){
                    $fcmServiceObj->sendPushNotification([$tempAgentTokenData['device_token']], $tempAgentTokenData, $notificationConfigs);
                }
                return response()->json(['message' => 'New game created successfully'], 200);
            } else {
                return response()->json(['message' => 'Something went wrong, please try again later'], 500);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 400);
        }
    }

    public function createGameOld(Request $request)
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


            $tempAgentTokenData = [];
            $gameUsers = User::all();
            foreach ($gameUsers as $gameUser){
                if($gameUser->fcm_token){
                    $tempAgentTokenData['device_token'] = $gameUser->fcm_token;
                    $tempAgentTokenData['game_id'] = $game->id;
                    $tempAgentTokenData['game_type'] = 'game_created';
                }
            }

            if ($game) {

                $gameStatus = GameStatusLog::create([
                    'game_id' => $game->id,
                    'game_status' => 0
                ]);

                $notificationConfigs = [
                    'title' => 'New contest available now !!',
                    'body' => 'Check All Details For This Request In App',
                    'soundPlay' => true,
                    'show_in_foreground' => true,
                ];
                $fcmServiceObj = new SendNotification();
                if(isset($tempAgentTokenData['device_token'])){
                    $fcmServiceObj->sendPushNotification([$tempAgentTokenData['device_token']], $tempAgentTokenData, $notificationConfigs);
                }
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
                'joined_amount' => 'required|numeric|min:0',
                'user_card' => 'required'
            ]);

            $user = Auth::user();
            $game = Game::find($data['game_id']);

            $totalJoins = UserGameJoin::where('game_id', $data['game_id'])->count();

            if ($totalJoins >= $game->entry_limit) {
                return response()->json(['message' => 'Limit exceeds, please join another game.'], 400);
            }

            if ($data['joined_amount'] < $game->min_fee) {
                return response()->json(['message' => 'Minimum amount ' . $game->min_fee . ' required.'], 400);
            }

            $userAmountLimit = $game->user_amount_limit;
            $symbolLimit = $game->symbol_limit;

            $existingJoins = UserGameJoin::where('user_id', $user->id)
                ->where('game_id', $data['game_id'])
                ->where('user_card', $data['user_card'])
                ->get();

            $allJoinedUsers = UserGameJoin::where('game_id', $data['game_id'])
                ->where('user_card', $data['user_card'])
                ->get();

            $userJoinedTotalAmount = 0;
            $usersCardLimit = 0;

            foreach ($existingJoins as $existingJoin) {
                $userJoinedTotalAmount += (float)$existingJoin->joined_amount;
            }

            foreach ($allJoinedUsers as $allJoinedUser){
                $usersCardLimit += (float)$existingJoin->joined_amount;
            }

            if($userJoinedTotalAmount + $data['joined_amount'] > $userAmountLimit){
                return response()->json(['status' => 'error', 'message' => 'User amount limit exceeds for this card'], 400);
            }

            if($usersCardLimit + $data['joined_amount'] > $symbolLimit){
                return response()->json(['status' => 'error', 'message' => 'User amount limit exceeds for this card'], 400);
            }


            $user->withdraw($data['joined_amount']);
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
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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
                $upcomingGames = Game::withCount('usersInGame')->with('gameLog')
                    ->whereHas('gameLog', function ($query) {
                        $query->where('game_status', 0);
                    })
                    ->orderBy('created_at', 'desc')
                    ->get();
                return response()->json(['games' => $upcomingGames->toArray(), 'message' => 'success', 'user_details' => $userGameDetails], 200);
            }

            if ($validatedData['type'] == 'completed') {
                $completedGames = Game::withCount('usersInGame')->with('gameLog')
                    ->whereHas('gameLog', function ($query) use ($currentDate) {
                        $query->where('game_status', 1)->whereDate('updated_at', $currentDate);
                    })
                    ->orderBy('created_at', 'desc')
                    ->get();

                return response()->json(['games' => $completedGames->toArray(), 'message' => 'success', 'user_details' => $userGameDetails], 200);
            }
        }

        if ($validatedData['type'] == 'upcoming') {
            $games = Game::with(['gameLog'])
                ->withCount('usersInGame')
                ->whereHas('gameLog', function ($query) {
                    $query->where('game_status', 0);
                })
                /*->where(function ($query) use ($currentDate, $currentTime) {
                    $query->whereDate('start_date', '>', $currentDate)
                        ->orWhere(function ($query) use ($currentDate, $currentTime) {
                            $query->whereDate('start_date', '=', $currentDate)
                                ->whereTime('start_time', '>', $currentTime);
                        });
                })*/
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
                ->whereDate('updated_at', $currentDate)
                ->whereNotNull('game_status')
                ->get();

            if ($userGameLogs->isEmpty()) {
                return response()->json(['games' => [], 'message' => 'No completed games found for user', 'user_details' => $userGameDetails], 200);
            }

            $gameIds = $userGameLogs->pluck('game_id')->unique();

            $games = Game::withCount('usersInGame')
                ->whereIn('id', $gameIds)
                ->get();

            if ($games->isEmpty()) {
                return response()->json(['games' => [], 'message' => 'No completed games found for user', 'user_details' => $userGameDetails], 200);
            }

            return response()->json(['games' => $games->toArray(), 'message' => 'success', 'user_details' => $userGameDetails], 200);
        }

        return response()->json(['message' => 'Invalid request type', 'user_details' => $userGameDetails], 400);
    }

    public function gameDetail(Request $request)
    {
        $gameId = $request->game_id;
        if ($gameId) {
            $game = Game::where('id', $gameId)->first();
            if ($game) {
                return response()->json(['game' => $game->toArray(), 'message' => 'success'], 200);
            } else {
                return response()->json(['game' => [], 'message' => 'error'], 401);
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

        $adminEarnings = '';

        $authUser = Auth::user();
        $agentCreatedUsers = User::where('created_by', $authUser->id)->pluck('id');

        if($authUser->is_super_admin == 1){
            $usersJoinedGames = UserGameJoin::with(['user', 'userGameLogs'])
                ->where('game_id', $validatedData['game_id'])
                ->get();
            $adminEarnings = AdminEarningLog::where('game_id', $validatedData['game_id'])->get();
        }else{
            $usersJoinedGames = UserGameJoin::with(['user', 'userGameLogs'])
                ->where('game_id', $validatedData['game_id'])
                ->whereIn('user_id', $agentCreatedUsers)
                ->get();
        }

        if ($usersJoinedGames->isEmpty()) {
            return response()->json(['message' => 'No users found for this game'], 200);
        }

        $userDetails = $usersJoinedGames->map(function ($usersJoinedGame) {
            $user = $usersJoinedGame->user;
            $userGameLogs = $usersJoinedGame->userGameLogs;

            $userAddress = $user->address;

            return [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_profile' => $userAddress ? Storage::url($userAddress->profile_image) : null,
                'user_phone' => $user->phone_number,
                'user_investment' => $usersJoinedGame->joined_amount,
                'user_card' => $usersJoinedGame->user_card,
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

        $joinedUsers = UserGameJoin::with('user')->where('game_id', $validatedData['game_id'])->get();

        $tempAgentTokenData = [];
        $totalGameInvestment = 0;
        $totalResultAmount = 0;

        foreach ($joinedUsers as $joinedUser) {
            if ($joinedUser->user && !empty($joinedUser->user->fcm_token)) {
                $tempAgentTokenData['device_token'] = $joinedUser->user->fcm_token;
                $tempAgentTokenData['game_id'] = $game->id;
                $tempAgentTokenData['game_type'] = 'game_published';
            }
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
            $userGameLog->game_join_id = $joinedUser->id;
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

        $notificationConfigs = [
            'title' => 'Result published !!',
            'body' => 'Check All Details For This Request In App',
            'soundPlay' => true,
            'show_in_foreground' => true,
        ];

        $fcmServiceObj = new SendNotification();

        if(isset($tempAgentTokenData['device_token'])){
            $fcmServiceObj->sendPushNotification([$tempAgentTokenData['device_token']], $tempAgentTokenData, $notificationConfigs);
        }

        return response()->json(['message' => 'Results announced successfully', 'admin_earnings_or_loss' => $adminEarningsOrLoss], 200);
    }

    public function singleGameDetail(Request $request)
    {
        try {
            $user = Auth::user();
            $game = Game::find($request->game_id);

            if (!$game) {
                return response()->json(['error' => 'Game not found'], 404);
            }

            $userGameList = UserGameJoin::where('game_id', $request->game_id)
                ->with('userGameLogs')
                ->get();

            $userTotalInvestment = 0;

            $gameStatus = GameStatusLog::where('game_id', $request->game_id)->first();

            $userEarnings = 0;
            $result = [];
            $userEarnings = 0;

            if ($gameStatus && $gameStatus->game_status == 1) {
                $userGameLogsQ = UserGameLog::where('game_id', $request->game_id)
                    ->where('user_id', $user->id);

                $userGameLogs = $userGameLogsQ->get();
                $userGameLogFirst = $userGameLogsQ->first();

                if ($userGameLogFirst && $userGameLogFirst->result_dice) {
                    $diceResults = json_decode($userGameLogFirst->result_dice, true);
                    $result = [
                        'dice_1' => $diceResults[0] ?? null,
                        'dice_2' => $diceResults[1] ?? null,
                        'dice_3' => $diceResults[2] ?? null,
                    ];
                }

                foreach ($userGameLogs as $userGameLog) {
                    $userEarnings += $userGameLog->game_earning;
                }
            }

            // Prepare the list data
            $userGameListData = [];
            foreach ($userGameList as $userGame) {
                $userTotalInvestment += $userGame->joined_amount;
                if ($userGame->userGameLogs->isNotEmpty()) {
                    foreach ($userGame->userGameLogs as $userGameLog) {
                        $userGameListData[] = [
                            'id' => $userGame->id,
                            'joined_amount' => $userGame->joined_amount,
                            'selected_card' => $userGame->user_card,
                            'game_earning' => $userGameLog->game_earning,
                        ];
                    }
                } else {
                    $userGameListData[] = [
                        'id' => $userGame->id,
                        'joined_amount' => $userGame->joined_amount,
                        'selected_card' => $userGame->user_card,
                        'game_earning' => 0,
                    ];
                }
            }

            return response()->json([
                'game' => [
                    'id' => $game->id,
                    'name' => $game->match_name,
                ],
                'userEarnings' => $userEarnings,
                'userGameList' => $userGameListData,
                'user_total_investment' => $userTotalInvestment,
                'result' => $result
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteGame(Request $request){
        $gameId = $request->game_id;
        $tempAgentTokenData= [];

        if($gameId){
            $game = Game::with(['gameLog', 'usersInGame'])->where('id', $gameId)->first();
            if($game){
                if($game->gameLog && $game->gameLog->game_status == 1){
                    return response()->json(['error' => 'Result already published'], 400);
                } else {
                    DB::beginTransaction();
                    try {
                        foreach ($game->usersInGame as $userGameJoin) {
                            $user = User::find($userGameJoin->user_id);
                            if ($user) {
                                $user->deposit($userGameJoin->joined_amount);
                                $tempAgentTokenData['device_token'] = $user->fcm_token;
                                $tempAgentTokenData['game_id'] = $game->id;
                                $tempAgentTokenData['game_type'] = 'game_deleted';
                            }
                        }

                        GameStatusLog::where('game_id', $gameId)->delete();
                        UserGameJoin::where('game_id', $gameId)->delete();
                        $game->delete();




                        $notificationConfigs = [
                            'title' => 'Game '.$game->match_name.' deleted by admin !!',
                            'body' => 'Check All Details For This Request In App',
                            'soundPlay' => true,
                            'show_in_foreground' => true,
                        ];

                        $fcmServiceObj = new SendNotification();
                        if(isset($tempAgentTokenData['device_token'])){
                            $fcmServiceObj->sendPushNotification([$tempAgentTokenData['device_token']], $tempAgentTokenData, $notificationConfigs);
                        }

                        DB::commit();
                        return response()->json(['message' => 'Game deleted successfully'], 200);
                    } catch (\Exception $e) {
                        DB::rollBack();
                        return response()->json(['error' => 'Failed to delete game data'], 500);
                    }
                }
            } else {
                return response()->json(['error' => 'Game not found'], 400);
            }
        } else {
            return response()->json(['error' => 'Game ID is required'], 400);
        }
    }

    public function editGame(Request $request) {
        $game = Game::with('gameLog')->find($request->game_id);
        if (!$game) {
            return response()->json(['message' => 'Game not found'], 404);
        }
        if ($game->gameLog && $game->gameLog->game_status == 1) {
            return response()->json(['message' => 'Game is already published and cannot be edited'], 400);
        }


        if ($request->has('match_name')) {
            $game->match_name = $request->match_name;
        }
        if ($request->has('min_fee')) {
            $game->min_fee = $request->min_fee;
        }
        if ($request->has('entry_limit')) {
            $game->entry_limit = $request->entry_limit;
        }
        if ($request->has('user_limit')) {
            $game->user_amount_limit = $request->user_limit;
        }
        if ($request->has('symbol_limit')) {
            $game->symbol_limit = $request->symbol_limit;
        }

        $game->save();
        return response()->json(['message' => 'Game updated successfully', 'game' => $game], 200);
    }

    private function transformDate($date) {
        $dateTime = \DateTime::createFromFormat('d/m/Y', $date);
        if ($dateTime) {
            return $dateTime->format('Y-m-d');
        } else {
            throw new \Exception("Invalid date format");
        }
    }

    public function gamePublishStatus(Request $request){
        $gameId = $request->game_id;
        $value = 0;
        if($request->is_publishable == true){
             $value = 1;
        }
        if($gameId){
            $gamePublishStatus = GameStatusLog::where('game_id',$gameId)->first();
            if($request->is_publishable){
                $gamePublishStatus->is_publishable = $value;
                $gamePublishStatus->save();
                return response()->json(['success' => 'Game status updated successfully', 'is_publishable' => $gamePublishStatus->is_publishable],200);
            }else{
                return response()->json(['success' => 'Game status fetched successfully', 'is_publishable' => $gamePublishStatus->is_publishable],200);
            }
        }else{
            return response()->json(['error' => 'Game id required'],400);
        }
    }

    public function getGameCardBalance(Request $request)
    {
        $gameId = $request->game_id;

        $balanceList = [
            1 => ['symbol' => 'Heart', 'balance' => 0, 'joins' => 0],
            2 => ['symbol' => 'Ace', 'balance' => 0, 'joins' => 0],
            3 => ['symbol' => 'Claver', 'balance' => 0, 'joins' => 0],
            4 => ['symbol' => 'Diamond', 'balance' => 0, 'joins' => 0],
            5 => ['symbol' => 'Moon', 'balance' => 0, 'joins' => 0],
            6 => ['symbol' => 'Flag', 'balance' => 0, 'joins' => 0]
        ];

        $game = Game::find($gameId);
        $cardLimit = $game->symbol_limit;

        $usersGameJoins = UserGameJoin::where('game_id', $gameId)->get();

        foreach ($balanceList as $card => $info) {
            $totalJoinedAmount = $usersGameJoins->where('user_card', $card)->sum('joined_amount');
            $joinsCount = $usersGameJoins->where('user_card', $card)->count();

            $balanceList[$card]['balance'] = $cardLimit - $totalJoinedAmount;
            $balanceList[$card]['joins'] = $joinsCount;
        }

        return response()->json([
            'game_id' => $gameId,
            'card_limit' => $cardLimit,
            'balances' => $balanceList
        ]);
    }

    public function deleteUserGameJoin(Request $request)
    {
        $type = $request->type;
        $gameId = $request->game_id;
        $card = $request->card;
        $user = Auth::user();

        $game = Game::find($gameId);
        if (!$game) {
            return response()->json(['error' => 'Game not found'], 400);
        }

        $userGameJoinQ = UserGameJoin::where('game_id', $gameId)->where('user_id', $user->id)->where('user_card', $card);

        if ($type == 'single') {
            $userLatestJoin = $userGameJoinQ->latest()->first();
            if ($userLatestJoin) {
                $joinedAmount = $userLatestJoin->joined_amount;
                $userLatestJoin->delete();
                $user->deposit($joinedAmount);
                return response()->json(['message' => 'Latest game join deleted and amount refunded successfully'], 200);
            } else {
                return response()->json(['error' => 'You are not joined in this game'], 400);
            }
        } elseif ($type == 'bulk') {
            $userJoins = $userGameJoinQ->get();
            if ($userJoins->isEmpty()) {
                return response()->json(['error' => 'You are not joined in this game'], 400);
            }

            $totalRefundAmount = $userJoins->sum('joined_amount');
            $userGameJoinQ->delete();
            $user->deposit($totalRefundAmount);

            return response()->json(['message' => 'All game joins deleted and amount refunded successfully'], 200);
        } else {
            return response()->json(['error' => 'Invalid type'], 400);
        }
    }


}
