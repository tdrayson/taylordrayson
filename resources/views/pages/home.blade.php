<x-layouts.app :streak="$streak" :sparklines="$sparklines">
    <div class="w-full px-5 py-10 lg:pt-28 lg:pb-16 lg:pl-[26px]">
        {{-- Bio intro --}}
        <div class="max-w-[680px] mx-auto pl-[52px]">
            <div class="mb-12 space-y-4 text-muted-foreground text-xl leading-relaxed max-w-[750px]">
                <p>Hey! I'm Taylor<img src="/headshot-taylor.jpg" alt="" class="w-7 h-7 align-sub rounded inline ml-2 mr-1 rotate-2">, a web developer in London who tracks everything including every calorie for {{ number_format($streak) }} days straight (and counting).</p>
                <p>I run a <a href="https://thecreativetinker.com" class="inline items-baseline underline decoration-muted-foreground/40 hover:text-foreground transition-colors"><img src="https://v1.indieweb-avatar.11ty.dev/https%3A%2F%2Fthecreativetinker.com" alt="" class="w-7 h-7 align-sub rounded inline mr-2 ml-1 -rotate-3">small web agency</a> I started at 19, develop a <a href="https://wpextended.io" class="inline items-baseline underline decoration-muted-foreground/40 hover:text-foreground transition-colors"><img src="https://v1.indieweb-avatar.11ty.dev/https%3A%2F%2Fwpextended.io" alt="" class="w-7 h-7 align-sub rounded inline mr-2 ml-1 rotate-1">WordPress plugin</a>, and co-host a <a href="https://thisweekwith.co.uk" class="inline items-baseline underline decoration-muted-foreground/40 hover:text-foreground transition-colors"><img src="https://v1.indieweb-avatar.11ty.dev/https%3A%2F%2Fthisweekwith.co.uk" alt="" class="w-7 h-7 align-sub rounded inline mr-2 ml-1 -rotate-2">weekly podcast</a> with my dad with over {{ $episodeCount }} episodes. When I touch grass, I'm probably playing a racket sport or making another coffee.</p>
            </div>
        </div>

        {{-- Timeline feed --}}
        <div class="space-y-8 max-w-[680px] mx-auto pl-[52px]">
            @php $lastDate = null; @endphp
            @foreach ($entries as $entry)
                @if ($entry->timelineable)
                    @php
                        $currentDate = $entry->occurred_at->format('Y-m-d');
                    @endphp

                    @if ($currentDate !== $lastDate)
                        <div class="flex items-center gap-3 pt-4">
                            <h3 class="text-sm font-medium text-muted-foreground">
                                {{ $entry->occurred_at->format('l j F Y') }}
                            </h3>
                        </div>
                        @php $lastDate = $currentDate; @endphp
                    @endif

                    <x-cards.dynamic :entry="$entry->timelineable" />
                @endif
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="max-w-[680px] mx-auto pl-[52px] mt-12">
            {{ $entries->links() }}
        </div>
    </div>
</x-layouts.app>
