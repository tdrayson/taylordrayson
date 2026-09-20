<?php

use App\Enums\CommentStatus;
use App\Models\Note;
use App\Support\PortableText;
use App\Support\VisitorIdentity;
use Illuminate\Http\Request;

/**
 * An approved comment comes back from the server whole, so it joins the thread
 * where it was written. The form used to say "Posted" over a thread that did
 * not have it in, which reads as the comment having gone nowhere.
 */
it('puts an approved comment straight into the thread', function () {
    $note = Note::factory()->create([
        'occurred_at' => now()->subHour(),
        'content' => PortableText::fromPlainText('Something worth answering.'),
    ]);

    // A name this visitor has had approved before, so the next one goes up
    // rather than into moderation.
    $note->comments()->create([
        'author_name' => 'Jo Bloggs',
        'body' => PortableText::fromPlainText('Said something here before.'),
        'status' => CommentStatus::Approved,
        'ip_hash' => VisitorIdentity::reputation(Request::create('/', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1'])),
    ]);

    $page = visit($note->url())->assertSee('1 interaction');

    $page->click('[placeholder="Add a comment"]')
        ->fill('[contenteditable="true"]', 'Straight into the thread, no reload.')
        ->fill('[autocomplete="name"]', 'Jo Bloggs')
        // The server holds anything posted within three seconds of the form
        // being touched, which would be the moderation path, not this one.
        ->wait(4)
        ->click('Post comment')
        ->assertSee('Posted. Thanks for joining in.')
        ->assertSee('Straight into the thread, no reload.')
        ->assertSee('2 interactions')
        ->assertNoJavaScriptErrors();
});
