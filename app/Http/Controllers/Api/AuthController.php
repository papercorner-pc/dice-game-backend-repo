<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Dotenv\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use function Illuminate\Foundation\Configuration\respond;

class AuthController extends Controller
{

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createAccount(Request $request)
    {
        try {
            $registrationData = $request->validate([
                'name' => 'required|string|max:255',
                'phone_number' => 'required|string|unique:users,phone_number',
                'password' => 'required|string|min:6',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 400);
        }

        $otp = mt_rand(100000, 999999);
        $otpValidTill = Carbon::now()->addMinutes(10);

        $user = User::create([
            'name' => $registrationData['name'],
            'phone_number' => $registrationData['phone_number'],
            'password' => Hash::make($registrationData['password']),
            'otp' => $otp,
            'otp_valid_till' => $otpValidTill,
            'otp_verified' => false,
        ]);

        return response()->json(['message' => 'Account created successfully.'], 201);
    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyOTP(Request $request)
    {
        try {
            $validator = $request->validate([
                'phone_number' => 'required|string',
                'otp' => 'required|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 400);
        }

        $user = User::where('phone_number', $request->phone_number)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        if ($user->otp != $request->otp) {
            return response()->json(['error' => 'Invalid OTP.'], 400);
        }

        if (Carbon::now()->gt($user->otp_valid_till)) {
            return response()->json(['error' => 'OTP has expired.'], 400);
        }

        $user->otp_verified = true;
        $user->save();

        return response()->json(['message' => 'OTP verified successfully.'], 200);
    }


    /**
     * Login with phone number and OTP or password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {

        try {
            $validator = $request->validate([
                'phone_number' => 'required|string',
                'password' => 'required_without:otp|string',
                'otp' => 'required_without:password|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 400);
        }

        if ($request->has('otp')) {
            $user = User::where('phone_number', $request->phone_number)
                ->where('otp_verified', true)
                ->first();

            if (!$user) {
                return response()->json(['error' => 'User not found or OTP not verified.'], 404);
            }
            if ($user->otp != $request->otp) {
                return response()->json(['error' => 'Invalid OTP.'], 400);
            }
        } else {
            $user = User::where('phone_number', $request->phone_number)->first();

            if (!$user) {
                return response()->json(['error' => 'User not found.'], 404);
            }

            if (!Hash::check($request->password, $user->password)) {
                return response()->json(['error' => 'Invalid password.'], 401);
            }
        }

        $token = $user->createToken('AuthToken')->plainTextToken;
        return response()->json(['token' => $token], 200);
    }



    public function logout(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 401);
        }

        $user->currentAccessToken()->delete();
        return response()->json(['message' => 'Successfully logged out'], 200);
    }
}
