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
import { ref, computed, onMounted } from 'vue';
import { Plus, Trash2, Image, Tag, ChevronLeft, Package, X } from 'lucide-vue-next';
import { useStandardSize } from '@/composables/useStandardSize';
import {
    addExtraSizeToVariant,
    extraSizesForVariant,
    getStandardStock,
    removeExtraSizeFromVariant,
    setStandardStock,
} from '@/types/settings';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Productos',
        href: '/productos',
    },
    {
        title: 'Nuevo Producto',
        href: '/productos/create',
    },
];

interface Category {
    id: number;
    name: string;
}

interface ProductVariant {
    color: string;
    image_url: string;
    sizes_stock: Record<string, number>;
    pendingFile?: File | null;
    tempSize?: string;
    tempStock?: number | null;
    showExtraSizes?: boolean;
}

const { standardSizeKey } = useStandardSize();

const form = ref({
    name: '',
    description: '',
    price: null as number | null,
    price_tiktok: null as number | null,
    discount: null as number | null,
    discount_active: false,
    oculto: false,
    category_id: null as number | null,
    tags_ia: [] as string[],
    variants: [] as ProductVariant[],
});

const categories = ref<Category[]>([]);
const loading = ref(false);
const newTag = ref('');
const monedaSimbolo = ref('S/');

const heroStats = computed(() => [{ label: 'Variantes', value: form.value.variants.length }]);

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

const onVariantPhotoSelected = (variant: ProductVariant, event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) variant.pendingFile = file;
};

const addVariant = () => {
    form.value.variants.push({
        color: '',
        image_url: '',
        sizes_stock: {},
        pendingFile: null,
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
    if (!form.value.price || form.value.price <= 0) {
        alert('El precio del producto debe ser mayor a 0');
        return;
    }

    loading.value = true;
    try {
        const response = await fetch('/api/products', {
            method: 'POST',
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
            throw new Error(err.message || 'Error al crear producto');
        }
        const created = await response.json();
        console.log('Producto creado:', created);
        
        const photoErrors: string[] = [];
        for (let i = 0; i < form.value.variants.length; i++) {
            const pending = form.value.variants[i].pendingFile;
            const createdVariant = created.data?.variants?.[i] || created.variants?.[i];
            if (pending && createdVariant?.id) {
                console.log('Subiendo foto para variant ID:', createdVariant.id, 'Archivo:', pending.name);
                
                const body = new FormData();
                body.append('photo', pending);
                
                const photoResponse = await fetch(`/api/product-variants/${createdVariant.id}/photo`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body,
                });
                
                console.log('Photo response status:', photoResponse.status);
                
                if (!photoResponse.ok) {
                    let message = `HTTP ${photoResponse.status}`;
                    try {
                        const err = await photoResponse.json();
                        message = err.message || err.errors?.photo?.[0] || message;
                    } catch {
                        message = (await photoResponse.text()) || message;
                    }
                    photoErrors.push(`${form.value.variants[i].color}: ${message}`);
                }
            }
        }

        if (photoErrors.length > 0) {
            alert(`Producto creado, pero falló la subida de fotos:\n\n${photoErrors.join('\n')}`);
            router.visit(`/productos/${created.data?.id || created.id}/edit`);
            return;
        }

        router.visit('/productos');
    } catch (error: any) {
        console.error('Error creating product:', error);
        alert(error.message || 'Error al crear producto');
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchCategories();
    fetchMoneda();
    addVariant();
});
</script>

<template>
    <Head title="Nuevo Producto" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="crm-page mx-auto max-w-6xl pb-20">
            <CrmPageHero
                compact
                title="Nuevo producto"
                description="Crea un nuevo artículo en el catálogo con sus variantes y stock por talla."
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
            <form id="product-create-form" @submit.prevent="submit" class="mt-6 space-y-6">
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
                        title="Precios de venta"
                        description="Precio normal para WhatsApp/web. Precio TikTok para clientas de TikTok Shop."
                    />

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
                                placeholder="Ej: 160.00 (opcional)"
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
                </CrmPanel>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
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
                                <Button type="button" @click="addTag">
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
                            title="Visibilidad"
                            description="Control manual de aparición en el catálogo del bot."
                        />
                        <div class="flex items-start gap-3">
                            <Checkbox
                                id="oculto"
                                :checked="form.oculto"
                                @update:checked="(value) => (form.oculto = Boolean(value))"
                            />
                            <div class="space-y-1">
                                <Label for="oculto" class="cursor-pointer font-medium">Ocultar producto del bot</Label>
                                <p class="text-xs text-muted-foreground">
                                    Manual. Disponible/agotado se calcula solo según el stock.
                                </p>
                            </div>
                        </div>
                    </CrmPanel>
                </div>

                <CrmPanel>
                    <ProductSectionHeader
                        step="4"
                        title="Variantes y stock"
                        description="Color, foto y stock en la talla estándar de la tienda."
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

                    <div v-else class="grid gap-4 xl:grid-cols-2">
                        <div
                            v-for="(variant, index) in form.variants"
                            :key="index"
                            class="rounded-xl border border-border bg-muted/15 p-4 shadow-sm"
                        >
                            <div class="mb-3 flex items-center justify-between border-b border-border/50 pb-3">
                                <h4 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                                        {{ index + 1 }}
                                    </span>
                                    Variante de color
                                </h4>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 px-2 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    @click="removeVariant(index)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>

                            <div class="space-y-4">
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
                                    <div class="flex items-center gap-3">
                                        <label class="flex h-20 w-28 shrink-0 cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-input bg-background p-3 text-center transition hover:border-primary hover:bg-primary/5">
                                            <Image class="h-5 w-5 text-muted-foreground" />
                                            <span class="mt-1 text-[10px] font-medium text-muted-foreground">Subir foto</span>
                                            <input
                                                type="file"
                                                accept="image/*"
                                                class="hidden"
                                                @change="onVariantPhotoSelected(variant, $event)"
                                            />
                                        </label>
                                        <div class="min-w-0 text-xs">
                                            <div v-if="variant.pendingFile" class="truncate font-medium text-foreground" :title="variant.pendingFile.name">
                                                {{ variant.pendingFile.name }}
                                            </div>
                                            <div v-else class="text-muted-foreground">Sin archivo</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-2 border-t border-border/50 pt-3">
                                    <Label class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Stock — {{ standardSizeKey }}
                                    </Label>
                                    <div class="flex items-center gap-2">
                                        <Input
                                            type="number"
                                            min="0"
                                            class="h-9 max-w-[120px]"
                                            :model-value="getStandardStock(variant, standardSizeKey)"
                                            placeholder="0"
                                            @update:model-value="(v) => updateStandardStock(variant, Number(v))"
                                        />
                                        <span class="text-xs text-muted-foreground">unidades</span>
                                    </div>

                                    <Button
                                        v-if="!variant.showExtraSizes"
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 px-2 text-xs text-muted-foreground"
                                        @click="variant.showExtraSizes = true"
                                    >
                                        <Plus class="h-3 w-3 mr-1" />
                                        Otra talla (opcional)
                                    </Button>

                                    <div v-if="variant.showExtraSizes" class="space-y-2 rounded-lg border border-dashed border-border p-3">
                                        <div v-if="Object.keys(extraSizesForVariant(variant, standardSizeKey)).length === 0" class="text-xs italic text-muted-foreground">
                                            Sin tallas extra.
                                        </div>
                                        <div v-else class="flex flex-wrap gap-2">
                                            <span
                                                v-for="(stock, size) in extraSizesForVariant(variant, standardSizeKey)"
                                                :key="size"
                                                class="inline-flex items-center rounded-full border border-border bg-muted px-3 py-0.5 text-xs font-medium text-foreground"
                                            >
                                                <span class="mr-1 font-bold text-primary">{{ size }}:</span> {{ stock }} ud
                                                <button type="button" class="ml-1.5 text-muted-foreground hover:text-foreground" @click="removeExtraSize(variant, size)">
                                                    <X class="h-3 w-3" />
                                                </button>
                                            </span>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <Input v-model="variant.tempSize" type="text" placeholder="Talla" class="h-9 w-24 text-xs" @keyup.enter="addExtraSize(variant)" />
                                            <Input v-model.number="variant.tempStock" type="number" placeholder="Stock" class="h-9 w-24 text-xs" min="0" @keyup.enter="addExtraSize(variant)" />
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
            </form>

            <ProductFormStickyBar
                form-id="product-create-form"
                :saving="loading"
                save-label="Crear producto"
            />
            </CrmAnimatedSection>
        </div>
    </AppLayout>
</template>
