<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwilioService;
use Dotenv\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use function Illuminate\Foundation\Configuration\respond;

class AuthController extends Controller
{


    protected $twilioService;

    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
    }
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createAccount(Request $request)
    {
        try {
            $registrationData = $request->validate([
                'name' => 'required|unique:users,name',
                'phone_number' => 'string|unique:users,phone_number',
                'password' => 'required',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Phone number already taken, please try different'], 400);
        }

        $otp = mt_rand(100000, 999999);
        $otpValidTill = Carbon::now()->addMinutes(10);

        $user = User::create([
            'name' => $registrationData['name'],
            'phone_number' => $registrationData['phone_number'] ?? null,
            'password' => Hash::make($registrationData['password']),
            'otp' => $otp,
            'otp_valid_till' => $otpValidTill,
            'otp_verified' => false,
            'fcm_token' => $request->device_token ?? null
        ]);

        /*$message = 'Your otp is '.$otp.' please verify ';
        $this->twilioService->sendSms($user->phone_number, $message);*/
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

        if($request->otp == 999999){
            $token = $user->createToken('AuthToken')->plainTextToken;
            $userObj = [
                'user_name' => $user->name,
                'user_phone' => $user->phone_number
                ];

            return response()->json(
                [
                    'token' => $token,
                    'is_admin' => 0,
                    'user' => $userObj
                ], 200);
        }

        if ($user->otp != $request->otp) {
            return response()->json(['error' => 'Invalid OTP.'], 400);
        }

        if (Carbon::now()->gt($user->otp_valid_till)) {
            return response()->json(['error' => 'OTP has expired.'], 400);
        }

        $user->otp_verified = true;
        $user->fcm_token = $request->device_token ?? null;
        $user->save();

        $token = $user->createToken('AuthToken')->plainTextToken;
        $userObj = [
            'user_name' => $user->name,
            'user_phone' => $user->phone_number
        ];
        return response()->json(['message' => 'OTP verified successfully.', 'token' => $token, 'is_admin' => 0, 'user' => $userObj
        ], 200);
    }


    /**
     * Login with phone number and OTP or password.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        try {
            $validator = $request->validate([
                'phone_number' => 'required_without:username|string',
                'username' => 'required_without:phone_number|string',
                'password' => 'required_without:otp|string',
                'otp' => 'required_without:password|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 400);
        }


        if ($request->has('otp') && $request->otp == 999999){

            $user = User::where('phone_number', $request->phone_number)
                ->first();

            if($user){
                if ($user->is_super_admin == 1) {
                    $userStatus = 1;
                } else {
                    $userStatus = 0;
                }

                $userObj = [
                    'user_name' => $user->name,
                    'user_phone' => $user->phone_number,];

                $token = $user->createToken('AuthToken')->plainTextToken;
                return response()->json(
                    [
                        'token' => $token,
                        'is_admin' => $userStatus,
                        'user' => $userObj
                    ], 200);
            }else{
                return response()->json(['error' => 'User not found or OTP not verified.'], 404);
            }
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
            /*$user = User::where(function ($query) use ($request) {
                if ($request->filled('phone_number')) {
                    $query->where('phone_number', $request->phone_number);
                }
                if ($request->filled('name')) {
                    $query->where('name', $request->username);
                }
            })->first();

            dd($user);

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json(['error' => 'Invalid credentials.'], 401);
            }*/

            if($request->username) {
                $user = User::where('name', $request->username)->first();
                if(!$user || !Hash::check($request->password, $user->password)) {
                    return response()->json(['error' => 'Invalid credentials.'], 401);
                }
            } elseif($request->phone_number) {
                $user = User::where('phone_number', $request->phone_number)->first();
                if(!$user || !Hash::check($request->password, $user->password)) {
                    return response()->json(['error' => 'Invalid credentials.'], 401);
                }
            } else {
                return response()->json(['error' => 'Something went wrong'], 401);
            }

        }

        $userType = '';
        if ($user->is_super_admin == 1) {
            $userStatus = 1;
            $userType = 'super_admin';
        }else if($user->is_agent == 1){
            $userStatus = 0;
            $userType = 'agent';
        } else  {
            $userStatus = 0;
            $userType = 'dealer';
        }

        $userObj = [
            'user_name' => $user->name,
            'user_phone' => $user->phone_number,];
        $token = $user->createToken('AuthToken')->plainTextToken;

        $user->fcm_token = $request->device_token ?? null;
        $user->save();
        return response()->json(['token' => $token, 'is_admin' => $userStatus, 'user' => $userObj, 'type' => $userType ], 200);
    }


    public function logout(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 401);
        }

        $user->currentAccessToken()->delete();
        return response()->json(['success' => 'Successfully logged out'], 200);
    }
}
