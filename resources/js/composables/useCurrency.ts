import { computed, ref } from 'vue';

export type CurrencyCode = 'PEN' | 'USD' | 'EUR';

const currentCurrency = ref<CurrencyCode>('PEN');

const currencySymbols: Record<CurrencyCode, string> = {
    PEN: 'S/',
    USD: '$',
    EUR: '€',
};

export function setGlobalCurrency(code: CurrencyCode): void {
    if (code in currencySymbols) {
        currentCurrency.value = code;
    }
}

export function useCurrency(currency?: CurrencyCode) {
    const targetCurrency = currency ?? currentCurrency.value;

    const symbol = computed(() => currencySymbols[targetCurrency] ?? 'S/');

    const format = (value: number | string | null | undefined, fallback: string = '—'): string => {
        if (value === null || value === undefined) {
            return fallback;
        }

        const num = typeof value === 'string' ? parseFloat(value) : value;

        if (!Number.isFinite(num) || num <= 0) {
            return fallback;
        }

        return `${symbol.value} ${num.toFixed(2)}`;
    };

    const formatWithPrefix = (value: number | string | null | undefined, prefix: string): string => {
        if (value === null || value === undefined) {
            return prefix;
        }

        const num = typeof value === 'string' ? parseFloat(value) : value;

        if (!Number.isFinite(num) || num < 0) {
            return prefix;
        }

        return `${prefix} ${symbol.value} ${num.toFixed(2)}`;
    };

    const formatDiscount = (price: number, discount: number): string => {
        const promo = Math.max(0.01, price - discount);
        return `${symbol.value} ${promo.toFixed(2)} (-${symbol.value} ${discount.toFixed(2)})`;
    };

    return {
        symbol,
        format,
        formatWithPrefix,
        formatDiscount,
    };
}
