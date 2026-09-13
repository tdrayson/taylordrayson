<?php

namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Tells somebody their comment got a reply.
 *
 * Sent when the reply is approved, never when it is submitted: moderating is
 * what stops spam, and notifying on submission would forward every spam
 * comment straight to a stranger's inbox.
 */
class ReplyPosted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Comment $reply,
        private readonly Comment $parent,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url($this->reply->commentable->url().'#'.$this->reply->fragment());

        return (new MailMessage)
            ->subject($this->reply->author_name.' replied to your comment')
            ->greeting('Hello '.$this->parent->author_name.',')
            ->line($this->reply->author_name.' replied to the comment you left on taylordrayson.com:')
            ->line('"'.str($this->reply->body)->limit(300).'"')
            ->action('Read the reply', $url)
            // Signed rather than a token column of its own: the URL carries its
            // own proof, and there is nothing extra to store or clean up.
            ->salutation("You are getting this because you asked to be told about replies.\n"
                .'Stop these: '.URL::signedRoute('unsubscribe', ['comment' => $this->parent->id]));
    }
}
