import { useEffect, useState } from 'react'
import { ArrowRight, BookOpen, Camera, ChevronLeft, ChevronRight, GraduationCap, Shirt, ShoppingBag, Sofa, Smartphone } from 'lucide-react'
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

export default function HomePage({ isAuthenticated, accessToken, navigate }: HomePageProps) {
  const [categories, setCategories] = useState<Category[]>([])
  const [products, setProducts] = useState<Product[]>([])
  const [slide, setSlide] = useState(0)
  const [totalProducts, setTotalProducts] = useState(0)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [favoriteBusyIds, setFavoriteBusyIds] = useState<Set<number>>(() => new Set())

  useEffect(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
    const timer = window.setInterval(() => setSlide((current) => (current + 1) % slides.length), 6000)
    return () => window.clearInterval(timer)
  }, [])

  useEffect(() => {
    let cancelled = false
    Promise.all([
      productRequest<Category[]>('categories', ''),
      productRequest<ProductPage>('products?page[number]=0&page[size]=10&sort=newest', accessToken),
    ]).then(([categoryResult, productResult]) => {
      if (cancelled) return
      setCategories(categoryResult.data)
      setProducts(productResult.data.content)
      setTotalProducts(productResult.data.totalElements)
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

  const moveSlide = (next: number) => setSlide((next + slides.length) % slides.length)
  const openCategory = (id: number) => navigate(`/products?category=${id}`)

  return <main className="font-vietnamese min-h-screen bg-[#f5f5f5] pb-12 text-[#222]">
    <div className="mx-auto w-[min(calc(100%-24px),1200px)] pt-4 sm:w-[min(calc(100%-40px),1200px)] sm:pt-5">
      <section className="grid gap-1 md:grid-cols-[minmax(0,2fr)_minmax(220px,1fr)]" aria-label="Gợi ý mua sắm">
        <div className="relative min-h-[190px] overflow-hidden rounded-2xl bg-[#50433b] sm:min-h-[270px]">
          {slides.map((item, index) => <button key={item.title} type="button" aria-label={item.action} onClick={() => navigate(index === 2 ? isAuthenticated ? '/sell' : '/register' : '/products')} className={`absolute inset-0 h-full w-full text-left transition-opacity duration-500 motion-reduce:transition-none ${index === slide ? 'z-[1] opacity-100' : 'pointer-events-none opacity-0'}`} style={{ backgroundImage: `linear-gradient(90deg, rgba(0,0,0,.72) 0%, rgba(0,0,0,.32) 55%, rgba(0,0,0,.05) 100%), url("${item.image}")`, backgroundPosition: 'center', backgroundSize: 'cover' }}>
            <span className="absolute bottom-8 left-5 max-w-[470px] text-white sm:bottom-10 sm:left-9"><span className="inline-flex bg-[#ee4d2d] px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide">H-Smart · Chợ sinh viên</span><span className="mt-3 block text-2xl font-extrabold leading-tight sm:text-4xl">{item.title}</span><span className="mt-2 block max-w-md text-sm leading-6 text-white/90 sm:text-base">{item.subtitle}</span><span className="mt-4 inline-flex items-center gap-2 text-sm font-bold">{item.action}<ArrowRight size={16} /></span></span>
          </button>)}
          <button type="button" aria-label="Banner trước" onClick={() => moveSlide(slide - 1)} className="absolute left-0 top-1/2 z-[2] grid h-10 w-8 -translate-y-1/2 place-items-center bg-white/80 text-[#444] hover:bg-white"><ChevronLeft size={20} /></button>
          <button type="button" aria-label="Banner tiếp theo" onClick={() => moveSlide(slide + 1)} className="absolute right-0 top-1/2 z-[2] grid h-10 w-8 -translate-y-1/2 place-items-center bg-white/80 text-[#444] hover:bg-white"><ChevronRight size={20} /></button>
          <div className="absolute bottom-3 right-4 z-[2] flex gap-1.5">{slides.map((item, index) => <button key={item.title} type="button" aria-label={`Chọn banner ${index + 1}`} onClick={() => moveSlide(index)} className={`h-2 rounded-full transition-all ${index === slide ? 'w-5 bg-white' : 'w-2 bg-white/60'}`} />)}</div>
        </div>
        <div className="hidden grid-cols-2 gap-1 md:grid md:grid-cols-1 md:grid-rows-2">
          {sideBanners.map((banner, index) => <button key={banner.label} type="button" onClick={() => { const category = index === 0 ? categories.find((item) => item.slug === 'sach-va-giao-trinh') : categories.find((item) => item.slug === 'do-dung-hoc-tap'); if (category) openCategory(category.id); else navigate('/products') }} className="group relative min-h-[100px] overflow-hidden bg-[#d9d2ca] text-left sm:min-h-[130px] md:min-h-0">
            <img src={banner.image} alt="" className="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105" />
            <span className="absolute inset-0 bg-gradient-to-r from-black/60 to-transparent" /><span className="absolute bottom-3 left-3 max-w-[80%] text-sm font-bold text-white sm:bottom-4 sm:left-4 sm:text-lg">{banner.label}</span>
          </button>)}
        </div>
      </section>

      <section id="categories" className="mt-3 scroll-mt-40 rounded-2xl bg-white px-2 py-3 shadow-sm sm:px-4" aria-label="Danh mục nổi bật">
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

      <section className="mt-3 hidden flex-wrap items-center justify-between gap-3 rounded-2xl bg-gradient-to-r from-[#ff6a35] to-[#f23d29] px-5 py-4 text-white sm:flex sm:px-8">
        <div><p className="text-[11px] font-bold uppercase tracking-[0.16em] text-white/75">H-Smart dành cho sinh viên</p><h2 className="mt-1 text-lg font-extrabold sm:text-2xl">Món đồ phù hợp, thêm một vòng đời mới</h2></div>
        <button type="button" onClick={() => navigate(isAuthenticated ? '/sell' : '/register')} className="inline-flex items-center gap-2 bg-white px-4 py-2.5 text-sm font-bold text-[#df482c] transition hover:bg-[#fff3ed]">{isAuthenticated ? 'Đăng bán sản phẩm' : 'Tham gia ngay'}<ArrowRight size={16} /></button>
      </section>

      <section className="mt-3">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center justify-between gap-2 rounded-t-2xl bg-white px-4 py-3"><div><h2 className="text-sm font-bold sm:text-base">Mới đăng</h2><p className="mt-0.5 text-xs text-[#666]">{totalProducts} sản phẩm đang được rao bán</p></div><button type="button" onClick={() => navigate('/products')} className="inline-flex items-center gap-1.5 text-sm font-semibold text-[#d94322] hover:underline">Xem tất cả <ArrowRight size={16} /></button></div>

          {error && <div role="alert" className="mt-2 border border-red-200 bg-red-50 p-3 text-sm text-red-800">{error}</div>}
          {loading ? <div className="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">{Array.from({ length: 10 }, (_, index) => <div key={index} className="aspect-[.78] animate-pulse bg-white" />)}</div>
            : products.length === 0 ? <div className="mt-2 grid min-h-[230px] place-content-center justify-items-center gap-3 bg-white px-5 text-center"><ShoppingBag size={34} className="text-[#ee4d2d]" /><p className="text-sm text-[#666]">Chưa có sản phẩm đang bán.</p><button type="button" onClick={() => navigate('/products')} className="text-sm font-semibold text-[#ee4d2d]">Khám phá sản phẩm</button></div>
              : <div className="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">{products.map((product, index) => <ProductCard key={product.id} product={product} priority={index < 5} onClick={() => navigate(`/products/${product.id}`)} onToggleFavorite={isAuthenticated ? () => toggleFavorite(product) : undefined} favoriteBusy={favoriteBusyIds.has(product.id)} />)}</div>}
        </div>
      </section>
    </div>
  </main>
}
