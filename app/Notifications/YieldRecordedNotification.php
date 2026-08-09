<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\YieldRecord;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class YieldRecordedNotification extends Notification
{
    public function __construct(public Project $project, public YieldRecord $yield)
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
            ->subject("New yield recorded for {$this->project->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line(sprintf(
                'A new yield of %s %s was recorded for "%s" on %s.',
                $this->yield->quantity,
                $this->project->type->yieldUnit(),
                $this->project->name,
                $this->yield->produced_on->toDateString()
            ));
    }
}
