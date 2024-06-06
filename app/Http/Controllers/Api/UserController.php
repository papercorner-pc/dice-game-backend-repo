<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{

    public function rechargeUserWallet(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);
        $rechargeAmount = $request->amount;
        if (!$user) {
            return response()->json(['message' => 'User not found'], 401);
        }
        $user->deposit($rechargeAmount);
        return response()->json(['message' => 'Wallet recharged successfully', 'balance' => $user->balance], 200);
    }

    public function debitUserWallet(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);
        $debitAmount = $request->amount;
        if (!$user) {
            return response()->json(['message' => 'User not found'], 401);
        }
        if ($user->balance < $debitAmount) {
            return response()->json(['message' => 'Insufficient balance'], 400);
        }
        $user->withdraw($debitAmount);
        return response()->json(['message' => 'Wallet debited successfully', 'balance' => $user->balance], 200);
    }

    public function walletHistory()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 401);
        }
        $transactions = $user->transactions;
        return response()->json(['transactions' => $transactions], 200);
    }


    public function userProfile(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $userProfile = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email ?? 'N/A',
            'phone_number' => $user->phone_number,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        return response()->json(['user' => $userProfile, 'message' => 'success'], 200);
    }


}