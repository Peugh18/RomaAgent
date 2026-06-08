import { ref, computed, reactive } from 'vue'

// Types
interface Stats {
  total_products: number
  total_variants: number
  variants_with_embeddings: number
  recent_embeddings: number
  products_complete: number
  completion_percentage: number
}

interface Product {
  id: number
  name: string
  total_variants: number
  variants_with_embeddings: number
  variants?: Array<{
    id: number
    color: string
    embedding_at?: string
  }>
}

interface ProcessingResult {
  processed: number
  success: number
  failed: number
  found?: number
  errors?: string[]
  details?: Array<{
    product_name: string
    color: string
    error?: string
  }>
  message?: string
}

interface Toast {
  show: boolean
  type: 'success' | 'error' | 'warning' | 'info'
  title: string
  message: string
}

// Helper para obtener CSRF token
const getCsrfToken = (): string => {
  const meta = document.querySelector('meta[name="csrf-token"]')
  return meta?.getAttribute('content') || ''
}

export function useVisionEmbeddings() {
  // State
  const stats = ref<Stats>({
    total_products: 0,
    total_variants: 0,
    variants_with_embeddings: 0,
    recent_embeddings: 0,
    products_complete: 0,
    completion_percentage: 0
  })

  const products = ref<Product[]>([])
  const loading = ref(false)
  const processing = ref(false)
  const selectedIds = ref<number[]>([])
  const search = ref('')
  const statusFilter = ref('all')
  const currentPage = ref(1)
  const error = ref<string | null>(null)

  // Pagination
  const pagination = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0
  })

  // Toast notification
  const toast = reactive<Toast>({
    show: false,
    type: 'info',
    title: '',
    message: ''
  })

  let toastTimeout: ReturnType<typeof setTimeout> | null = null

  const showToast = (type: Toast['type'], title: string, message: string, duration = 5000) => {
    if (toastTimeout) clearTimeout(toastTimeout)
    
    toast.type = type
    toast.title = title
    toast.message = message
    toast.show = true
    
    toastTimeout = setTimeout(() => {
      toast.show = false
    }, duration)
  }

  const hideToast = () => {
    toast.show = false
    if (toastTimeout) clearTimeout(toastTimeout)
  }

  // Computed
  const selectedCount = computed(() => selectedIds.value.length)
  const hasSelection = computed(() => selectedCount.value > 0)
  const allSelected = computed(() => 
    products.value.length > 0 && selectedIds.value.length === products.value.length
  )
  const someSelected = computed(() => 
    selectedIds.value.length > 0 && selectedIds.value.length < products.value.length
  )

  const completionColor = computed(() => {
    const pct = stats.value.completion_percentage
    if (pct >= 90) return 'text-green-600'
    if (pct >= 70) return 'text-yellow-600'
    return 'text-red-600'
  })

  // Methods
  const loadStats = async () => {
    try {
      error.value = null
      const response = await fetch('/admin/vision/embeddings/stats', {
        headers: {
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json'
        }
      })
      if (!response.ok) throw new Error('Error loading stats')
      stats.value = await response.json()
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Unknown error'
      console.error('Error loading stats:', err)
    }
  }

  const loadProducts = async () => {
    try {
      loading.value = true
      error.value = null
      
      const params = new URLSearchParams({
        page: currentPage.value.toString(),
        search: search.value,
        status: statusFilter.value
      })
      
      const response = await fetch(`/admin/vision/embeddings/products?${params}`, {
        headers: {
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json'
        }
      })
      if (!response.ok) throw new Error('Error loading products')
      
      const data = await response.json()
      products.value = data.data
      
      // Update pagination
      Object.assign(pagination, {
        current_page: data.current_page,
        last_page: data.last_page,
        per_page: data.per_page,
        total: data.total
      })
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Unknown error'
      console.error('Error loading products:', err)
    } finally {
      loading.value = false
    }
  }

  const processProduct = async (productId: number): Promise<ProcessingResult | null> => {
    try {
      loading.value = true
      error.value = null
      
      const response = await fetch(`/admin/vision/embeddings/products/${productId}`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json'
        }
      })
      
      if (!response.ok) throw new Error('Error processing product')
      
      const result = await response.json()
      
      // Refresh data
      await Promise.all([loadStats(), loadProducts()])
      
      return result
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Unknown error'
      console.error('Error processing product:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const processSelected = async (): Promise<boolean> => {
    if (!hasSelection.value) return false
    
    try {
      processing.value = true
      error.value = null
      
      const response = await fetch('/admin/vision/embeddings/process-batch', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json'
        },
        body: JSON.stringify({ product_ids: selectedIds.value })
      })
      
      let errorMessage = 'Error processing batch'
      
      if (!response.ok) {
        try {
          const errorData = await response.json()
          errorMessage = errorData.message || errorData.error || `Error ${response.status}`
        } catch (e) {
          errorMessage = `Error ${response.status}: ${response.statusText}`
        }
        throw new Error(errorMessage)
      }
      
      const result = await response.json()
      
      // Clear selection
      selectedIds.value = []
      
      // Mostrar resultado detallado
      const stats = result.results
      if (stats) {
        const msg = `Procesados: ${stats.processed}, Éxitos: ${stats.success}, Fallos: ${stats.failed}`
        showToast('success', 'Procesamiento Completado', msg, 5000)
        
        // Si hay errores de imágenes, mostrar advertencia
        if (stats.failed > 0 && stats.errors?.some((e: string) => e.includes('Sin imagen'))) {
          showToast('warning', 'Productos Sin Imágenes', 'Algunos productos no tienen URLs de imagen configuradas', 8000)
        }
      } else {
        showToast('success', 'Procesamiento Completado', result.message)
      }
      
      // Refrescar datos
      await Promise.all([loadStats(), loadProducts()])
      
      return true
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Unknown error'
      error.value = message
      showToast('error', 'Error', message)
      console.error('Error processing batch:', err)
      return false
    } finally {
      processing.value = false
    }
  }

  const processAll = async (): Promise<boolean> => {
    try {
      processing.value = true
      error.value = null
      
      const response = await fetch('/admin/vision/embeddings/process-all', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      
      let errorMessage = 'Error al iniciar procesamiento'
      
      if (!response.ok) {
        try {
          const errorData = await response.json()
          errorMessage = errorData.message || errorData.error || `Error ${response.status}: ${response.statusText}`
        } catch (e) {
          errorMessage = `Error ${response.status}: ${response.statusText}`
        }
        throw new Error(errorMessage)
      }
      
      const result = await response.json()
      
      // Mostrar resultado detallado
      const stats = result.results
      if (stats) {
        const msg = `Procesados: ${stats.processed}, Éxitos: ${stats.success}, Fallos: ${stats.failed}`
        showToast('success', 'Procesamiento Completado', msg, 5000)
        
        // Si hay errores de imágenes, mostrar advertencia
        if (stats.failed > 0 && stats.errors?.some((e: string) => e.includes('Sin imagen'))) {
          showToast('warning', 'Sin Imágenes', 'Algunos productos no tienen URLs de imagen. Configúralas primero.', 10000)
        }
      } else {
        showToast('success', 'Procesamiento Completado', result.message)
      }
      
      // Refrescar datos inmediatamente (procesamiento sincrónico)
      await Promise.all([loadStats(), loadProducts()])
      
      return true
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Error desconocido'
      error.value = message
      showToast('error', 'Error de Procesamiento', message, 8000)
      console.error('Error processing all:', err)
      return false
    } finally {
      processing.value = false
    }
  }

  const processMissingImages = async (): Promise<ProcessingResult | null> => {
    try {
      loading.value = true
      error.value = null
      
      const response = await fetch('/admin/vision/embeddings/process-missing-images', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json'
        }
      })
      
      if (!response.ok) throw new Error('Error processing missing images')
      
      const result = await response.json()
      
      // Refresh data
      await Promise.all([loadStats(), loadProducts()])
      
      return result
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Unknown error'
      console.error('Error processing missing images:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const clearProduct = async (productId: number): Promise<boolean> => {
    try {
      loading.value = true
      error.value = null
      
      const response = await fetch(`/admin/vision/embeddings/products/${productId}`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json'
        }
      })
      
      if (!response.ok) throw new Error('Error clearing product')
      
      const result = await response.json()
      
      // Refresh data
      await Promise.all([loadStats(), loadProducts()])
      
      return true
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Unknown error'
      console.error('Error clearing product:', err)
      return false
    } finally {
      loading.value = false
    }
  }

  const toggleSelection = (productId: number) => {
    const index = selectedIds.value.indexOf(productId)
    if (index > -1) {
      selectedIds.value.splice(index, 1)
    } else {
      selectedIds.value.push(productId)
    }
  }

  const selectAllVisible = () => {
    selectedIds.value = products.value.map(p => p.id)
  }

  const clearSelection = () => {
    selectedIds.value = []
  }

  const startPolling = () => {
    const poll = setInterval(async () => {
      await loadStats()
      
      // Stop polling when no longer processing
      if (!processing.value) {
        clearInterval(poll)
      }
    }, 5000)
    
    // Auto-stop after 10 minutes
    setTimeout(() => clearInterval(poll), 600000)
  }

  const refresh = async () => {
    await Promise.all([loadStats(), loadProducts()])
  }

  const goToPage = (page: number) => {
    currentPage.value = page
    loadProducts()
  }

  const applyFilters = () => {
    currentPage.value = 1
    loadProducts()
  }

  const clearError = () => {
    error.value = null
  }

  // Return reactive state and methods
  return {
    // State
    stats,
    products,
    loading,
    processing,
    selectedIds,
    search,
    statusFilter,
    currentPage,
    error,
    pagination,
    toast,
    
    // Computed
    selectedCount,
    hasSelection,
    allSelected,
    someSelected,
    completionColor,
    
    // Methods
    loadStats,
    loadProducts,
    processProduct,
    processSelected,
    processAll,
    processMissingImages,
    clearProduct,
    toggleSelection,
    selectAllVisible,
    clearSelection,
    refresh,
    goToPage,
    applyFilters,
    clearError,
    showToast,
    hideToast
  }
}
