<script setup>
import Icon from '../Ui/Icon.vue';
import Button from '../Ui/Button.vue';
import DropdownMenu from '../Ui/DropdownMenu.vue';

const now = new Date();
const pad = (value) => String(value).padStart(2, '0');
const year = now.getFullYear();
const month = pad(now.getMonth() + 1);
const day = pad(now.getDate());

const items = [
    { label: 'Today', href: `/${year}/${month}/${day}` },
    { label: 'This month', href: `/${year}/${month}` },
    { label: 'This year', href: `/${year}` },
    { label: 'On this day', href: '/on-this-day' },
    { label: "I'm feeling lucky", href: '/lucky' },
];
</script>

<template>
    <DropdownMenu :items="items" label="Time navigation">
        <template #trigger="{ open, toggle }">
            <div class="inline-flex">
                <Button href="/now" variant="secondary" size="sm" class="h-9 rounded-r-none border-r-0">
                    Now
                </Button>
                <Button
                    variant="secondary"
                    size="sm"
                    class="h-9 rounded-l-none px-2"
                    :aria-label="open ? 'Close time navigation' : 'Open time navigation'"
                    aria-haspopup="menu"
                    :aria-expanded="open"
                    @click="toggle"
                >
                    <Icon name="ArrowDown01Icon" class="size-3.5 transition-transform" :class="open ? 'rotate-180' : ''" />
                </Button>
            </div>
        </template>
    </DropdownMenu>
</template>
