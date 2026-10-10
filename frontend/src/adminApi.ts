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
