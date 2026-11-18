<script setup lang="ts">
const PATHS = {
    panel: 'M4 13h6V4H4v9Zm10 7h6v-9h-6v9ZM4 20h6v-3H4v3ZM14 4v3h6V4h-6Z',
    truck: 'M2 16V6a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v10M14 9h4l3 3.5V16h-1M2 16h3m4 0h6M7 18a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z',
    route: 'M6 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm12-10a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM8 17h6a3 3 0 0 0 0-6h-4a3 3 0 0 1 0-6h6',
    crew: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0',
    wrench: 'M14.5 6.5a4 4 0 0 0 5 5L11 20a2.1 2.1 0 0 1-3-3l8.5-8.5a4 4 0 0 0-2-2Z',
    plus: 'M12 5v14M5 12h14',
    search: 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Zm9 2-4-4',
    pencil: 'M4 20h4L19 9l-4-4L4 16v4Zm9-13 4 4',
    trash: 'M5 7h14M10 7V4h4v3m-7 0 1 13h8l1-13M10 11v6m4-6v6',
    close: 'M6 6l12 12M18 6 6 18',
    menu: 'M4 7h16M4 12h16M4 17h16',
    logout: 'M15 17l5-5-5-5M20 12H9M12 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6',
    users: 'M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm-6 9a6 6 0 0 1 12 0m1-16.5a3.5 3.5 0 0 1 0 7M21 20a6 6 0 0 0-3.5-5.5',
    ban: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM5.6 5.6l12.8 12.8',
    undo: 'M9 14 4 9l5-5M4 9h10.5a5.5 5.5 0 0 1 0 11H11',
    shield: 'M12 3l7 3v5c0 4.5-3 8.2-7 10-4-1.8-7-5.5-7-10V6l7-3Zm-3 9 2 2 4-4',
    alert: 'M12 9v4m0 4h.01M10.3 4.3 2.8 17.5A2 2 0 0 0 4.5 20.5h15a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0Z',
    clock: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-14v5l3 2',
    gauge: 'M5 18a8 8 0 1 1 14 0M12 14l4-5M12 15a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z',
    coins: 'M9 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm5.5-1.8A5 5 0 1 1 11 20.6M9 6v4m-1.5-3h3',
    license: 'M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Zm4.5 6a1.75 1.75 0 1 0 0-3.5 1.75 1.75 0 0 0 0 3.5ZM5.5 15.5a3 3 0 0 1 6 0M14 9.5h4M14 13h4',
    check: 'M5 12.5l4.5 4.5L19 7.5',
    download: 'M12 4v11m0 0 4-4m-4 4-4-4M5 19h14',
    'arrow-up': 'M12 19V5m0 0-5 5m5-5 5 5',
    'arrow-down': 'M12 5v14m0 0 5-5m-5 5-5-5',
    signpost: 'M12 3v18M12 6h6l2 2-2 2h-6M12 12H6l-2 2 2 2h6',
    fuel: 'M5 20V6a2 2 0 0 1 2-2h5a2 2 0 0 1 2 2v14M4 20h11M7.5 8h4M14 11h1.5a1.5 1.5 0 0 1 1.5 1.5V16a1.5 1.5 0 0 0 3 0V9.5L17 6.5',
    calendar: 'M7 3v3m10-3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z',
    'chevron-left': 'M14.5 6 8.5 12l6 6',
    'chevron-right': 'M9.5 6l6 6-6 6',
    info: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-10v5m0-8h.01',
} as const;

export type IconName = keyof typeof PATHS;

defineProps<{ name: IconName }>();
</script>

<template>
    <svg
        viewBox="0 0 24 24"
        width="20"
        height="20"
        fill="none"
        stroke="currentColor"
        stroke-width="1.75"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path :d="PATHS[name]" />
    </svg>
</template>
