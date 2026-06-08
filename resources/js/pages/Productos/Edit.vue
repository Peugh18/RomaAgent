<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CrmAnimatedSection from '@/components/crm/CrmAnimatedSection.vue';
import CrmPageHero from '@/components/crm/CrmPageHero.vue';
import CrmPanel from '@/components/crm/CrmPanel.vue';
import ProductFormStickyBar from '@/components/products/ProductFormStickyBar.vue';
import ProductSectionHeader from '@/components/products/ProductSectionHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, onMounted, watch, computed } from 'vue';
import { Plus, Trash2, Image, Tag, ChevronLeft, Package, X, Sparkles } from 'lucide-vue-next';
import { normalizeLaravelPagination } from '@/lib/pagination';
import { useStandardSize } from '@/composables/useStandardSize';
import {
    addExtraSizeToVariant,
    extraSizesForVariant,
    getStandardStock,
    removeExtraSizeFromVariant,
    setStandardStock,
} from '@/types/settings';

const props = defineProps<{
    product: {
        id: number;
        name: string;
        description: string | null;
        price: number | null;
        price_tiktok?: number | null;
        discount: number | null;
        discount_active?: boolean;
        category_id: number | null;
        status?: string;
        tags_ia: string[] | null;
        category: { id: number; name: string } | null;
        variants: {
            id: number;
            color: string;
            image_url: string | null;
            sizes_stock: Record<string, number>;
        }[];
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Productos',
        href: '/productos',
    },
    {
        title: 'Editar Producto',
        href: `/productos/${props.product.id}/edit`,
    },
];

interface Category {
    id: number;
    name: string;
}

interface ProductVariant {
    id?: number;
    color: string;
    image_url: string;
    image_path?: string | null;
    public_image_url?: string | null;
    sizes_stock: Record<string, number>;
    pendingFile?: File | null;
    uploading?: boolean;
    tempSize?: string;
    tempStock?: number | null;
    showExtraSizes?: boolean;
}

const { standardSizeKey } = useStandardSize();

const form = ref({
    name: props.product.name,
    description: props.product.description || '',
    price: props.product.price,
    price_tiktok: props.product.price_tiktok ?? null,
    discount: props.product.discount,
    discount_active: props.product.discount_active ?? false,
    oculto: props.product.status === 'oculto',
    category_id: props.product.category_id,
    tags_ia: props.product.tags_ia || [],
    variants: props.product.variants.map(v => ({
        id: v.id,
        color: v.color,
        image_url: v.image_url || '',
        image_path: (v as any).image_path || null,
        public_image_url: (v as any).public_image_url || v.image_url || null,
        sizes_stock: v.sizes_stock,
        pendingFile: null,
        uploading: false,
        tempSize: '',
        tempStock: null as number | null,
        showExtraSizes: false,
    })),
});

const categories = ref<Category[]>([]);
const allProducts = ref<{ id: number; name: string }[]>([]);
const similarIds = ref<number[]>([]);
const savingSimilares = ref(false);
const loading = ref(false);
const newTag = ref('');
const monedaSimbolo = ref('S/');

const categoryName = computed(() => {
    const category = categories.value.find((item) => item.id === form.value.category_id);
    return category?.name ?? props.product.category?.name ?? null;
});

const stockTotalProducto = computed(() =>
    form.value.variants.reduce(
        (sum, variant) =>
            sum +
            Object.values(variant.sizes_stock ?? {}).reduce(
                (acc, qty) => acc + Math.max(0, Number(qty)),
                0,
            ),
        0,
    ),
);

const estadoVentaLabel = computed(() => {
    if (form.value.oculto) {
        return 'Oculto (manual)';
    }

    return stockTotalProducto.value > 0 ? 'Disponible (automático)' : 'Agotado (automático)';
});

const heroStats = computed(() => [
    { label: 'Variantes', value: form.value.variants.length },
    { label: 'Stock', value: stockTotalProducto.value },
]);

const heroDescription = computed(() => {
    const category = categoryName.value;
    return category ? `Editando · ${category}` : 'Modifica precio, variantes, stock y similares.';
});

const fetchCategories = async () => {
    try {
        const response = await fetch('/api/categories', {
            headers: {
                'Accept': 'application/json',
            },
        });
        categories.value = await response.json();
    } catch (error) {
        console.error('Error fetching categories:', error);
    }
};

const fetchMoneda = async () => {
    try {
        const response = await fetch('/api/company-settings', { headers: { Accept: 'application/json' } });
        const settings = await response.json();
        monedaSimbolo.value = settings.moneda === 'USD' ? '$' : settings.moneda === 'EUR' ? '€' : 'S/';
    } catch {
        monedaSimbolo.value = 'S/';
    }
};

const pendingPreviewUrls = ref<Record<number, string>>({});

const variantPreviewUrl = (variant: ProductVariant, index: number): string | null => {
    if (variant.pendingFile) {
        if (!pendingPreviewUrls.value[index]) {
            pendingPreviewUrls.value[index] = URL.createObjectURL(variant.pendingFile);
        }
        return pendingPreviewUrls.value[index];
    }
    if (variant.public_image_url) return variant.public_image_url;
    if (variant.image_path) return `/storage/${variant.image_path}`;
    return variant.image_url || null;
};

const clearPendingPreview = (index: number): void => {
    const url = pendingPreviewUrls.value[index];
    if (url) {
        URL.revokeObjectURL(url);
        delete pendingPreviewUrls.value[index];
    }
};

const onVariantPhotoSelected = async (variant: ProductVariant, index: number, event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }

    clearPendingPreview(index);
    variant.pendingFile = file;

    if (variant.id) {
        await uploadVariantPhoto(variant, index);
        return;
    }
};

const uploadVariantPhotoForId = async (variantId: number, file: File): Promise<{ image_path: string; public_url: string }> => {
    const body = new FormData();
    body.append('photo', file);

    const response = await fetch(`/api/product-variants/${variantId}/photo`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
        body,
    });

    if (!response.ok) {
        let message = `No se pudo subir la foto (HTTP ${response.status})`;
        try {
            const err = await response.json();
            message = err.message || err.errors?.photo?.[0] || message;
        } catch {
            // respuesta no JSON
        }
        throw new Error(message);
    }

    return response.json();
};

const uploadVariantPhoto = async (variant: ProductVariant, index?: number) => {
    if (!variant.id) {
        alert('Guarda el producto primero (botón Guardar abajo). La foto se subirá automáticamente al guardar.');
        return;
    }
    if (!variant.pendingFile) {
        alert('Selecciona una imagen primero.');
        return;
    }

    variant.uploading = true;
    try {
        const data = await uploadVariantPhotoForId(variant.id, variant.pendingFile);
        variant.image_path = data.image_path;
        variant.public_image_url = data.public_url;
        variant.image_url = '';
        variant.pendingFile = null;
        if (index !== undefined) {
            clearPendingPreview(index);
        }
    } catch (error: any) {
        console.error('Error al subir foto:', error);
        alert(`Error al subir foto (${variant.color || 'variante'}): ${error.message}`);
    } finally {
        variant.uploading = false;
    }
};

const resolveSavedVariantId = (
    variant: ProductVariant,
    savedVariants: Array<{ id: number; color?: string }>,
): number | null => {
    if (variant.id) {
        return variant.id;
    }

    const color = variant.color?.trim().toLowerCase();
    if (!color) {
        return null;
    }

    const match = savedVariants.find((saved) => saved.color?.trim().toLowerCase() === color);

    return match?.id ?? null;
};

const uploadPendingVariantPhotos = async (savedVariants: Array<{ id: number; color?: string }>) => {
    const errors: string[] = [];

    for (let i = 0; i < form.value.variants.length; i++) {
        const variant = form.value.variants[i];
        const pending = variant.pendingFile;
        if (!pending) {
            continue;
        }

        const targetId = resolveSavedVariantId(variant, savedVariants);
        if (!targetId) {
            errors.push(`${variant.color || `Variante ${i + 1}`}: no se encontró la variante guardada`);
            continue;
        }

        try {
            const data = await uploadVariantPhotoForId(targetId, pending);
            variant.id = targetId;
            variant.image_path = data.image_path;
            variant.public_image_url = data.public_url;
            variant.image_url = '';
            variant.pendingFile = null;
            clearPendingPreview(i);
        } catch (error: any) {
            errors.push(`${variant.color || `Variante ${i + 1}`}: ${error.message}`);
        }
    }

    return errors;
};

const addVariant = () => {
    form.value.variants.push({
        color: '',
        image_url: '',
        sizes_stock: {},
        pendingFile: null,
        uploading: false,
        tempSize: '',
        tempStock: null,
        showExtraSizes: false,
    });
};

const removeVariant = (index: number) => {
    form.value.variants.splice(index, 1);
};

const updateStandardStock = (variant: ProductVariant, value: number | null) => {
    setStandardStock(variant, standardSizeKey.value, Math.max(0, Number(value ?? 0)));
};

const addExtraSize = (variant: ProductVariant) => {
    if (!addExtraSizeToVariant(variant, standardSizeKey.value)) {
        return;
    }
    variant.showExtraSizes = true;
};

const removeExtraSize = (variant: ProductVariant, size: string) => {
    removeExtraSizeFromVariant(variant, size, standardSizeKey.value);
};

const syncExtraSizesVisibility = () => {
    for (const variant of form.value.variants) {
        variant.showExtraSizes =
            Object.keys(extraSizesForVariant(variant, standardSizeKey.value)).length > 0;
    }
};

watch(standardSizeKey, syncExtraSizesVisibility);

const addTag = () => {
    if (newTag.value.trim()) {
        form.value.tags_ia.push(newTag.value.trim());
        newTag.value = '';
    }
};

const removeTag = (index: number) => {
    form.value.tags_ia.splice(index, 1);
};

const getCsrfToken = (): string => {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
};

const submit = async () => {
    if (!form.value.price || Number(form.value.price) <= 0) {
        alert('El precio del producto debe ser mayor a 0');
        return;
    }

    loading.value = true;
    try {
        const response = await fetch(`/api/products/${props.product.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(form.value),
        });
        if (!response.ok) {
            if (response.status === 419) {
                alert('Sesión expirada. Recarga la página (F5) e intenta de nuevo.');
                return;
            }
            const err = await response.json();
            throw new Error(err.message || 'Error al actualizar');
        }

        const updated = await response.json();
        const photoErrors = await uploadPendingVariantPhotos(updated.variants ?? []);

        if (photoErrors.length > 0) {
            alert(`Producto guardado, pero falló la subida de fotos:\n\n${photoErrors.join('\n')}`);
            return;
        }

        router.visit('/productos');
    } catch (error: any) {
        console.error('Error updating product:', error);
        alert(error.message || 'Error al actualizar producto');
    } finally {
        loading.value = false;
    }
};

const fetchAllProducts = async () => {
    try {
        const list = await fetch('/api/products?per_page=100', { headers: { Accept: 'application/json' } }).then((r) => r.json());
        const normalized = normalizeLaravelPagination<{ id: number; name: string }>(list);
        allProducts.value = normalized.data.filter((p) => p.id !== props.product.id);
    } catch {
        allProducts.value = [];
    }
};

const fetchSimilares = async () => {
    try {
        const res = await fetch(`/api/products/${props.product.id}/similares`, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        similarIds.value = (data.manual ?? []).map((m: { id: number }) => m.id);
    } catch {
        similarIds.value = [];
    }
};

const saveSimilares = async () => {
    savingSimilares.value = true;
    try {
        await fetch(`/api/products/${props.product.id}/similares`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ similar_product_ids: similarIds.value }),
        });
        alert('Similares guardados');
    } catch {
        alert('No se pudieron guardar los similares');
    } finally {
        savingSimilares.value = false;
    }
};

const toggleSimilar = (id: number) => {
    const idx = similarIds.value.indexOf(id);
    if (idx >= 0) {
        similarIds.value.splice(idx, 1);
    } else if (similarIds.value.length < 5) {
        similarIds.value.push(id);
    }
};

onMounted(() => {
    fetchCategories();
    fetchMoneda();
    fetchAllProducts();
    fetchSimilares();
    syncExtraSizesVisibility();
});
</script>

<template>
    <Head title="Editar Producto" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page mx-auto max-w-6xl pb-20">
            <CrmPageHero
                compact
                :title="form.name || product.name"
                :description="heroDescription"
                :icon="Package"
                variant="violet"
                :stats="heroStats"
            >
                <template #actions>
                    <Button variant="outline" as-child class="border-border/70 bg-background/60">
                        <Link href="/productos" class="flex items-center gap-1">
                            <ChevronLeft class="h-4 w-4" />
                            <span>Volver</span>
                        </Link>
                    </Button>
                </template>
            </CrmPageHero>

            <CrmAnimatedSection :delay="80">
            <form id="product-edit-form" @submit.prevent="submit" class="mt-6 space-y-6">
                <div class="grid gap-6 xl:grid-cols-2">
                <CrmPanel>
                    <ProductSectionHeader
                        step="1"
                        title="Información general"
                        description="Nombre, categoría y descripción del artículo."
                    />

                    <div class="grid grid-cols-1 gap-y-6 sm:grid-cols-6 sm:gap-x-6">
                        <div class="sm:col-span-4 space-y-1.5">
                            <Label for="name">Nombre del producto</Label>
                            <Input
                                type="text"
                                id="name"
                                v-model="form.name"
                                required
                                placeholder="Ej: Camiseta Oversize Negra"
                            />
                        </div>

                        <div class="sm:col-span-4 space-y-1.5">
                            <Label for="category">Categoría</Label>
                            <select
                                id="category"
                                v-model="form.category_id"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option :value="null">Sin categoría</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                        </div>

                        <div class="sm:col-span-6 space-y-1.5">
                            <Label for="description">Descripción</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                placeholder="Escribe detalles del producto..."
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </div>
                    </div>
                </CrmPanel>

                <CrmPanel>
                    <ProductSectionHeader
                        step="2"
                        title="Precios y visibilidad"
                        description="Precio normal, TikTok y estado de venta del bot."
                    />

                    <div class="mb-4 rounded-lg border border-border bg-muted/15 px-4 py-3 text-sm">
                        <span class="text-muted-foreground">Estado de venta:</span>
                        <span class="ml-2 font-medium text-foreground">{{ estadoVentaLabel }}</span>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Disponible/agotado se actualiza solo cuando el stock llega a 0.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-y-6 sm:grid-cols-2 sm:gap-x-6">
                        <div class="space-y-1.5">
                            <Label for="price">Precio normal ({{ monedaSimbolo }})</Label>
                            <Input
                                type="number"
                                id="price"
                                v-model="form.price"
                                step="0.01"
                                min="0.01"
                                required
                                placeholder="Ej: 180.00"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="price-tiktok">Precio TikTok ({{ monedaSimbolo }})</Label>
                            <Input
                                type="number"
                                id="price-tiktok"
                                v-model="form.price_tiktok"
                                step="0.01"
                                min="0.01"
                                placeholder="Ej: 160.00"
                            />
                            <p class="text-xs text-muted-foreground">No se ve afectado por el descuento promo.</p>
                        </div>
                    </div>

                    <div class="mt-6 rounded-lg border border-border bg-muted/15 p-4 space-y-4">
                        <div class="flex items-start gap-3">
                            <Checkbox
                                id="discount-active"
                                :checked="form.discount_active"
                                @update:checked="(value) => (form.discount_active = Boolean(value))"
                            />
                            <div class="space-y-1">
                                <Label for="discount-active" class="cursor-pointer font-medium">
                                    Activar descuento promo
                                </Label>
                                <p class="text-xs text-muted-foreground">
                                    Solo descuenta el precio normal. TikTok mantiene su precio.
                                </p>
                            </div>
                        </div>

                        <div v-if="form.discount_active" class="space-y-1.5 max-w-xs">
                            <Label for="discount">Monto de descuento ({{ monedaSimbolo }})</Label>
                            <Input
                                type="number"
                                id="discount"
                                v-model="form.discount"
                                step="0.01"
                                min="0.01"
                                placeholder="Ej: 10.00"
                            />
                            <p
                                v-if="form.price && form.discount && Number(form.discount) > 0"
                                class="text-xs font-medium text-emerald-700 dark:text-emerald-400"
                            >
                                Precio normal con promo:
                                {{ monedaSimbolo }}
                                {{ Math.max(0.01, Number(form.price) - Number(form.discount)).toFixed(2) }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex items-start gap-3 rounded-lg border border-border bg-muted/15 p-4">
                        <Checkbox
                            id="oculto"
                            :checked="form.oculto"
                            @update:checked="(value) => (form.oculto = Boolean(value))"
                        />
                        <div class="space-y-1">
                            <Label for="oculto" class="cursor-pointer font-medium">Ocultar producto del bot</Label>
                            <p class="text-xs text-muted-foreground">
                                Manual. Si está oculto, no aparece en el catálogo aunque tenga stock.
                            </p>
                        </div>
                    </div>
                </CrmPanel>
                </div>

                <CrmPanel>
                    <ProductSectionHeader
                        step="3"
                        :icon="Tag"
                        title="Etiquetas para la IA"
                        description="Ayudan al bot a entender el tipo de producto al responder consultas."
                    />

                    <div class="space-y-4">
                        <div class="flex gap-2">
                            <Input
                                type="text"
                                v-model="newTag"
                                @keyup.enter="addTag"
                                placeholder="Ej: elegante, algodon, urbano..."
                                class="flex-1"
                            />
                            <Button
                                type="button"
                                @click="addTag"
                            >
                                <Plus class="h-4 w-4 mr-1.5" />
                                Agregar
                            </Button>
                        </div>
                        <div v-if="form.tags_ia.length === 0" class="text-xs text-muted-foreground italic">
                            No hay etiquetas configuradas.
                        </div>
                        <div v-else class="flex flex-wrap gap-1.5">
                            <span
                                v-for="(tag, index) in form.tags_ia"
                                :key="index"
                                class="inline-flex items-center rounded-full bg-primary/10 border border-primary/20 px-3 py-0.5 text-xs font-semibold text-primary animate-fade-in"
                            >
                                {{ tag }}
                                <button type="button" @click="removeTag(index)" class="ml-1.5 text-primary hover:text-primary/75 focus:outline-none">
                                    <X class="h-3 w-3" />
                                </button>
                            </span>
                        </div>
                    </div>
                </CrmPanel>

                <CrmPanel>
                    <ProductSectionHeader
                        step="4"
                        title="Variantes y stock"
                        description="Colores, fotos y stock disponible por talla."
                    >
                        <template #actions>
                            <Button type="button" variant="secondary" size="sm" @click="addVariant">
                                <Plus class="h-4 w-4 mr-1.5" />
                                Agregar variante
                            </Button>
                        </template>
                    </ProductSectionHeader>

                    <div v-if="form.variants.length === 0" class="text-center py-8 border border-dashed border-border rounded-xl text-muted-foreground text-sm">
                        No hay variantes configuradas. Agrega al menos una para continuar.
                    </div>

                    <div v-else class="space-y-6">
                        <div 
                            v-for="(variant, index) in form.variants" 
                            :key="index" 
                            class="border border-border bg-muted/15 p-6 rounded-xl relative shadow-sm animate-in fade-in-50 duration-200"
                        >
                            <div class="flex items-center justify-between mb-4 pb-3 border-b border-border/50">
                                <h4 class="text-sm font-semibold text-foreground flex items-center gap-1.5">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                                        {{ index + 1 }}
                                    </span>
                                    Variante de Color
                                </h4>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive hover:text-destructive hover:bg-destructive/10 h-8 px-2"
                                    @click="removeVariant(index)"
                                >
                                    <Trash2 class="h-4 w-4 mr-1" />
                                    <span>Eliminar</span>
                                </Button>
                            </div>

                            <div class="grid grid-cols-1 gap-y-6 sm:grid-cols-2 sm:gap-x-6">
                                <div class="space-y-1.5">
                                    <Label>Color / Nombre del tono</Label>
                                    <Input
                                        type="text"
                                        v-model="variant.color"
                                        required
                                        placeholder="Ej: Negro, Rojo, Azul Marino"
                                    />
                                </div>

                                <div class="space-y-1.5">
                                    <Label>Foto por color</Label>
                                    <div class="flex flex-col gap-3">
                                        <div class="flex items-center gap-3">
                                            <label class="flex flex-col items-center justify-center border border-dashed border-input hover:border-primary bg-background hover:bg-primary/5 rounded-lg p-4 cursor-pointer transition text-center shrink-0 w-28 h-20">
                                                <Image class="h-5 w-5 text-muted-foreground" />
                                                <span class="text-[10px] text-muted-foreground mt-1 font-medium">Subir foto</span>
                                                <input
                                                    type="file"
                                                    accept="image/*"
                                                    class="hidden"
                                                    @change="onVariantPhotoSelected(variant, index, $event)"
                                                />
                                            </label>
                                            <div class="text-xs flex-1">
                                                <div v-if="variant.pendingFile" class="font-medium text-foreground truncate max-w-[200px]" :title="variant.pendingFile.name">
                                                    {{ variant.pendingFile.name }}
                                                </div>
                                                <div v-else class="text-muted-foreground">
                                                    Ningún archivo seleccionado
                                                </div>
                                                <div class="mt-2 flex gap-1.5">
                                                    <Button
                                                        type="button"
                                                        size="xs"
                                                        class="h-7 text-[10px]"
                                                        @click="uploadVariantPhoto(variant, index)"
                                                        :disabled="!variant.pendingFile || variant.uploading"
                                                    >
                                                        {{ variant.uploading ? 'Subiendo...' : (variant.id ? 'Reintentar subida' : 'Subir Foto Ahora') }}
                                                    </Button>
                                                </div>
                                                <p class="text-[9px] text-muted-foreground/75 mt-1">
                                                    {{ variant.id
                                                        ? 'Al elegir archivo se sube automáticamente. Si falla, usa «Reintentar subida».'
                                                        : 'Nueva variante: guarda el producto y la foto se sube sola al guardar.' }}
                                                </p>
                                                <p v-if="!variant.id" class="text-[9px] text-amber-600 mt-1 font-medium">Nueva variante: se creará al guardar el producto.</p>
                                            </div>
                                        </div>

                                        <!-- Vista Previa de Imagen -->
                                        <div v-if="variantPreviewUrl(variant, index)" class="mt-1">
                                            <div class="text-[10px] text-muted-foreground mb-1">
                                                {{ variant.pendingFile ? 'Vista previa (pendiente de guardar):' : 'Imagen guardada:' }}
                                            </div>
                                            <img
                                                :src="variantPreviewUrl(variant, index)!"
                                                alt="Vista previa"
                                                class="h-28 w-28 rounded-md object-cover border border-border bg-muted/20"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stock talla estándar -->
                            <div class="mt-6 pt-4 border-t border-border/50">
                                <Label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider block mb-2">
                                    Stock — talla {{ standardSizeKey }} (estándar)
                                </Label>
                                <p class="text-xs text-muted-foreground mb-3">
                                    Toda la tienda usa una talla estándar. Solo indica cuántas unidades hay de este color.
                                </p>
                                <div class="flex items-center gap-2 max-w-xs">
                                    <Input
                                        type="number"
                                        min="0"
                                        class="h-9"
                                        :model-value="getStandardStock(variant, standardSizeKey)"
                                        placeholder="0"
                                        @update:model-value="(v) => updateStandardStock(variant, Number(v))"
                                    />
                                    <span class="text-xs text-muted-foreground whitespace-nowrap">unidades</span>
                                </div>

                                <div class="mt-4">
                                    <Button
                                        v-if="!variant.showExtraSizes"
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 px-2 text-xs text-muted-foreground"
                                        @click="variant.showExtraSizes = true"
                                    >
                                        <Plus class="h-3 w-3 mr-1" />
                                        Agregar otra talla (opcional)
                                    </Button>

                                    <div v-if="variant.showExtraSizes" class="mt-2 space-y-3 rounded-lg border border-dashed border-border p-3">
                                        <p class="text-xs font-medium text-muted-foreground">Otras tallas (opcional)</p>
                                        <div v-if="Object.keys(extraSizesForVariant(variant, standardSizeKey)).length === 0" class="text-xs text-muted-foreground italic">
                                            Sin tallas extra.
                                        </div>
                                        <div v-else class="flex flex-wrap gap-2">
                                            <span
                                                v-for="(stock, size) in extraSizesForVariant(variant, standardSizeKey)"
                                                :key="size"
                                                class="inline-flex items-center rounded-full bg-muted border border-border px-3 py-0.5 text-xs font-medium text-foreground"
                                            >
                                                <span class="font-bold text-primary mr-1">{{ size }}:</span> {{ stock }} ud
                                                <button
                                                    type="button"
                                                    class="ml-1.5 text-muted-foreground hover:text-foreground"
                                                    @click="removeExtraSize(variant, size)"
                                                >
                                                    <X class="h-3 w-3" />
                                                </button>
                                            </span>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2 max-w-md">
                                            <div class="w-32">
                                                <Input
                                                    v-model="variant.tempSize"
                                                    type="text"
                                                    placeholder="Ej: S, 38"
                                                    class="h-9 text-xs"
                                                    @keyup.enter="addExtraSize(variant)"
                                                />
                                            </div>
                                            <div class="w-32">
                                                <Input
                                                    v-model.number="variant.tempStock"
                                                    type="number"
                                                    placeholder="Stock"
                                                    class="h-9 text-xs"
                                                    min="0"
                                                    @keyup.enter="addExtraSize(variant)"
                                                />
                                            </div>
                                            <Button type="button" variant="outline" size="sm" class="h-9 px-3 text-xs" @click="addExtraSize(variant)">
                                                <Plus class="h-3 w-3 mr-1" />
                                                Añadir
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CrmPanel>

                <CrmPanel>
                    <ProductSectionHeader
                        step="5"
                        :icon="Sparkles"
                        title="Productos similares"
                        description="Si este modelo se agota, el bot ofrecerá estas alternativas (máx. 5)."
                    />

                    <div class="space-y-4">
                        <div class="flex flex-wrap gap-2 max-h-40 overflow-y-auto p-1 border border-border rounded-lg bg-muted/10">
                            <button
                                v-for="p in allProducts"
                                :key="p.id"
                                type="button"
                                class="rounded-full px-3 py-1.5 text-xs border transition duration-150 font-medium select-none"
                                :class="
                                    similarIds.includes(p.id)
                                        ? 'bg-primary text-primary-foreground border-primary'
                                        : 'bg-background hover:bg-muted text-foreground border-border'
                                "
                                @click="toggleSimilar(p.id)"
                            >
                                {{ p.name }}
                            </button>
                        </div>
                        <div class="flex justify-between items-center gap-2">
                            <span class="text-xs text-muted-foreground">
                                Seleccionados: {{ similarIds.length }} de 5
                            </span>
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                :disabled="savingSimilares"
                                @click="saveSimilares"
                            >
                                {{ savingSimilares ? 'Guardando...' : 'Guardar similares ahora' }}
                            </Button>
                        </div>
                    </div>
                </CrmPanel>
            </form>

            <ProductFormStickyBar
                form-id="product-edit-form"
                :saving="loading"
                save-label="Guardar cambios"
            />
            </CrmAnimatedSection>
        </div>
    </AppLayout>
</template>
