<?php

namespace App\Console\Commands\Demo;

use App\Actions\Webmentions\ParseMentionSource;
use App\Enums\CommentStatus;
use App\Enums\ReactionType;
use App\Enums\WebmentionKind;
use App\Models\Comment;
use App\Models\Page;
use App\Models\Reaction;
use App\Models\Webmention;
use App\Support\HtmlToPortableText;
use App\Support\InteractionTarget;
use App\Support\PortableText;
use App\Timeline\TypeRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Fill one entry of every type with a full conversation, and leave a second of
 * each with nothing, so both states can be looked at side by side.
 *
 * A development aid, not a seeder: it writes rows that look like real traffic
 * so the conversation UI can be judged on every entry type at once. Delete this
 * class once the design has settled.
 */
#[Signature('demo:interactions {--clear : Remove the demo rows and stop}')]
#[Description('Fill one entry per type with comments, reactions and every webmention kind')]
class SeedInteractions extends Command
{
    /** Marks a row as this command's, so a re-run replaces rather than piles up. */
    private const MARKER = 'demo:interactions';

    private const SOURCE_HOST = 'https://demo.example';

    public function handle(): int
    {
        $this->clear();

        if ($this->option('clear')) {
            $this->components->info('Demo interactions removed.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($this->targets() as $type => [$populated, $bare]) {
            if ($populated === null) {
                $this->components->warn("{$type} - no entries to demonstrate with");

                continue;
            }

            $this->fill($populated);

            $rows[] = [$type, $populated->url(), $bare?->url() ?? 'no second entry'];
        }

        $this->newLine();
        $this->table(['Type', 'With a conversation', 'With nothing'], $rows);

        return self::SUCCESS;
    }

    /**
     * Two entries per type: the newest gets a conversation, the next one is
     * left alone so the empty state can be seen on the same kind of page.
     *
     * @return array<string, array{0: Model|null, 1: Model|null}>
     */
    private function targets(): array
    {
        $classes = array_map(fn (array $type): string => $type['model'], TypeRegistry::all())
            + ['page' => Page::class];

        $targets = [];

        foreach ($classes as $type => $class) {
            $found = $class::query()
                ->latest($class === Page::class ? 'created_at' : 'occurred_at')
                ->get()
                ->filter(fn (Model $model): bool => InteractionTarget::accepts($model))
                ->take(2)
                ->values();

            $targets[$type] = [$found->get(0), $found->get(1)];
        }

        return $targets;
    }

    /** Every kind of response one entry can carry, on one entry. */
    private function fill(Model $target): void
    {
        $slug = md5($target::class.$target->getKey());

        // Two local comments, the second answering the first, plus a reply to
        // that reply so the flattening past one level is visible.
        $first = $this->leaveComment($target, 'Marty Spargo', 'This is the bit that always gets me too. Did you try the shorter warm up?', null);
        $second = $this->leaveComment($target, 'Taylor Drayson', 'Shorter warm up, yes. Made a real difference.', $first->id);
        $this->leaveComment($target, 'Clare', 'He will not admit that was my idea.', $second->id);
        $this->leaveComment($target, 'Priya Raman', 'Saving this one for later.', null);

        // On-site reactions, one visitor each so the counts are honest.
        foreach ([ReactionType::Love, ReactionType::Love, ReactionType::Celebrate, ReactionType::Haha, ReactionType::Wow] as $index => $type) {
            $target->reactions()->create([
                'type' => $type,
                'identity_key' => hash('sha256', self::MARKER.$slug.$index),
            ]);
        }

        // One of every kind a webmention can be, including the two that carry
        // no words: a like reads as a gesture, a reacji as an emoji.
        $mentions = [
            [WebmentionKind::Reply, 'Jo Bloggs', 'https://jobloggs.example/', 'Completely agree. I wrote something similar last month and got the same pushback.'],
            [WebmentionKind::Like, 'Mark Ellery', 'https://mark.example/', null],
            [WebmentionKind::Repost, 'Ana Silva', 'https://ana.example/', null],
            [WebmentionKind::Bookmark, 'Dev Sharma', 'https://dev.example/', null],
            [WebmentionKind::Mention, 'Holly Dean', 'https://holly.example/', 'Been following Taylor logging every walk and it has finally got me out doing the same.'],
            [WebmentionKind::Rsvp, 'Ryan Patel', 'https://ryan.example/', null],
            [WebmentionKind::Reacji, 'Chloe Nolan', 'https://chloenolan.example/', '🎉'],
        ];

        foreach ($mentions as $index => [$kind, $name, $url, $content]) {
            $mention = $target->webmentions()->make([
                'source_url' => self::SOURCE_HOST."/{$slug}/{$index}",
                'target_url' => rtrim((string) config('app.url'), '/').$target->url(),
                'kind' => $kind->value,
                'author_name' => $name,
                'author_url' => $url,
                'content' => $content === null ? null : PortableText::fromPlainText($content),
                'published_at' => now()->subDays(7 - $index)->subHours($index),
                'status' => CommentStatus::Approved,
                'verified_at' => now(),
            ]);

            $mention->save();
        }

        // A reply built the way a real one is: sender HTML through the
        // converter, so the demo shows what the allowlist actually renders
        // rather than a hand-written document that skips it.
        $sent = <<<'HTML'
        <p>This is the part I keep coming back to:</p>
        <blockquote><p>Shorter warm up, yes. Made a real difference.</p></blockquote>
        <p>I tried it for a fortnight and the difference was <strong>obvious</strong>,
        though <em>only</em> on the longer runs. Wrote it up
        <a href="https://rosa.example/warm-ups">over here</a> with the numbers, and
        the bit that matters is <u>the first ten minutes</u>.</p>
        <p>Two things that helped:</p>
        <ul>
            <li>Starting slower than feels right</li>
            <li>Walking the first <code>400m</code></li>
        </ul>
        <p><a href="https://rosa.example/warm-ups"><img src="https://rosa.example/chart.png" alt="A chart of pace against week, climbing steadily"></a></p>
        <p><img src="https://rosa.example/tracker.gif" alt=""> Thanks for writing it up.</p>
        HTML;

        $rich = $target->webmentions()->make([
            'source_url' => self::SOURCE_HOST."/{$slug}/rich",
            'target_url' => rtrim((string) config('app.url'), '/').$target->url(),
            'kind' => WebmentionKind::Reply->value,
            'author_name' => 'Rosa Lindqvist',
            'author_url' => 'https://rosa.example/',
            'content' => HtmlToPortableText::convert($sent),
            'published_at' => now()->subHours(3),
            'status' => CommentStatus::Approved,
            'verified_at' => now(),
        ]);

        $rich->save();

        // The commonest shape there is: an article that links here, carrying a
        // title and no content or summary at all. Run through the real parser,
        // because how a title-only mention reads is the open design question.
        $target_url = rtrim((string) config('app.url'), '/').$target->url();

        $titleOnly = <<<HTML
        <div class="h-entry">
            <a class="p-author h-card" href="https://jan.systems/">Jan Sydanviita</a>
            <h1 class="p-name"><a class="u-url" href="https://jan.systems/posts/now">Now, summer 2026</a></h1>
            <p>Plenty of prose here that is not marked up as content, and a link to
            <a href="{$target_url}">something Taylor wrote</a> in the middle of it.</p>
        </div>
        HTML;

        $parsed = (new ParseMentionSource)($titleOnly, self::SOURCE_HOST."/{$slug}/title-only", $target_url);

        $target->webmentions()->make([
            'source_url' => self::SOURCE_HOST."/{$slug}/title-only",
            'target_url' => $target_url,
            'kind' => $parsed->kind->value,
            'author_name' => $parsed->authorName,
            'author_url' => $parsed->authorUrl,
            'content' => $parsed->content,
            'published_at' => now()->subHours(5),
            'status' => CommentStatus::Approved,
            'verified_at' => now(),
        ])->save();

        // A like that arrived from a platform rather than a personal site. The
        // closest this can get today: syndicated responses have no storage of
        // their own yet, so a kudo is modelled as the like it becomes.
        $kudo = $target->webmentions()->make([
            'source_url' => self::SOURCE_HOST."/{$slug}/strava",
            'target_url' => rtrim((string) config('app.url'), '/').$target->url(),
            'kind' => WebmentionKind::Like->value,
            'author_name' => 'Sam Whitfield',
            'author_url' => 'https://www.strava.com/athletes/4412',
            'published_at' => now()->subDay(),
            'status' => CommentStatus::Approved,
            'verified_at' => now(),
        ]);

        $kudo->save();
    }

    private function leaveComment(Model $target, string $name, string $body, ?int $parentId): Comment
    {
        return $target->comments()->create([
            'parent_id' => $parentId,
            'author_name' => $name,
            'body' => PortableText::fromPlainText($body),
            'status' => CommentStatus::Approved,
            'user_agent' => self::MARKER,
            'created_at' => now()->subDays(random_int(1, 6)),
        ]);
    }

    /** Everything this command has ever written, recognised by its markers. */
    private function clear(): void
    {
        Comment::query()->where('user_agent', self::MARKER)->delete();
        Webmention::query()->where('source_url', 'like', self::SOURCE_HOST.'%')->delete();

        Reaction::query()->whereIn('identity_key', $this->knownIdentities())->delete();
    }

    /**
     * The reaction identities this command can have minted. Derived rather than
     * recorded, which is what lets a re-run clean up after the last one.
     *
     * @return list<string>
     */
    private function knownIdentities(): array
    {
        $keys = [];

        foreach ($this->targets() as [$populated, $bare]) {
            foreach (array_filter([$populated, $bare]) as $model) {
                $slug = md5($model::class.$model->getKey());

                for ($index = 0; $index < 5; $index++) {
                    $keys[] = hash('sha256', self::MARKER.$slug.$index);
                }
            }
        }

        return $keys;
    }
}
