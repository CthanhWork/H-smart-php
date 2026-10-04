export type ProductImage = { id: number; url: string; sort_order: number }
export type Product = {
  id: number; seller_id: number; title: string; description: string; price: number
  condition: string; status: string; images: ProductImage[]; created_at: string; updated_at: string
}
export type ProductPage = { content: Product[]; pageNo: number; pageSize: number; totalElements: number; totalPages: number; last: boolean }
export type ProductResult<T> = { status: string; message: string; data: T; errors?: Record<string, string[]> }

export const statusLabel: Record<string, string> = {
  draft: 'Bản nháp', pending_review: 'Chờ duyệt', active: 'Đang bán',
  reserved: 'Đã giữ chỗ', sold: 'Đã bán', hidden: 'Đã ẩn',
}
export const conditionLabel: Record<string, string> = {
  new: 'Mới', like_new: 'Như mới', good: 'Còn tốt', fair: 'Đã qua sử dụng',
}

export async function productRequest<T>(path: string, token: string, options: RequestInit = {}): Promise<ProductResult<T>> {
  let response: Response
  try {
    response = await fetch(`/api/v1/${path}`, {
      ...options,
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}`, ...options.headers },
    })
  } catch { throw new Error('Không thể kết nối máy chủ. Hãy thử lại sau.') }
  const result = await response.json() as ProductResult<T>
  if (!response.ok) {
    const detail = result.errors && Object.values(result.errors)[0]?.[0]
    throw new Error(detail || result.message || 'Yêu cầu không thành công.')
  }
  return result
}
