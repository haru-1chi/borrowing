<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Password;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Hash;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assis ts in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $token = Str::random(length: 64);
        DB::table(table: 'password_resets')->insert([
            'email' => $request->email,
            'token' => $token,
            'created_at' => Carbon::now()
        ]);
        Mail::send(
            'auth.forget-password',
            ['token' => $token, 'email' => $request->email],
            function ($message) use ($request) {
                $message->to($request->email);
                $message->subject("Reset Password");
            }
        );
        return response()->json(['message' => 'Password reset link sent to your email']);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required'
        ]);
        $resetRecord = DB::table('password_resets')->where([
            'email' => $request->email,
            'token' => $request->token,
        ])->first();
        if (!$resetRecord) {
            return response()->json(['error' => 'Invalid reset token'], 400);
        }
        $user = User::where('email', $request->email)->first();
        if ($request->password !== $request->password_confirmation) {
            return response()->json(['error' => 'Password and confirmation do not match'], 401);
        }
        $user->update(['password' => Hash::make($request->password)]);
        DB::table('password_resets')->where(['email' => $request->email])->delete();

        return response()->json(['message' => 'Password reset successfully']);
    }

    public function showResetForm(Request $request, $token = null, $email = null)
    {
        return view('auth.passwords.reset')->with(['token' => $token, 'email' => $email]);
    }
}
