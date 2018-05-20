<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetMailer;
use App\PasswordReset;
use App\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class PasswordController extends Controller
{
	/*
	|--------------------------------------------------------------------------
	| Password Reset Controller
	|--------------------------------------------------------------------------
	|
	| This controller is responsible for handling password reset requests
	| and uses a simple trait to include this behavior. You're free to
	| explore this trait and override any methods you wish to tweak.
	|
	*/

	//use ResetsPasswords;

	/**
	 * Create a new password controller instance.
	 */

	public function __construct()
	{
		//$this->middleware('guest');
	}


	/**
	 * @param \Illuminate\Http\Request $request
	 * @return mixed
	 */
	public function changePassword(Request $request)
	{

		$validator = Validator::make($request->all(), ['token' => 'required', 'password' => 'required|min:4',]);

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		$token = $request->token;
		$passwordReset = PasswordReset::where($token, $token)->firstOrFail();

		try {

			$user = $passwordReset->user;
			$user->update(["password" => Hash($request->password)]);
		} catch (Exception $e) {

			return response()->error("Password could not be successfully updated");
		}
		$passwordReset->delete();

		return response()->success("Password successfully saved");
	}

	/**
	 * @param \Illuminate\Http\Request $request
	 * @return mixed
	 */
	public function sendResetPasswordEmail(Request $request)
	{
		$validator = Validator::make($request->all(), ['email' => 'required']);

		if ($validator->fails()) {
			return response()->error($validator->errors(), 422);
		}

		// Get user...
		try {
			$user = User::where("email", $request->email)->firstOrFail();
		} catch (ModelNotFoundException $e) {
			return response()->error('No User found');
		}
		// invalidate old tokens...
		PasswordReset::where('email', $request->email)->delete();

		// Create new Password
		$reset = PasswordReset::create(['email' => $request->email, 'token' => str_random(16)]);

		$token = md5($reset->email . $reset->token);

		$this->sendPasswordLink($reset, $reset->email, $token);

		return response()->success('mail sent');

	}


	/**
	 * @param PasswordReset $reset
	 * @param User $user
	 * @param $token
	 * @return void
	 */
	private function sendPasswordLink(PasswordReset $reset, $email, $token)
	{
//		$changePasswordURL = config('app.config.change_password_url');

//		$passwordResetLink = $changePasswordURL . $token;

		Mail::to($email)->send(new PasswordResetMailer($reset, $email, $token));

//		return Mailer::sendPasswordResetLink($user, $data);
	}


	/*public function updatePassword(Request $request) {
		$validator = $this->validate($request, [

			'newpassword' => 'required|min:4', 'oldpassword' => 'required|min:4', 'msisdn' => 'required|exists:users,username']);

		$username = $request->msisdn;
		$oldpassword = $request->oldpassword;
		$newpassword = $request->newpassword;
		$user = User::whereUsername($username)->firstOrFail();
		if ($oldpassword == $newpassword) {

			return response()->error("New password must be the same as the old password", Response::HTTP_ALREADY_REPORTED);
		}

		if (password_verify($oldpassword, $user->password)) {
			try {
				$user->password = bcrypt($newpassword);
				$user->save();
			}
			catch (\Exception $e) {

				return response()->error("Password Update failed", Response::HTTP_INTERNAL_SERVER_ERROR);
			}

			return response()->success(["message" => "Password successfully changed"]);
		}
		else {
			return response()->error("Please supply a valid old password", Response::HTTP_UNAUTHORIZED);
		}
	}*/
}
