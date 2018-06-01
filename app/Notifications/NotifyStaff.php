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
	public function __construct(Transaction $transaction, Staff $staff, TransactionEvent $event)
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

		return (new MailMessage)
			->subject($this->event->action)
			->greeting('Hello ' . $this->staff->full_name . ',')
			->line('The transaction has been reviewed.')
			->line('"' . $this->event->comment . '"')
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
	 * @return array
	 */
	public function toArray($notifiable)
	{
		return [
			//
		];
	}
}
