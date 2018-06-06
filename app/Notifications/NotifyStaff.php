<?php

namespace App\Notifications;

use App\Staff;
use App\Transaction;
use App\TransactionEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NotifyStaff extends Notification implements ShouldQueue
{
	use Queueable;

	public $transaction;
	public $staff;
	public $event;

	/**
	 * Create a new notification instance.
	 *
	 * @param Transaction $transaction
	 * @param Staff $staff
	 * @param TransactionEvent $event
	 */
	public function __construct(Transaction $transaction, Staff $staff = null, TransactionEvent $event = null)
	{
		$this->transaction = $transaction;
		$this->staff = $staff;
		$this->event = $event;
	}

	/**
	 * Get the notification's delivery channels.
	 *
	 * @param  mixed $notifiable
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
	 * @return \Illuminate\Notifications\Messages\MailMessage
	 */
	public function toMail($notifiable)
	{
		$url = config('custom.client.host') . '/#/admin/transaction/details/' . $this->transaction->id;

		if ($this->event === null) {
			return (new MailMessage)
				->subject('New Transaction Request')
				->greeting('Dear FX Ops Member,')
				->line('A new transaction has been requested and awaiting review.')
				->action('View Transaction', $url);
		}

		switch ((string)$this->event->action) {
			case 'Approved Transaction':
				$greeting = 'Dear Treasury Ops Member,';
				$message = 'A transaction has been approved and awaiting fulfilment.';
				break;

			case 'Treated Transaction':
				$greeting = 'Dear FX Ops Lead/Manager Member,';
				$message = 'A transaction has been treated and awaiting approval.';
				break;

			case 'New Transaction Request':
				$greeting = 'Dear FX Ops Member,';
				$message = 'A new transaction has been requested and awaiting review.';
				break;

			case 'Transaction Rejected':
				$greeting = 'Dear FX Ops Member,';
				$message = 'A transaction has been rejected and awaiting review.';
				break;

			default:
				$greeting = 'Hello,';
				$message = 'A transaction has been reviewed.';
		}

		return (new MailMessage)
			->subject($this->event->action)
			->greeting($greeting)
			->line($message)
			->line('with comment by ' . $this->event->doneBy->name . ': "' . $this->event->comment . '"')
			->action('View Transaction', $url);

		/*return (new MailMessage)->view(
			'emails.transaction', ['transaction' => $this->transaction]
		);*/
	}

	/**
	 * Get the array representation of the notification.
	 *
	 * @param  mixed $notifiable
	 * @return array
	 */
	public function toArray($notifiable)
	{
		return [
			//
		];
	}
}
