<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Support\Links;
use App\Support\PortableText;
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

        // Signed rather than a token column of its own: the URL carries its own
        // proof, and there is nothing extra to store or clean up.
        $unsubscribe = URL::signedRoute('unsubscribe', ['comment' => $this->parent->id]);

        return (new MailMessage)
            ->subject($this->reply->author_name.' replied to your comment')
            ->greeting('Hello '.$this->parent->author_name.',')
            ->line($this->reply->author_name.' replied to the comment you left on '.Links::host(config('app.url')).':')
            // The body is Portable Text, so it is flattened here. Quoting the
            // array itself sends the word "Array" and nobody finds out.
            ->line('> '.self::quote($this->reply->body))
            ->action('Read the reply', $url)
            ->line('You are getting this because you asked to be told about replies. ['
                .'Stop these emails]('.$unsubscribe.')');
    }

    /**
     * A comment as one quotable line.
     *
     * Takes whatever the cast hands back rather than insisting on Portable
     * Text: a queued notification that fatals on an odd row fails where nobody
     * is watching, and this is the last place that should be brittle.
     */
    private static function quote(mixed $body): string
    {
        $text = is_array($body) ? PortableText::plainText($body) : (string) $body;

        // Newlines would break out of the markdown blockquote and render the
        // rest of the comment as body copy.
        $text = preg_replace('/\s+/', ' ', $text);

        return (string) str(trim((string) $text))->limit(300);
    }
}
