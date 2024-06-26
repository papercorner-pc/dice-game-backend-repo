<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{


    protected $twilioService;

    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
    }

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

        $userAddress = $user->address;

        $userProfile = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email ?? 'N/A',
            'phone_number' => $user->phone_number,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'profile_image' => $userAddress ? Storage::url($userAddress->profile_image) : null,
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

        if (isset($validatedData['phone_number'])) {
            $existingUser = User::where('phone_number', $validatedData['phone_number'])->where('id', '!=', $user->id)->first();
            if ($existingUser) {
                return response()->json(['error' => 'Phone number already in use'], 400);
            }
        }

        $user->name = $validatedData['name'];
        $phoneNumberChanged = false;

        if (isset($validatedData['phone_number'])) {
            if ($user->phone_number !== $validatedData['phone_number']) {
                $otp = mt_rand(100000, 999999);
                $otpValidTill = Carbon::now()->addMinutes(10);
                $user->otp = $otp;
                $user->otp_valid_till = $otpValidTill;
                $user->phone_number = $validatedData['phone_number'];
                $phoneNumberChanged = true;
            }
        }

        $user->save();

        if ($phoneNumberChanged) {
            $message = 'Your otp is '.$otp.' please verify ';
            $this->twilioService->sendSms('+91'.$user->phone_number, $message);
        }

        $updatedProfile = [
            'id' => $user->id,
            'name' => $user->name,
            'phone_number' => $user->phone_number,
        ];

        return response()->json(['user' => $updatedProfile, 'message' => 'Profile updated successfully', 'phone_status' => $phoneNumberChanged], 200);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validatedData = $request->validate([
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $addressData = $validatedData;
        unset($addressData['name'], $addressData['phone_number'], $addressData['profile_image']);

        $userAddress = $user->address()->updateOrCreate(['user_id' => $user->id], $addressData);

        if ($request->hasFile('profile_image')) {
            if ($userAddress->profile_image && Storage::disk('public')->exists($userAddress->profile_image)) {
                Storage::disk('public')->delete($userAddress->profile_image);
            }

            $image = $request->file('profile_image');
            $filename = time() . '_' . preg_replace('/\s+/', '_', $image->getClientOriginalName());
            $path = $image->storeAs('assets/user/images', $filename, 'public');

            $userAddress->profile_image = $path;
            $userAddress->save();
        }

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user, 'address' => $userAddress], 200);
    }


}
