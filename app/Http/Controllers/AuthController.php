<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Mail\VerificationCodeMail;


class AuthController extends Controller
{
    public function register(Request $request){


        $request->validate(['email' => 'required|email|unique:users']);
        
        $email = $request->email;
        $verificationCode = rand(100000, 999999);

        Cache::put('verification_' . $email, $verificationCode, now()->addSeconds(60));
        Mail::to($email)->send(new VerificationCodeMail($verificationCode));

        return response()->json(['message' => 'Verification code sent to your email.'], 200);
    }

    public function login(Request $request){
        $fields = $request -> validate([
            'email' => 'required|email|exists:users',
            'password' => 'required'
        ]);

        $user = User::where('email', $request -> email) -> first();
        if ( !$user | !Hash::check($request -> password, $user->password)){
            return [
                'message' => 'The provided credentials are incorrect.' 
            ];
        }

        $token = $user->createToken($user->name);
        return [
            'user'=> $user,
            'token' =>  $token -> plainTextToken
        ];

    }
    
    public function logout(Request $request){
        $request -> user() -> tokens() -> delete();
        return [
            'message' => 'You are logged out.' 
        ];
    }

    public function verifyRegistration(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|digits:6'
        ]);
    
        $email = $request->email;
        $code = $request->code;
    
        
        $cachedCode = Cache::get('verification_' . $email);
    
        if (!$cachedCode || $cachedCode != $code) {
            return response()->json(['message' => 'Invalid or expired code.'], 400);
        }
    
        
        Cache::forget('verification_' . $email);
    
        
        $user = User::create([
            'email' => $email,
            'password' => bcrypt('default_password') 
        ]);
    
        return response()->json(['message' => 'Registration successful!', 'user' => $user], 201);
    }
    
    

    public function resendCode(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $email = $request->email;

        if (Cache::has('verification_' . $email)) {
            return response()->json(['message' => 'A code is already active. Please wait.'], 400);
        }

        $verificationCode = rand(100000, 999999);

        Cache::put('verification_' . $email, $verificationCode, now()->addSeconds(60));

        Mail::to($email)->send(new VerificationCodeMail($verificationCode));

        return response()->json(['message' => 'Verification code resent to your email.'], 200);
    }


}
