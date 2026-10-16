import { cn } from "../lib/utils"
import { Star } from "lucide-react"
import type { Product } from "../productApi"
import { conditionLabel, statusLabel } from "../productApi"

type ProductCardProps = {
  product: Product
  onClick?: () => void
}

const formatVnd = (amount: number) =>
  new Intl.NumberFormat("vi-VN").format(amount) + " đ"

const ProductCard = ({ product, onClick }: ProductCardProps) => {
  const cover = product.images?.[0]?.url
  const rating = product.rating_average ?? 0
  const reviewCount = product.review_count ?? 0

  return (
    <button
      onClick={onClick}
      className="group text-left flex flex-col gap-3 rounded-2xl transition-transform hover:-translate-y-1"
    >
      <div className="relative bg-[#F0EEED] rounded-2xl aspect-square overflow-hidden">
        {cover ? (
          <img
            src={cover}
            alt={product.title}
            className="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform"
            loading="lazy"
          />
        ) : (
          <div className="absolute inset-0 flex items-center justify-center text-black/30 text-sm">
            Không có ảnh
          </div>
        )}
        {product.status && product.status !== "active" && (
          <span
            className={cn(
              "absolute top-3 left-3 text-xs font-medium px-2.5 py-1 rounded-full",
              product.status === "sold"
                ? "bg-black/70 text-white"
                : product.status === "pending_review"
                ? "bg-amber-100 text-amber-900"
                : product.status === "hidden"
                ? "bg-[#FF3333] text-white"
                : "bg-white text-black"
            )}
          >
            {statusLabel[product.status] ?? product.status}
          </span>
        )}
      </div>

      <div className="px-1">
        <h3 className="font-bold text-sm sm:text-base text-black truncate">
          {product.title}
        </h3>

        <div className="flex items-center gap-1 mt-1">
          <Star size={14} className="fill-amber-400 text-amber-400" />
          <span className="text-sm font-medium">{rating.toFixed(1)}</span>
          <span className="text-xs text-black/60">({reviewCount})</span>
        </div>

        <div className="flex items-center justify-between mt-2">
          <p className="font-bold text-lg sm:text-xl text-black">
            {formatVnd(product.price)}
            {product.negotiable && (
              <span className="ml-1 text-xs font-normal text-black/60">trở lên</span>
            )}
          </p>
        </div>

        <div className="flex items-center gap-2 mt-1">
          {product.category && (
            <span className="text-xs text-black/60 bg-muted rounded-full px-2 py-0.5">
              {product.category.name}
            </span>
          )}
          {product.condition && (
            <span className="text-xs text-black/60">
              {conditionLabel[product.condition] ?? product.condition}
            </span>
          )}
        </div>
      </div>
    </button>
  )
}

export default ProductCard
