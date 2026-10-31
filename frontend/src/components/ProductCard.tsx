import { Heart, Star } from 'lucide-react'
import type { Product } from '../productApi'
import { conditionLabel } from '../productApi'

type ProductCardProps = {
  product: Product
  onClick?: () => void
  onToggleFavorite?: () => void
  favoriteBusy?: boolean
  compact?: boolean
}
const money = (amount: number) => new Intl.NumberFormat('vi-VN').format(amount)

export default function ProductCard({ product, onClick, onToggleFavorite, favoriteBusy = false, compact = false }: ProductCardProps) {
  const image = product.images?.[0]?.url
  const hasReviews = (product.review_count ?? 0) > 0 && product.rating_average != null
  const isFavorite = Boolean(product.is_favorite)

  return <article className={`group relative h-full min-w-0 overflow-hidden border border-[#ededed] bg-white text-[#333] transition duration-150 hover:-translate-y-0.5 hover:border-[#ee4d2d] hover:shadow-[0_8px_22px_-18px_rgba(238,77,45,.75)] ${compact ? 'text-xs' : ''}`}>
    <button type="button" onClick={onClick} className="block h-full w-full text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#ee4d2d]">
      <span className="relative block aspect-square overflow-hidden bg-[#f7f7f7]">
        {image ? <img src={image} alt={product.title} loading="lazy" className="h-full w-full object-cover object-center transition duration-200 group-hover:scale-[1.035]" /> : <span className="flex h-full items-center justify-center text-sm text-black/35">Không có ảnh</span>}
        {product.negotiable && <span className="absolute left-0 top-0 bg-[#ee4d2d] px-2 py-1 text-[10px] font-bold text-white">Có thể trả giá</span>}
      </span>
      <span className="grid content-start gap-1.5 p-2.5 sm:p-3">
        <span className="truncate text-[11px] font-semibold leading-tight text-[#888]">{product.category?.name || 'Chưa phân loại'}</span>
        <span className="line-clamp-2 min-h-9 text-[13px] font-medium leading-[1.4] text-[#222]">{product.title}</span>
        {hasReviews && <span className="flex items-center gap-1 text-xs text-[#777]"><Star size={12} className="fill-[#ee4d2d] text-[#ee4d2d]" /><span className="font-semibold text-[#ee4d2d]">{product.rating_average?.toFixed(1)}</span><span>({product.review_count})</span></span>}
        <span className="flex min-w-0 items-center justify-between gap-2 pt-0.5">
          <span className="truncate text-[15px] font-bold text-[#ee4d2d]">{money(product.price)} <span className="text-[11px]">₫</span></span>
          <span className="shrink-0 text-[10px] text-[#888]">{conditionLabel[product.condition] ?? 'Đã qua sử dụng'}</span>
        </span>
      </span>
    </button>
    {onToggleFavorite && <button type="button" onClick={(event) => { event.stopPropagation(); onToggleFavorite() }} disabled={favoriteBusy} aria-label={isFavorite ? 'Xóa khỏi yêu thích' : 'Thêm vào yêu thích'} aria-pressed={isFavorite} className="absolute right-2 top-2 grid h-9 w-9 place-items-center rounded-full bg-white/95 text-[#555] shadow-sm transition hover:text-[#e65332] disabled:opacity-50">
      <Heart size={18} className={isFavorite ? 'fill-[#e65332] text-[#e65332]' : ''} />
    </button>}
  </article>
}
