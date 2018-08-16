<?php

namespace App\Notifications;

use App\Transaction;
use App\TransactionEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NotifyClient extends Notification implements ShouldQueue
{
	use Queueable;

	public $transaction;
	public $event;


	/**
	 * Create a new notification instance.
	 *
	 * @param Transaction      $transaction
	 * @param TransactionEvent $event
	 */
	public function __construct(Transaction $transaction, TransactionEvent $event = NULL)
	{
		$this->transaction = $transaction;
		$this->event = $event;
	}


	/**
	 * Get the notification's delivery channels.
	 *
	 * @param  mixed $notifiable
	 *
	 * @return array
	 */
	public function via($notifiable)
	{
		return ['mail'];
	}


	/**
	 * Get the mail representation of the notification.
	 *
	 * @param  mixed $notifiable
	 *
	 * @return \Illuminate\Notifications\Messages\MailMessage
	 */
	public function toMail($notifiable)
	{
		$url = config('custom.client.host') . '/#/me/transaction/details/' . $this->transaction->id;

		if ($this->event !== NULL) {
			switch ((string) $this->event->action) {
				case 'Transaction Rejected':
					$message = 'A transaction has been rejected and awaiting review.';
					break;

				case 'Fulfilled and Closed Transaction':
					$message = 'Your transaction has been fulfilled.';
					break;

				case 'Cancelled Transaction':
					$message = 'Your transaction has been cancelled for some reasons. Please contact support or request another.';
					break;

				default:
					$message = 'Your Transaction has been reviewed.';
			}
		} else {
			$message = 'Your Transaction Request has been received! Watch here for updates.';
		}


		return (new MailMessage)
			->subject($this->event !== NULL ? $this->event->action : 'Transaction Request Submitted')
			->greeting('Hello ' . $this->transaction->client->first_name . ' ' . $this->transaction->client->last_name . ',')
			->line($message)
			->action('View Transaction', $url)
			->line('Thank you for using our application!');

		/*return (new MailMessage)->view(
			'emails.transaction', ['transaction' => $this->transaction]
		);*/
	}


	/**
	 * Get the array representation of the notification.
	 *
	 * @param  mixed $notifiable
	 *
	 * @return array
	 */
	public function toArray($notifiable)
	{
		return [
			//
		];
	}
}
