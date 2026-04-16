<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    as: {
        type: String,
        default: 'button',
    },
    href: {
        type: String,
        default: null,
    },
    label: {
        type: String,
        required: true,
    },
    icon: {
        type: String,
        default: 'edit',
    },
    variant: {
        type: String,
        default: 'neutral',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['click']);

const paths = {
    add: 'M12 5v14M5 12h14',
    edit: 'M4 20h4l10.5-10.5a2.8 2.8 0 0 0-4-4L4 16v4zM13.5 6.5l4 4',
    delete: 'M5 7h14M10 11v6M14 11v6M7 7l1 13h8l1-13M9 7l1-3h4l1 3',
    play: 'M8 5v14l11-7L8 5z',
    flow: 'M6 6h5v5H6zM13 13h5v5h-5zM11 8h3a2 2 0 0 1 2 2v3M13 16h-2a2 2 0 0 1-2-2v-3',
    trigger: 'M13 2L4 14h7l-1 8 9-12h-7l1-8z',
    mapping: 'M7 7h10M7 12h10M7 17h10M4 7h.01M4 12h.01M4 17h.01',
    test: 'M20 6L9 17l-5-5',
    back: 'M15 18l-6-6 6-6',
    reset: 'M4 4v6h6M20 20v-6h-6M5 14a7 7 0 0 0 12 4M19 10A7 7 0 0 0 7 6',
};

const classes = [
    'icon-action',
    `icon-action--${props.variant}`,
];

const onClick = (event) => {
    if (props.disabled) {
        event.preventDefault();
        return;
    }

    emit('click', event);
};
</script>

<template>
    <Link
        v-if="as === 'link'"
        :href="href"
        :class="classes"
        :aria-label="label"
        :data-tooltip="label"
    >
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path :d="paths[icon] ?? paths.edit" />
        </svg>
    </Link>

    <button
        v-else
        type="button"
        :class="classes"
        :aria-label="label"
        :data-tooltip="label"
        :disabled="disabled"
        @click="onClick"
    >
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path :d="paths[icon] ?? paths.edit" />
        </svg>
    </button>
</template>

<style scoped>
.icon-action {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    margin-right: 6px;
    border: 1px solid #d6dee8;
    border-radius: 10px;
    background: #fff;
    color: #344256;
    cursor: pointer;
    text-decoration: none;
    transition: transform 0.16s ease, border-color 0.16s ease, background 0.16s ease, box-shadow 0.16s ease;
}

.icon-action:hover:not(:disabled) {
    transform: translateY(-1px);
    border-color: #9db1c9;
    background: #f8fbff;
    box-shadow: 0 8px 18px rgba(35, 48, 68, 0.12);
}

.icon-action:disabled {
    cursor: not-allowed;
    opacity: 0.45;
}

.icon-action svg {
    width: 17px;
    height: 17px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.icon-action--primary {
    border-color: #1d4ed8;
    background: #1d4ed8;
    color: #fff;
}

.icon-action--danger {
    border-color: #fecaca;
    background: #fff1f2;
    color: #b91c1c;
}

.icon-action--success {
    border-color: #bbf7d0;
    background: #f0fdf4;
    color: #15803d;
}

.icon-action::after {
    content: attr(data-tooltip);
    position: absolute;
    z-index: 20;
    left: 50%;
    bottom: calc(100% + 9px);
    transform: translate(-50%, 4px);
    width: max-content;
    max-width: 190px;
    padding: 6px 8px;
    border-radius: 8px;
    background: #0f172a;
    color: #fff;
    font-size: 11px;
    line-height: 1.2;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.14s ease, transform 0.14s ease;
    box-shadow: 0 12px 22px rgba(15, 23, 42, 0.2);
}

.icon-action::before {
    content: '';
    position: absolute;
    z-index: 20;
    left: 50%;
    bottom: calc(100% + 4px);
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #0f172a;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.14s ease;
}

.icon-action:hover::after,
.icon-action:hover::before,
.icon-action:focus-visible::after,
.icon-action:focus-visible::before {
    opacity: 1;
}

.icon-action:hover::after,
.icon-action:focus-visible::after {
    transform: translate(-50%, 0);
}
</style>
