import { onMounted, ref } from 'vue';

function readCssHsl(variable: string, fallback: string): string {
    if (typeof document === 'undefined') {
        return fallback;
    }

    const value = getComputedStyle(document.documentElement).getPropertyValue(variable).trim();

    return value !== '' ? `hsl(${value})` : fallback;
}

export function useChartTheme() {
    const primary = ref('hsl(142 76% 36%)');
    const mutedForeground = ref('hsl(0 0% 63%)');
    const border = ref('hsl(0 0% 20%)');
    const popover = ref('hsl(0 0% 10%)');
    const popoverForeground = ref('hsl(0 0% 98%)');
    const background = ref('hsl(0 0% 7%)');

    const refresh = (): void => {
        primary.value = readCssHsl('--primary', primary.value);
        mutedForeground.value = readCssHsl('--muted-foreground', mutedForeground.value);
        border.value = readCssHsl('--border', border.value);
        popover.value = readCssHsl('--popover', popover.value);
        popoverForeground.value = readCssHsl('--popover-foreground', popoverForeground.value);
        background.value = readCssHsl('--background', background.value);
    };

    onMounted(() => {
        refresh();

        const observer = new MutationObserver(refresh);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });
    });

    return {
        primary,
        mutedForeground,
        border,
        popover,
        popoverForeground,
        background,
        refresh,
    };
}
