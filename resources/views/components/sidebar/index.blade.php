@props(['streak' => 0, 'sparklines' => []])

<aside class="hidden lg:flex flex-col w-[280px] shrink-0 sticky top-0 h-screen py-10 pl-8 pr-6 overflow-y-auto">
    <x-sidebar.profile />
    <x-sidebar.nav />
    <div class="space-y-6 mt-auto">
        <x-sidebar.streak :count="$streak" />
        <x-sidebar.sparklines :data="$sparklines" />
    </div>
</aside>
