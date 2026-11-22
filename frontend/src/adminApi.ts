export type User = {
  id: number
  username: string
  email: string
  full_name: string | null
  role: 'member' | 'admin'
  status: 'active' | 'suspended' | 'pending_verification'
  created_at: string
}

export type Product = {
  id: number
  seller_id: number
  title: string
  description: string
  price: number
  condition: string
  status: string
  created_at: string
  updated_at: string
  category?: { id: number; name: string; slug?: string } | null
  images?: Array<{ id: number; url: string; sort_order: number }>
}

export type PaginatedUsers = {
  data: User[]
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export type PaginatedProducts = {
  data: Product[]
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export type ApiResult<T> = {
  success: boolean
  status: number
  message: string
  data: T
}

export type Category = { id: number; name: string; slug: string; products_count: number }
export type AdminStats = { users: number; products: number; orders: number; hidden_products: number; pending_reports: number }
export type AdminReport = { id: number; target_type: 'product' | 'user'; target_id: number; reason: string; details: string | null; status: string; created_at: string; reporter: User; product: (Product & { seller_id: number }) | null; reported_user: User | null }
export type AdminOrder = { id: number; status: string; total_amount: number; product_amount: number; shipping_fee: number; platform_fee: number; commission_rate: number; payment_method: string; payment_provider: string; payment_status: string; cod_deposit_percent?: number | null; cod_deposit_amount?: number; cod_remaining_amount?: number; cod_remaining_collected_at?: string | null; cod_remaining_collection_type?: string | null; cod_deposit_refunded_at?: string | null; cod_deposit_wallet_consent_at?: string | null; delivery_status: string | null; delivered_at?: string | null; release_eligible_at?: string | null; cod_collected_at: string | null; carrier_settled_at: string | null; seller_paid_at: string | null; tracking_number: string | null; shipping_carrier: string | null; shipping_address_snapshot?: Record<string, unknown> | null; created_at: string; paid_at: string | null; confirmed_at: string | null; shipped_at: string | null; completed_at: string | null; cancelled_at: string | null; buyer: User; seller: User; product: Product & { id: number; title: string }; status_history?: Array<{from_status:string|null;to_status:string;changed_at:string}> }
export type AdminAuditLog = { id: number; action: string; subject_type: string; subject_id: number | null; summary: string; details: Record<string, unknown> | null; created_at: string; admin: User | null }
export type AdminPageResult<T> = { data: T[]; current_page: number; last_page: number; total: number }
export type MarketplaceSettings = { commission_rate: number; shipping_fee: number; listing_free_monthly: number; listing_fee: number; boost_price: number; boost_days: number; vip_price: number; vip_days: number; promotion_slots: number; cod_deposit_percent: number }
export type PaymentReconciliation = { id: number; provider: string; payment_reference: string; bank_reference: string; amount: number; status: string; order_id: number | null; evidence_note: string; reconciled_at: string | null; created_at: string }

async function adminRequest<T>(
  path: string,
  token: string,
  options: RequestInit = {}
): Promise<ApiResult<T>> {
  const response = await fetch(`/api/v1/admin/${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token}`,
      ...options.headers,
    },
  })

  const result = await response.json() as ApiResult<T>

  if (!response.ok) {
    throw new Error(result.message || 'Yêu cầu không thành công')
  }

  return result
}

export async function getMarketplaceSettings(token: string): Promise<MarketplaceSettings> {
  return (await adminRequest<MarketplaceSettings>('marketplace-settings', token)).data
}

export async function updateMarketplaceSettings(token: string, values: MarketplaceSettings): Promise<MarketplaceSettings> {
  return (await adminRequest<MarketplaceSettings>('marketplace-settings', token, { method: 'PATCH', body: JSON.stringify(values) })).data
}

export async function getPaymentReconciliations(token: string): Promise<{ data: PaymentReconciliation[]; current_page: number; last_page: number }> {
  return (await adminRequest<{ data: PaymentReconciliation[]; current_page: number; last_page: number }>('payment-reconciliations', token)).data
}

export async function reconcileVietQr(token: string, values: { payment_reference: string; bank_reference: string; amount: number; evidence_note: string }): Promise<PaymentReconciliation> {
  return (await adminRequest<PaymentReconciliation>('payment-reconciliations', token, { method: 'POST', body: JSON.stringify({ ...values, provider: 'vietqr', verified_against_bank_statement: true }) })).data
}

export async function getPendingProducts(token: string, page = 1): Promise<PaginatedProducts> {
  const result = await adminRequest<PaginatedProducts>(`products/pending?page=${page}`, token)
  return result.data
}

export async function approveProduct(token: string, productId: number): Promise<void> {
  await adminRequest<{ product: Product }>(`products/${productId}/approve`, token, {
    method: 'POST',
  })
}

export async function hideProduct(token: string, productId: number, reason: string): Promise<void> {
  await adminRequest<{ product: Product }>(`products/${productId}/hide`, token, {
    method: 'POST',
    body: JSON.stringify({ reason }),
  })
}

export async function getUsers(token: string, page = 1, filters?: { status?: string; role?: string; q?: string }): Promise<PaginatedUsers> {
  const params = new URLSearchParams({ page: String(page) })
  if (filters?.status) params.append('filter[status]', filters.status)
  if (filters?.role) params.append('filter[role]', filters.role)
  if (filters?.q) params.append('filter[q]', filters.q)

  const result = await adminRequest<PaginatedUsers>(`users?${params}`, token)
  return result.data
}

export async function suspendUser(token: string, userId: number, reason: string): Promise<void> {
  await adminRequest<{ user: User }>(`users/${userId}/suspend`, token, {
    method: 'POST',
    body: JSON.stringify({ reason }),
  })
}

export async function restoreUser(token: string, userId: number): Promise<void> {
  await adminRequest<{ user: User }>(`users/${userId}/restore`, token, {
    method: 'POST',
  })
}

export async function getCategories(token: string): Promise<Category[]> {
  return (await adminRequest<Category[]>('categories', token)).data
}

export async function createCategory(token: string, name: string): Promise<Category> {
  return (await adminRequest<Category>('categories', token, { method: 'POST', body: JSON.stringify({ name }) })).data
}

export async function updateCategory(token: string, id: number, name: string): Promise<Category> {
  return (await adminRequest<Category>(`categories/${id}`, token, { method: 'PATCH', body: JSON.stringify({ name }) })).data
}

export async function deleteCategory(token: string, id: number): Promise<void> {
  await adminRequest<null>(`categories/${id}`, token, { method: 'DELETE' })
}

export async function getAdminStats(token: string): Promise<AdminStats> { return (await adminRequest<AdminStats>('stats', token)).data }
export async function getAdminReports(token: string, status = 'pending'): Promise<AdminPageResult<AdminReport>> {
  return (await adminRequest<AdminPageResult<AdminReport>>(`reports?status=${encodeURIComponent(status)}`, token)).data
}
export async function resolveReport(token: string, id: number, action: 'hide_product' | 'suspend_user' | 'dismiss'): Promise<void> {
  await adminRequest(`reports/${id}/resolve`, token, { method: 'POST', body: JSON.stringify({ action }) })
}
export async function getAdminOrders(token: string, q = '', status = ''): Promise<AdminPageResult<AdminOrder>> {
  const params = new URLSearchParams(); if (q) params.set('q', q); if (status) params.set('status', status)
  return (await adminRequest<AdminPageResult<AdminOrder>>(`orders?${params}`, token)).data
}
export async function updateCodOrder(token: string, id: number, action: 'delivered' | 'carrier_settled' | 'seller_paid' | 'delivery_failed' | 'retry_delivery' | 'returned' | 'refund_deposit', reason?: string, evidence?: string, collectionType?: 'carrier' | 'direct'): Promise<AdminOrder> {
  return (await adminRequest<AdminOrder>(`orders/${id}/cod`, token, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action, ...(reason ? { reason } : {}), ...(evidence ? { evidence } : {}), ...(collectionType ? { collection_type: collectionType } : {}) }) })).data
}
export async function confirmOrderDelivery(token: string, id: number, evidence: string): Promise<AdminOrder> {
  return (await adminRequest<AdminOrder>(`orders/${id}/delivery-confirmation`, token, { method: 'POST', body: JSON.stringify({ evidence }) })).data
}
export async function getAdminAuditLogs(token: string): Promise<AdminPageResult<AdminAuditLog>> {
  return (await adminRequest<AdminPageResult<AdminAuditLog>>('audit-logs', token)).data
}
