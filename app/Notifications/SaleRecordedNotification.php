<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\Sale;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SaleRecordedNotification extends Notification
{
    public function __construct(public Project $project, public Sale $sale)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New sale recorded for {$this->project->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line(sprintf(
                'A new sale of %s %s for $%s was recorded for "%s" on %s.',
                $this->sale->quantity,
                $this->project->type->yieldUnit(),
                number_format($this->sale->amount, 2),
                $this->project->name,
                $this->sale->sold_on->toDateString()
            ));
    }
}
