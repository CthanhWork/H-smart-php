import { useEffect, useMemo, useState } from 'react'
import { ArrowLeft, ArrowRight, BookOpen, Camera, ChevronLeft, ChevronRight, GraduationCap, Menu, Shirt, ShoppingBag, Sofa, Smartphone } from 'lucide-react'
import type { Category, Product, ProductPage } from './productApi'
import { productRequest } from './productApi'
import ProductCard from './components/ProductCard'

type HomePageProps = { isAuthenticated: boolean; accessToken: string; navigate: (path: string) => void }
type Slide = { title: string; subtitle: string; image: string; action: string }

const slides: Slide[] = [
  { title: 'Đồ tốt, giá vừa túi tiền', subtitle: 'Tìm sách, thiết bị học tập và đồ dùng đã qua sử dụng từ cộng đồng sinh viên.', image: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=1500&q=85', action: 'Khám phá sản phẩm' },
  { title: 'Góc học tập sẵn sàng', subtitle: 'Laptop, điện thoại, sách và phụ kiện cho một học kỳ mới.', image: 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1500&q=85', action: 'Tìm đồ học tập' },
  { title: 'Cho món đồ thêm vòng đời', subtitle: 'Đăng bán món đồ bạn không còn dùng và tìm người cần đến nó.', image: 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=1500&q=85', action: 'Đăng bán ngay' },
]

const sideBanners = [
  { image: 'https://images.unsplash.com/photo-1512820790803-83ca734da794?auto=format&fit=crop&w=700&q=80', label: 'Sách và giáo trình' },
  { image: 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=700&q=80', label: 'Thiết bị học tập' },
]

const categoryIcons: Record<string, typeof ShoppingBag> = {
  'dien-tu': Smartphone,
  'sach-va-giao-trinh': BookOpen,
  'do-dung-hoc-tap': GraduationCap,
  'thoi-trang': Shirt,
  'do-gia-dung': Sofa,
  khac: Camera,
}

const pageSize = 10

export default function HomePage({ isAuthenticated, accessToken, navigate }: HomePageProps) {
  const [categories, setCategories] = useState<Category[]>([])
  const [products, setProducts] = useState<Product[]>([])
  const [slide, setSlide] = useState(0)
  const [categoryId, setCategoryId] = useState('')
  const [sort, setSort] = useState('latest')
  const [page, setPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [favoriteBusyIds, setFavoriteBusyIds] = useState<Set<number>>(() => new Set())

  useEffect(() => {
    const timer = window.setInterval(() => setSlide((current) => (current + 1) % slides.length), 6000)
    return () => window.clearInterval(timer)
  }, [])

  useEffect(() => {
    let cancelled = false
    Promise.all([
      productRequest<Category[]>('categories', ''),
      productRequest<ProductPage>('products?page[number]=0&page[size]=100', accessToken),
    ]).then(([categoryResult, productResult]) => {
      if (cancelled) return
      setCategories(categoryResult.data)
      setProducts(productResult.data.content)
    }).catch((cause: Error) => {
      if (!cancelled) setError(cause.message || 'Không tải được dữ liệu cửa hàng.')
    }).finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [accessToken])

  async function toggleFavorite(product: Product) {
    if (!accessToken || favoriteBusyIds.has(product.id)) return
    setFavoriteBusyIds((current) => new Set(current).add(product.id))
    setError('')
    try {
      if (product.is_favorite) await productRequest<null>(`me/favorites/${product.id}`, accessToken, { method: 'DELETE' })
      else await productRequest<null>('me/favorites', accessToken, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ product_id: product.id }) })
      setProducts((current) => current.map((item) => item.id === product.id ? { ...item, is_favorite: !product.is_favorite } : item))
    } catch (cause) { setError((cause as Error).message) }
    finally { setFavoriteBusyIds((current) => { const next = new Set(current); next.delete(product.id); return next }) }
  }

  const visibleProducts = useMemo(() => {
    const filtered = categoryId ? products.filter((product) => String(product.category_id) === categoryId) : [...products]
    if (sort === 'price-asc') filtered.sort((a, b) => a.price - b.price)
    if (sort === 'price-desc') filtered.sort((a, b) => b.price - a.price)
    return filtered
  }, [products, categoryId, sort])

  const totalPages = Math.max(1, Math.ceil(visibleProducts.length / pageSize))
  const currentPage = Math.min(page, totalPages)
  const pageProducts = visibleProducts.slice((currentPage - 1) * pageSize, currentPage * pageSize)
  const moveSlide = (next: number) => setSlide((next + slides.length) % slides.length)
  const selectCategory = (id: string) => { setCategoryId(id); setPage(1) }
  const openCategory = (id: number) => navigate(`/products?category=${id}`)

  return <main className="font-vietnamese min-h-screen bg-[#f5f5f5] pb-12 text-[#222]">
    <div className="mx-auto w-[min(calc(100%-24px),1200px)] pt-4 sm:w-[min(calc(100%-40px),1200px)] sm:pt-5">
      <section className="grid gap-1 md:grid-cols-[minmax(0,2fr)_minmax(220px,1fr)]" aria-label="Gợi ý mua sắm">
        <div className="relative min-h-[210px] overflow-hidden bg-[#50433b] sm:min-h-[270px]">
          {slides.map((item, index) => <button key={item.title} type="button" aria-label={item.action} onClick={() => navigate(index === 2 && isAuthenticated ? '/sell' : '/products')} className={`absolute inset-0 h-full w-full text-left transition-opacity duration-500 ${index === slide ? 'z-[1] opacity-100' : 'pointer-events-none opacity-0'}`} style={{ backgroundImage: `linear-gradient(90deg, rgba(0,0,0,.72) 0%, rgba(0,0,0,.32) 55%, rgba(0,0,0,.05) 100%), url("${item.image}")`, backgroundPosition: 'center', backgroundSize: 'cover' }}>
            <span className="absolute bottom-8 left-5 max-w-[470px] text-white sm:bottom-10 sm:left-9"><span className="inline-flex bg-[#ee4d2d] px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide">H-Smart · Chợ sinh viên</span><span className="mt-3 block text-2xl font-extrabold leading-tight sm:text-4xl">{item.title}</span><span className="mt-2 block max-w-md text-sm leading-6 text-white/90 sm:text-base">{item.subtitle}</span><span className="mt-4 inline-flex items-center gap-2 text-sm font-bold">{item.action}<ArrowRight size={16} /></span></span>
          </button>)}
          <button type="button" aria-label="Banner trước" onClick={() => moveSlide(slide - 1)} className="absolute left-0 top-1/2 z-[2] grid h-10 w-8 -translate-y-1/2 place-items-center bg-white/80 text-[#444] hover:bg-white"><ChevronLeft size={20} /></button>
          <button type="button" aria-label="Banner tiếp theo" onClick={() => moveSlide(slide + 1)} className="absolute right-0 top-1/2 z-[2] grid h-10 w-8 -translate-y-1/2 place-items-center bg-white/80 text-[#444] hover:bg-white"><ChevronRight size={20} /></button>
          <div className="absolute bottom-3 right-4 z-[2] flex gap-1.5">{slides.map((item, index) => <button key={item.title} type="button" aria-label={`Chọn banner ${index + 1}`} onClick={() => moveSlide(index)} className={`h-2 rounded-full transition-all ${index === slide ? 'w-5 bg-white' : 'w-2 bg-white/60'}`} />)}</div>
        </div>
        <div className="grid grid-cols-2 gap-1 md:grid-cols-1 md:grid-rows-2">
          {sideBanners.map((banner, index) => <button key={banner.label} type="button" onClick={() => { const category = index === 0 ? categories.find((item) => item.slug === 'sach-va-giao-trinh') : categories.find((item) => item.slug === 'do-dung-hoc-tap'); category ? openCategory(category.id) : navigate('/products') }} className="group relative min-h-[100px] overflow-hidden bg-[#d9d2ca] text-left sm:min-h-[130px] md:min-h-0">
            <img src={banner.image} alt="" className="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105" />
            <span className="absolute inset-0 bg-gradient-to-r from-black/60 to-transparent" /><span className="absolute bottom-3 left-3 max-w-[80%] text-sm font-bold text-white sm:bottom-4 sm:left-4 sm:text-lg">{banner.label}</span>
          </button>)}
        </div>
      </section>

      <section className="mt-3 bg-white px-2 py-3 shadow-[0_1px_2px_rgba(0,0,0,.06)] sm:px-4" aria-label="Danh mục nổi bật">
        <div className="grid grid-cols-4 gap-y-2 sm:grid-cols-6 lg:grid-cols-8">
          {categories.slice(0, 8).map((category) => {
            const Icon = categoryIcons[category.slug] ?? ShoppingBag
            return <button key={category.id} type="button" onClick={() => openCategory(category.id)} className="group flex min-w-0 flex-col items-center gap-1.5 px-1 py-2 text-center">
              <span className="grid h-12 w-12 place-items-center rounded-full bg-[#fff2ed] text-[#e65332] transition group-hover:-translate-y-0.5 group-hover:bg-[#ffe0d5] sm:h-14 sm:w-14"><Icon size={23} strokeWidth={1.7} /></span>
              <span className="line-clamp-2 min-h-8 text-[10px] leading-4 text-[#444] group-hover:text-[#e65332] sm:text-xs">{category.name}</span>
            </button>
          })}
          {categories.length === 0 && !loading && <p className="col-span-full py-5 text-center text-sm text-black/50">Chưa có danh mục sản phẩm.</p>}
        </div>
      </section>

      <section className="mt-3 flex flex-wrap items-center justify-between gap-3 bg-gradient-to-r from-[#ff6a35] to-[#f23d29] px-5 py-4 text-white sm:px-8">
        <div><p className="text-[11px] font-bold uppercase tracking-[0.16em] text-white/75">H-Smart dành cho sinh viên</p><h2 className="mt-1 text-lg font-extrabold sm:text-2xl">Món đồ phù hợp, thêm một vòng đời mới</h2></div>
        <button type="button" onClick={() => navigate(isAuthenticated ? '/sell' : '/register')} className="inline-flex items-center gap-2 bg-white px-4 py-2.5 text-sm font-bold text-[#df482c] transition hover:bg-[#fff3ed]">{isAuthenticated ? 'Đăng bán sản phẩm' : 'Tham gia ngay'}<ArrowRight size={16} /></button>
      </section>

      <section className="mt-3 grid items-start gap-3 lg:grid-cols-[190px_minmax(0,1fr)]">
        <aside className="overflow-hidden bg-white shadow-[0_1px_2px_rgba(0,0,0,.06)] lg:sticky lg:top-[150px]">
          <h2 className="flex items-center gap-2 border-b border-[#f0f0f0] px-4 py-3 text-sm font-bold"><Menu size={16} />Danh mục</h2>
          <div className="flex gap-1 overflow-x-auto p-2 lg:block lg:max-h-[calc(100vh-180px)] lg:overflow-y-auto">
            <button type="button" onClick={() => selectCategory('')} className={`shrink-0 px-3 py-2 text-left text-[13px] transition lg:block lg:w-full ${!categoryId ? 'bg-[#fff2ed] font-semibold text-[#ee4d2d] lg:border-l-2 lg:border-[#ee4d2d]' : 'text-[#555] hover:text-[#ee4d2d]'}`}>Tất cả sản phẩm</button>
            {categories.map((category) => <button key={category.id} type="button" onClick={() => selectCategory(String(category.id))} className={`shrink-0 px-3 py-2 text-left text-[13px] transition lg:block lg:w-full ${String(categoryId) === String(category.id) ? 'bg-[#fff2ed] font-semibold text-[#ee4d2d] lg:border-l-2 lg:border-[#ee4d2d]' : 'text-[#555] hover:text-[#ee4d2d]'}`}>{category.name}</button>)}
          </div>
        </aside>

        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2 bg-[#ededed] p-2.5 sm:gap-3 sm:px-4">
            <span className="mr-1 text-xs text-[#666] sm:text-sm">Sắp xếp theo</span>
            <button type="button" onClick={() => { setSort('latest'); setPage(1) }} className={`h-8 bg-white px-3 text-xs sm:px-4 sm:text-sm ${sort === 'latest' ? 'bg-[#ee4d2d] font-semibold text-white' : 'text-[#333] hover:text-[#ee4d2d]'}`}>Mới nhất</button>
            <label className="relative"><span className="sr-only">Sắp xếp theo giá</span><select value={sort === 'latest' ? '' : sort} onChange={(event) => { setSort(event.target.value || 'latest'); setPage(1) }} className="h-8 min-w-[132px] appearance-none bg-white px-3 pr-8 text-xs text-[#333] outline-none sm:text-sm"><option value="">Giá</option><option value="price-asc">Giá: thấp đến cao</option><option value="price-desc">Giá: cao đến thấp</option></select><ChevronRight size={14} className="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 rotate-90 text-[#888]" /></label>
            <div className="ml-auto flex items-center gap-2 text-xs text-[#555] sm:text-sm"><span><b className="text-[#ee4d2d]">{currentPage}</b> / {totalPages}</span><button type="button" disabled={currentPage <= 1} onClick={() => setPage((value) => Math.max(1, value - 1))} aria-label="Trang trước" className="grid h-8 w-8 place-items-center border border-[#e5e5e5] bg-white disabled:text-[#ccc]"><ArrowLeft size={15} /></button><button type="button" disabled={currentPage >= totalPages} onClick={() => setPage((value) => Math.min(totalPages, value + 1))} aria-label="Trang sau" className="grid h-8 w-8 place-items-center border border-[#e5e5e5] bg-white disabled:text-[#ccc]"><ArrowRight size={15} /></button></div>
          </div>

          {error && <div role="alert" className="mt-2 border border-red-200 bg-red-50 p-3 text-sm text-red-800">{error}</div>}
          {loading ? <div className="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">{Array.from({ length: 10 }, (_, index) => <div key={index} className="aspect-[.78] animate-pulse bg-white" />)}</div>
            : pageProducts.length === 0 ? <div className="mt-2 grid min-h-[230px] place-content-center justify-items-center gap-3 bg-white px-5 text-center"><ShoppingBag size={34} className="text-[#ee4d2d]" /><p className="text-sm text-[#666]">Chưa có sản phẩm trong danh mục này.</p><button type="button" onClick={() => selectCategory('')} className="text-sm font-semibold text-[#ee4d2d]">Xem tất cả sản phẩm</button></div>
              : <div className="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">{pageProducts.map((product) => <ProductCard key={product.id} product={product} onClick={() => navigate(`/products/${product.id}`)} onToggleFavorite={isAuthenticated ? () => toggleFavorite(product) : undefined} favoriteBusy={favoriteBusyIds.has(product.id)} />)}</div>}
          {totalPages > 1 && <div className="mt-5 flex items-center justify-center gap-2"><button type="button" disabled={currentPage <= 1} onClick={() => setPage((value) => value - 1)} className="h-9 border border-black/10 bg-white px-4 text-sm disabled:opacity-40">Trước</button><span className="px-2 text-sm text-[#666]">Trang {currentPage} / {totalPages}</span><button type="button" disabled={currentPage >= totalPages} onClick={() => setPage((value) => value + 1)} className="h-9 border border-black/10 bg-white px-4 text-sm disabled:opacity-40">Sau</button></div>}
        </div>
      </section>
    </div>
  </main>
}
