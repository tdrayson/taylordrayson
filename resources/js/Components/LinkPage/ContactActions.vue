<script setup>
import Icon from '../Ui/Icon.vue';
import ActionTile from './ActionTile.vue';
import CardButton from './CardButton.vue';

defineProps({
    // The card payload: contactHref plus the optional phone, email and WhatsApp hrefs.
    card: { type: Object, required: true },
    variant: { type: String, default: 'personal' },
});

const ICON_CLASSES = {
    personal: 'text-accent-500',
    business: 'text-tinker-600',
};
</script>

<template>
    <div class="flex flex-col gap-3">
        <CardButton :href="card.contactHref" :variant="variant">
            <Icon name="UserAdd01Icon" class="size-6" />
            Save my contact
        </CardButton>

        <div v-if="card.phoneHref || card.emailHref" class="grid grid-cols-3 gap-2.5">
            <ActionTile v-if="card.phoneHref" :href="card.phoneHref" label="Call" aria-label="Call me" icon="Call02Icon" :icon-class="ICON_CLASSES[variant]" :variant="variant" />
            <ActionTile v-if="card.emailHref" :href="card.emailHref" label="Email" aria-label="Email me" icon="Mail01Icon" :icon-class="ICON_CLASSES[variant]" :variant="variant" />
            <ActionTile v-if="card.whatsappHref" :href="card.whatsappHref" label="WhatsApp" aria-label="Message me on WhatsApp" icon="WhatsappIcon" :icon-class="ICON_CLASSES[variant]" :variant="variant" external />
        </div>
    </div>
</template>
