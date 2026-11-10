import { useEffect, useState } from 'react'
import { ArrowLeft, CalendarDays, PackageCheck, ShieldCheck, Star } from 'lucide-react'
import ProductCard from './components/ProductCard'
import { productRequest, type Product, type ProductPage } from './productApi'
import { Button } from './components/ui/button'

type SellerProfile = {
  id: number
  name: string
  joined_at: string | null
  completed_sales_count: number
  review_average: number | null
  review_count: number
  active_products_count: number
}

type Props = { sellerId: number; accessToken: string; navigate: (path: string) => void }

export default function SellerProfilePage({ sellerId, accessToken, navigate }: Props) {
  const [result, setResult] = useState<{ sellerId: number; seller: SellerProfile; products: Product[] } | null>(null)
  const [failure, setFailure] = useState<{ sellerId: number; message: string } | null>(null)

  useEffect(() => {
    let cancelled = false
    const params = new URLSearchParams({ 'filter[seller_id]': String(sellerId), 'page[number]': '0', 'page[size]': '12' })
    Promise.all([
      productRequest<SellerProfile>(`sellers/${sellerId}`, accessToken),
      productRequest<ProductPage>(`products?${params.toString()}`, accessToken),
    ]).then(([profileResult, productResult]) => {
      if (cancelled) return
      setResult({ sellerId, seller: profileResult.data, products: productResult.data.content })
      setFailure(null)
    }).catch((cause: Error & { status?: number }) => {
      if (!cancelled) setFailure({ sellerId, message: cause.status === 404 ? 'Không tìm thấy hồ sơ người bán.' : cause.message })
    })

    return () => { cancelled = true }
  }, [accessToken, sellerId])

  const currentResult = result?.sellerId === sellerId ? result : null
  const currentFailure = failure?.sellerId === sellerId ? failure.message : ''
  const seller = currentResult?.seller
  const products = currentResult?.products ?? []

  return <main className="font-vietnamese min-h-[65vh] bg-[#f5f5f5] px-4 py-8 sm:py-12">
    <div className="mx-auto max-w-frame">
      <Button variant="ghost" className="mb-5 px-0 text-black/60 hover:text-black" onClick={() => navigate(`/products?seller_id=${sellerId}`)}><ArrowLeft size={16} className="mr-2" />Quay lại sản phẩm</Button>
      {currentFailure ? <section role="alert" className="rounded-2xl border border-red-200 bg-white p-8 text-center text-sm text-red-700">{currentFailure}</section> : !seller ? <p role="status" className="rounded-2xl border border-black/10 bg-white p-8 text-center text-sm text-black/55">Đang tải hồ sơ người bán…</p> : <>
        <section className="rounded-[24px] border border-black/10 bg-white p-6 shadow-sm sm:p-8">
          <div className="flex flex-wrap items-start justify-between gap-5">
            <div className="flex items-center gap-4">
              <span className="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-[#fff1eb] text-[#e65332]"><ShieldCheck size={25} /></span>
              <div><p className="text-xs font-semibold uppercase tracking-[0.14em] text-black/45">Hồ sơ người bán</p><h1 className="mt-1 text-2xl font-bold sm:text-3xl">{seller.name}</h1><p className="mt-2 flex items-center gap-1.5 text-sm text-black/55"><CalendarDays size={15} />Tham gia {seller.joined_at ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'medium' }).format(new Date(seller.joined_at)) : 'H-Smart'}</p></div>
            </div>
            <div className="grid min-w-[220px] grid-cols-2 gap-3 text-center">
              <div className="rounded-xl bg-[#f7f7f7] px-4 py-3"><p className="text-xl font-bold">{seller.completed_sales_count}</p><p className="mt-1 text-xs text-black/55">Giao dịch hoàn tất</p></div>
              <div className="rounded-xl bg-[#f7f7f7] px-4 py-3"><p className="flex items-center justify-center gap-1 text-xl font-bold">{seller.review_average == null ? '—' : <>{seller.review_average.toFixed(1)} <Star size={16} className="fill-[#e65332] text-[#e65332]" /></>}</p><p className="mt-1 text-xs text-black/55">{seller.review_count} đánh giá</p></div>
            </div>
          </div>
        </section>

        <section className="mt-7" aria-labelledby="seller-products-title">
          <div className="mb-4 flex flex-wrap items-end justify-between gap-2"><div><h2 id="seller-products-title" className="mt-1 text-xl font-bold">Sản phẩm của {seller.name}</h2></div><div className="flex items-center gap-3"><span className="text-sm text-black/55">{seller.active_products_count} sản phẩm</span>{seller.active_products_count > products.length && <Button variant="outline" size="sm" onClick={() => navigate(`/products?seller_id=${seller.id}`)}>Xem tất cả</Button>}</div></div>
          {products.length ? <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">{products.map((product, index) => <ProductCard key={product.id} product={product} priority={index < 4} onClick={() => navigate(`/products/${product.id}`)} />)}</div> : <div className="rounded-2xl border border-dashed border-black/20 bg-white p-10 text-center"><PackageCheck className="mx-auto mb-3 text-black/40" size={28} /><p className="text-sm text-black/60">Người bán hiện chưa có sản phẩm nào đang rao.</p></div>}
        </section>
      </>}
    </div>
  </main>
}
