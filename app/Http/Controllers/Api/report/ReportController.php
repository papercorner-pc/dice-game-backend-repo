<?php

namespace App\Http\Controllers\Api\report;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use App\Models\UserGameJoin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function gameReportOld()
    {
        $user = Auth::user();
        $today = Carbon::today();

        if ($user->is_super_admin == 1) {
            $games = Game::with(['gameLog', 'adminEarning'])
                ->whereDate('created_at', $today)
                ->whereHas('gameLog', function($query){
                    $query->where('game_status', 1);
                })
                ->get();

            $totalGamesPlayed = $games->count();
            $totalInvestment = 0 ;
            $totalEarnings = 0;
            $totalLoss = 0;

            foreach ($games as $game){
                $totalInvestment += $game->adminEarning->game_investment;
                $totalEarnings += $game->adminEarning->game_total_earnings;
                $totalLoss += $game->adminEarning->game_total_loss;
            }

            $gameDetails = $games->map(function ($game) {
                $Investment = $game->adminEarning->game_investment ?? 0;
                $Earnings = $game->adminEarning->game_total_earnings ?? 0;
                $Loss = $game->adminEarning->game_total_loss ?? 0;

                return [
                    'game_id' => $game->id,
                    'game_name' => $game->name,
                    'total_investment' => $Investment,
                    'total_earnings' => $Earnings,
                    'total_loss' => $Loss,
                    'created_at' => $game->created_at->format('Y-m-d H:i:s'),
                ];
            });

            $totalData = [
                'total_games_played' => $totalGamesPlayed,
                'total_investment' => $totalInvestment,
                'total_earnings' => $totalEarnings,
                'total_loss' => $totalLoss,
            ];

            return response()->json([
                'game_report' => $gameDetails,
                'total_report' => $totalData,
            ]);

        } elseif ($user->is_agent == 1) {
            $agentUsers = User::where('created_by', $user->id)->pluck('id');
            $games = Game::whereHas('userGameLogs', function ($query) use ($agentUsers, $today) {
                $query->whereIn('user_id', $agentUsers)
                    ->whereDate('created_at', $today);
            })->with(['userGameLogs', 'gameStatusLog'])
                ->whereHas('gameStatusLog', function($query) {
                    $query->where('game_status', 1);
                })
                ->get();

            $totalGamesPlayed = $games->count();
            $totalInvestment = $games->sum(function ($game) {
                return $game->userGameLogs->sum('investment');
            });

            $totalEarnings = $games->sum(function ($game) {
                return $game->userGameLogs->sum('earning');
            });

            $totalLosses = $games->sum(function ($game) {
                return $game->userGameLogs->sum('loss');
            });

            $gameDetails = $games->map(function ($game) {
                $totalInvestment = $game->userGameLogs->sum('investment');
                $totalEarnings = $game->userGameLogs->sum('earning');
                $totalLosses = $game->userGameLogs->sum('loss');

                return [
                    'game_id' => $game->id,
                    'game_name' => $game->name,
                    'total_investment' => $totalInvestment,
                    'total_earnings' => $totalEarnings,
                    'total_losses' => $totalLosses,
                    'created_at' => $game->created_at->format('Y-m-d H:i:s'),
                    'status' => $game->gameStatusLog->status, // Assuming 'status' is stored in gameStatusLog
                ];
            });

            $totalData = [
                'total_games_played' => $totalGamesPlayed,
                'total_investment' => $totalInvestment,
                'total_earnings' => $totalEarnings,
                'total_losses' => $totalLosses
            ];


            return response()->json([
                'game_report' => $gameDetails,
                'total_report' => $totalData,
            ]);
        }

        return response()->json(['error' => 'Unauthorized'], 403);
    }

    public function gameReport()
    {
        $user = Auth::user();
        $today = Carbon::today();

        // For Super Admin
        if ($user->is_super_admin == 1) {
            $agentUsers = User::where('created_by', $user->id)->get();
            $response = [
                'total_game_join' => 0,
                'total_earning' => 0,
                'total_loss' => 0,
                'total_invest' => 0,
                'agents' => []
            ];

            foreach ($agentUsers as $agent) {
                $dealers = User::where('created_by', $agent->id)->get();

                $agentTotalGames = 0; // Initialize agent total game count
                $agentData = [
                    'name' => $agent->name,
                    'total_game_join' => 0,  // This will track the total game join for the agent
                    'total_invest' => 0,
                    'total_earning' => 0,
                    'total_loss' => 0,
                    'dealers' => []
                ];

                $totalAdminEarnings = 0;
                $totalAdminLoss = 0;

                foreach ($dealers as $dealer) {
                    $games = UserGameJoin::with(['userGameLogs', 'game', 'game.gameLog', 'game.adminEarning'])
                        ->where('user_id', $dealer->id)
                        ->whereHas('game', function ($query) use ($today) {
                            $query->whereDate('created_at', $today)
                                ->whereHas('gameLog', function ($query) {
                                    $query->where('game_status', 1);
                                });
                        })->get();

                    $totalGames = $games->count();
                    $totalInvestment = 0;
                    $totalLosses = 0;
                    $totalEarnings = 0;

                    foreach ($games as $game) {
                        $totalInvestment += $game->joined_amount;
                        foreach ($game->userGameLogs as $log) {
                            if ($log->game_earning <= 0) {
                                $totalLosses -= $game->joined_amount;
                            }else if($log->game_earning > 0){
                                $totalEarnings += $log->game_earning;
                            }
                        }

                        if ($game->game && $game->game->adminEarning) {
                            $totalAdminEarnings += $game->game->adminEarning->game_total_earnings;
                            $totalAdminLoss += $game->game->adminEarning->game_total_loss;
                        }
                    }

                    $dealerData = [
                        'name' => $dealer->name,
                        'total_game_join' => $totalGames,
                        'total_invest' => $totalInvestment,
                        'total_earning' => $totalEarnings,
                        'total_loss' => $totalLosses
                    ];

                    // Update agent totals
                    $agentData['total_game_join'] += $totalGames;
                    $agentData['total_invest'] += $totalInvestment;
                    $agentData['total_earning'] += $totalEarnings;
                    $agentData['total_loss'] += $totalLosses;

                    // Add dealer data to agent
                    $agentData['dealers'][] = $dealerData;
                }

                // Update overall totals in response
                $response['agents'][] = $agentData;
                $response['total_game_join'] += $agentData['total_game_join'];
                $response['total_invest'] += $agentData['total_invest'];
                $response['total_earning'] = $totalAdminEarnings;
                $response['total_loss'] = $totalAdminLoss;
            }


            return response()->json($response);
        }

        // For Agents
        if ($user->is_agent == 1) {
            $dealers = User::where('created_by', $user->id)->get();
            $response = [
                'name' => $user->name,
                'total_game_join' => 0,  // This will track the total game join for the agent
                'total_invest' => 0,
                'total_earning' => 0,
                'total_loss' => 0,
                'dealers' => []
            ];

            foreach ($dealers as $dealer) {
                $games = UserGameJoin::with(['userGameLogs', 'game', 'game.gameLog',])
                    ->where('user_id', $dealer->id)
                    ->whereHas('game', function ($query) use ($today) {
                        $query->whereDate('created_at', $today)
                            ->whereHas('gameLog', function ($query) {
                                $query->where('game_status', 1);
                            });
                    })->get();

                $totalGames = $games->count();
                $totalInvestment = 0;
                $totalLosses = 0;
                $totalEarnings = 0;

                foreach ($games as $game) {
                    $totalInvestment += $game->joined_amount;
                    foreach ($game->userGameLogs as $log) {
                        if ($log->game_earning <= 0) {
                            $totalLosses -= $game->joined_amount;
                        }else if($log->game_earning > 0){
                            $totalEarnings += $log->game_earning;
                        }
                    }
                }
                $dealerData = [
                    'name' => $dealer->name,
                    'total_game_join' => $totalGames,
                    'total_invest' => $totalInvestment,
                    'total_earning' => $totalEarnings,
                    'total_loss' => $totalLosses
                ];

                $response['total_game_join'] += $totalGames;
                $response['total_invest'] += $totalInvestment;
                $response['total_earning'] += $totalEarnings;
                $response['total_loss'] += $totalLosses;
                $response['dealers'][] = $dealerData;
            }

            return response()->json($response);
        }

        return response()->json(['error' => 'Unauthorized'], 403);
    }

}
