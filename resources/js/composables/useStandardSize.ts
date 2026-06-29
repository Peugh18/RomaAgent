import { onMounted, ref } from 'vue';
import { apiJson } from '@/composables/useApi';
import { DEFAULT_STANDARD_SIZE, normalizeSizeKey } from '@/types/settings';
import { usePage } from '@inertiajs/vue3';

const cachedStandardSize = ref(normalizeSizeKey(DEFAULT_STANDARD_SIZE));
let loaded = false;

export function useStandardSize() {
    const page = usePage();
    const standardSizeKey = cachedStandardSize;
    const loading = ref(!loaded);

    const load = async () => {
        // Intentar leer de las props compartidas globales de Inertia primero
        const sharedSize = page.props.company?.standard_size as string | undefined;
        if (sharedSize) {
            cachedStandardSize.value = normalizeSizeKey(sharedSize);
            loaded = true;
            loading.value = false;
            return;
        }

        if (loaded) {
            loading.value = false;
            return;
        }

        try {
            const data = await apiJson<{ standard_size?: string; empresa?: { standard_size?: string } } | null>(
                '/api/company-settings',
            );
            const size = data?.empresa?.standard_size?.trim() || data?.standard_size?.trim();
            if (size) {
                cachedStandardSize.value = normalizeSizeKey(size);
            }
        } catch {
            cachedStandardSize.value = normalizeSizeKey(DEFAULT_STANDARD_SIZE);
        } finally {
            loaded = true;
            loading.value = false;
        }
    };

    onMounted(load);

    return {
        standardSizeKey,
        loading,
        reloadStandardSize: load,
    };
}

/** Llamar tras guardar configuración de empresa para refrescar la talla en memoria. */
export function invalidateStandardSizeCache(next?: string): void {
    cachedStandardSize.value = normalizeSizeKey(next ?? DEFAULT_STANDARD_SIZE);
    loaded = true;
}
