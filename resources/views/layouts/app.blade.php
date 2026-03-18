@props(['fullWidth' => false, 'streak' => 0, 'sparklines' => []])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Taylor Drayson' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{ mobileNavOpen: false }">
    <div class="min-h-screen text-foreground">
        {{-- Mobile header --}}
        <div class="lg:hidden flex items-center justify-between px-5 py-3">
            <div class="flex items-center gap-3">
                <img src="/headshot-taylor.jpg" alt="Taylor Drayson" class="h-8 w-8 rounded-full object-cover">
                <span class="font-display text-base text-foreground">Taylor Drayson</span>
            </div>
            <div class="flex items-center gap-1">
                <div class="relative">
                    <button class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors px-2.5 py-1.5 rounded-lg hover:bg-accent">
                        <span>Today</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-chevron-down h-3.5 w-3.5 transition-transform">
                            <path d="m6 9 6 6 6-6"></path>
                        </svg>
                    </button>
                </div>
                <button @click="mobileNavOpen = true" class="p-1.5 text-muted-foreground hover:text-foreground transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-menu h-5 w-5">
                        <line x1="4" x2="20" y1="12" y2="12"></line>
                        <line x1="4" x2="20" y1="6" y2="6"></line>
                        <line x1="4" x2="20" y1="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Mobile overlay --}}
        <div
            x-show="mobileNavOpen"
            x-transition:enter="transition-opacity duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="mobileNavOpen = false"
            class="fixed inset-0 bg-background/80 backdrop-blur-sm z-50"
            style="display: none;"
        ></div>

        {{-- Mobile slide-out nav --}}
        <div
            x-show="mobileNavOpen"
            x-transition:enter="transition-transform duration-300"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition-transform duration-300"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="fixed inset-0 z-50 bg-background flex flex-col"
            style="display: none;"
        >
            <div class="flex items-center justify-between px-5 py-4">
                <div class="flex items-center gap-3">
                    <img src="/headshot-taylor.jpg" alt="Taylor Drayson" class="h-10 w-10 rounded-full object-cover">
                    <span class="font-display text-lg text-foreground">Taylor Drayson</span>
                </div>
                <button @click="mobileNavOpen = false" class="p-2 text-muted-foreground hover:text-foreground transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-x h-5 w-5">
                        <path d="M18 6 6 18"></path>
                        <path d="m6 6 12 12"></path>
                    </svg>
                </button>
            </div>
            <nav class="flex-1 px-5 py-4 space-y-1">
                <a @if(request()->is('/')) aria-current="page" @endif
                    class="flex items-center gap-3 py-3 px-3 rounded-lg text-base transition-colors {{ request()->is('/') ? 'text-foreground font-medium bg-accent' : 'text-muted-foreground hover:text-foreground hover:bg-accent/50' }}"
                    href="/">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="lucide lucide-house h-5 w-5">
                        <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path>
                        <path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    </svg>
                    <span>Timeline</span>
                </a>
                <a @if(request()->is('stats*')) aria-current="page" @endif
                    class="flex items-center gap-3 py-3 px-3 rounded-lg text-base transition-colors {{ request()->is('stats*') ? 'text-foreground font-medium bg-accent' : 'text-muted-foreground hover:text-foreground hover:bg-accent/50' }}"
                    href="/stats">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="lucide lucide-chart-column h-5 w-5">
                        <path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
                        <path d="M18 17V9"></path>
                        <path d="M13 17V5"></path>
                        <path d="M8 17v-3"></path>
                    </svg>
                    <span>Stats</span>
                </a>
                <a @if(request()->is('map*')) aria-current="page" @endif
                    class="flex items-center gap-3 py-3 px-3 rounded-lg text-base transition-colors {{ request()->is('map*') ? 'text-foreground font-medium bg-accent' : 'text-muted-foreground hover:text-foreground hover:bg-accent/50' }}"
                    href="/map">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="lucide lucide-map h-5 w-5">
                        <path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"></path>
                        <path d="M15 5.764v15"></path>
                        <path d="M9 3.236v15"></path>
                    </svg>
                    <span>Map</span>
                </a>
                <a @if(request()->is('about*')) aria-current="page" @endif
                    class="flex items-center gap-3 py-3 px-3 rounded-lg text-base transition-colors {{ request()->is('about*') ? 'text-foreground font-medium bg-accent' : 'text-muted-foreground hover:text-foreground hover:bg-accent/50' }}"
                    href="/about">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="lucide lucide-user h-5 w-5">
                        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>About</span>
                </a>
                <a @if(request()->is('writing*')) aria-current="page" @endif
                    class="flex items-center gap-3 py-3 px-3 rounded-lg text-base transition-colors {{ request()->is('writing*') ? 'text-foreground font-medium bg-accent' : 'text-muted-foreground hover:text-foreground hover:bg-accent/50' }}"
                    href="/writing">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="lucide lucide-file-text h-5 w-5">
                        <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                        <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                        <path d="M10 9H8"></path>
                        <path d="M16 13H8"></path>
                        <path d="M16 17H8"></path>
                    </svg>
                    <span>Writing</span>
                </a>
            </nav>
            <div class="px-5 py-6 border-t border-border space-y-3">
                <div class="flex items-center gap-3">
                    <a href="#" class="text-muted-foreground hover:text-foreground transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-github h-5 w-5">
                            <path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"></path>
                            <path d="M9 18c-4.51 2-5-2-7-2"></path>
                        </svg>
                    </a>
                    <a href="#" class="text-muted-foreground hover:text-foreground transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-twitter h-5 w-5">
                            <path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z"></path>
                        </svg>
                    </a>
                    <a href="#" class="text-muted-foreground hover:text-foreground transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-rss h-5 w-5">
                            <path d="M4 11a9 9 0 0 1 9 9"></path>
                            <path d="M4 4a16 16 0 0 1 16 16"></path>
                            <circle cx="5" cy="19" r="1"></circle>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        {{-- Main flex container --}}
        <div class="flex">
            @unless($fullWidth)
                <x-sidebar.index :streak="$streak" :sparklines="$sparklines" />
            @endunless

            <div class="flex-1 min-w-0 flex justify-center relative">
                {{-- Top-right status bar (desktop only) --}}
                <div class="hidden lg:flex items-center gap-4 absolute top-6 right-6 text-sm text-muted-foreground">
                    <span class="flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-clock h-3.5 w-3.5">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        23:37 GMT
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-cloud-sun h-3.5 w-3.5">
                            <path d="M12 2v2"></path>
                            <path d="m4.93 4.93 1.41 1.41"></path>
                            <path d="M20 12h2"></path>
                            <path d="m19.07 4.93-1.41 1.41"></path>
                            <path d="M15.947 12.65a4 4 0 0 0-5.925-4.128"></path>
                            <path d="M13 22H7a5 5 0 1 1 4.9-6H13a3 3 0 0 1 0 6Z"></path>
                        </svg>
                        8&deg;C
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-map-pin h-3.5 w-3.5">
                            <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        London
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-battery h-3.5 w-3.5">
                            <rect width="16" height="10" x="2" y="7" rx="2" ry="2"></rect>
                            <line x1="22" x2="22" y1="11" y2="13"></line>
                        </svg>
                        72%
                    </span>
                    <div class="relative">
                        <button class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors px-2.5 py-1.5 rounded-lg hover:bg-accent">
                            <span>Today</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-chevron-down h-3.5 w-3.5 transition-transform">
                                <path d="m6 9 6 6 6-6"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
