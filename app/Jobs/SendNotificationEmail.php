<?php

namespace App\Jobs;

use App\Models\NotificationMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNotificationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly int $notificationMessageId,
    ) {}

    public function handle(): void
    {
        $message = NotificationMessage::query()
            ->with('recipient:id,name,email,status,email_verified_at')
            ->findOrFail($this->notificationMessageId);

        if ($message->email_status === 'sent'
            || $message->recipient->status !== 'active'
            || $message->recipient->email_verified_at === null) {
            return;
        }

        try {
            Mail::raw(
                $message->body."\n\nBuka: ".url($message->action_url ?? '/notifications'),
                function (Message $mail) use ($message): void {
                    $mail
                        ->to($message->recipient->email, $message->recipient->name)
                        ->subject('[Together Kamera] '.$message->title);
                },
            );

            $message->forceFill([
                'email_status' => 'sent',
                'email_sent_at' => now(),
                'email_failed_at' => null,
                'email_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $message->forceFill([
                'email_status' => 'failed',
                'email_failed_at' => now(),
                'email_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();

            throw $exception;
        }
    }
}
