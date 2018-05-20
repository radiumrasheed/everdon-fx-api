<?php

namespace App\Mail;

use App\PasswordReset;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class PasswordResetMailer extends Mailable
{
	use Queueable, SerializesModels;

	protected $resets;
	protected $email;
	protected $token;

	/**
	 * Create a new message instance.
	 *
	 * @param PasswordReset $resets
	 * @param User $email
	 * @param $token
	 */
	public function __construct(PasswordReset $resets, $email, $token)
	{
		//
		$this->resets = $resets;
		$this->$email = $email;
		$this->token = $token;
	}

	/**
	 * Build the message.
	 *
	 * @return $this
	 */
	public function build()
	{
		return $this->view('emails.reset')
			->with(['token' => $this->token])
			->with(['email' => $this->email])
			->with(['resets' => $this->resets]);
	}
}
