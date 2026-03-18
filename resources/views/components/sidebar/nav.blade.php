<nav class="space-y-0.5 mb-10">
    <a @if(request()->is('/')) aria-current="page" @endif
        class="flex items-center gap-2.5 py-2 px-2 rounded-lg text-sm transition-colors {{ request()->is('/') ? 'text-foreground font-medium bg-accent' : 'text-muted-foreground hover:text-foreground hover:bg-accent/50' }}"
        href="/">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            class="lucide lucide-house h-4 w-4">
            <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path>
            <path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
        </svg>
        <span>Timeline</span>
    </a>
    <a @if(request()->is('stats*')) aria-current="page" @endif
        class="flex items-center gap-2.5 py-2 px-2 rounded-lg text-sm transition-colors {{ request()->is('stats*') ? 'text-foreground font-medium bg-accent' : 'text-muted-foreground hover:text-foreground hover:bg-accent/50' }}"
        href="/stats">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            class="lucide lucide-chart-column h-4 w-4">
            <path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
            <path d="M18 17V9"></path>
            <path d="M13 17V5"></path>
            <path d="M8 17v-3"></path>
        </svg>
        <span>Stats</span>
    </a>
    <a @if(request()->is('map*')) aria-current="page" @endif
        class="flex items-center gap-2.5 py-2 px-2 rounded-lg text-sm transition-colors {{ request()->is('map*') ? 'text-foreground font-medium bg-accent' : 'text-muted-foreground hover:text-foreground hover:bg-accent/50' }}"
        href="/map">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            class="lucide lucide-map h-4 w-4">
            <path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"></path>
            <path d="M15 5.764v15"></path>
            <path d="M9 3.236v15"></path>
        </svg>
        <span>Map</span>
    </a>
    <a @if(request()->is('about*')) aria-current="page" @endif
        class="flex items-center gap-2.5 py-2 px-2 rounded-lg text-sm transition-colors {{ request()->is('about*') ? 'text-foreground font-medium bg-accent' : 'text-muted-foreground hover:text-foreground hover:bg-accent/50' }}"
        href="/about">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            class="lucide lucide-user h-4 w-4">
            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
        </svg>
        <span>About</span>
    </a>
    <a @if(request()->is('writing*')) aria-current="page" @endif
        class="flex items-center gap-2.5 py-2 px-2 rounded-lg text-sm transition-colors {{ request()->is('writing*') ? 'text-foreground font-medium bg-accent' : 'text-muted-foreground hover:text-foreground hover:bg-accent/50' }}"
        href="/writing">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            class="lucide lucide-file-text h-4 w-4">
            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
            <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
            <path d="M10 9H8"></path>
            <path d="M16 13H8"></path>
            <path d="M16 17H8"></path>
        </svg>
        <span>Writing</span>
    </a>
</nav>
