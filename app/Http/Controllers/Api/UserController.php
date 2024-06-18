<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
        $balance = $user->balance;
        return response()->json(['transactions' => $transactions, 'balance' => $balance], 200);
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


    public function changePassword(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validatedData = $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|confirmed',
        ]);

        if (!Hash::check($validatedData['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 400);
        }

        $user->password = Hash::make($validatedData['new_password']);
        $user->save();

        return response()->json(['message' => 'Password changed successfully'], 200);
    }


    public function editProfile(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:15',
        ]);

        $user->name = $validatedData['name'];
        if (isset($validatedData['phone_number'])) {
            $user->phone_number = $validatedData['phone_number'];
        }
        $user->save();

        $updatedProfile = [
            'id' => $user->id,
            'name' => $user->name,
            'phone_number' => $user->phone_number,
        ];

        return response()->json(['user' => $updatedProfile, 'message' => 'Profile updated successfully'], 200);
    }


}
