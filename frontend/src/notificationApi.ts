export type NotificationItem = {
  id: number
  type: string
  title: string
  body: string
  action_url: string
  data: Record<string, unknown> | null
  read_at: string | null
  created_at: string
  actor: { id: number; name: string } | null
}

export type NotificationList = { items: NotificationItem[]; unread_count: number; current_page: number; last_page: number; total: number }
type Result<T> = { status: string; message: string; data: T; errors?: Record<string, string[]> }

export async function notificationRequest<T>(path: string, token: string, options: RequestInit = {}): Promise<Result<T>> {
  let response: Response
  try {
    response = await fetch(`/api/v1/notifications${path}`, {
      ...options,
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}`, ...options.headers },
    })
  } catch { throw new Error('Không kết nối được với máy chủ. Thử lại sau nhé.') }
  const result = await response.json() as Result<T>
  if (!response.ok) {
    const detail = result.errors && Object.values(result.errors)[0]?.[0]
    throw new Error(detail || result.message || 'Không tải được thông báo.')
  }
  if (options.method?.toUpperCase() === 'PATCH') window.dispatchEvent(new Event('hsmart:notifications-updated'))
  return result
}

export async function getUnreadNotificationCount(token: string): Promise<number> {
  const result = await notificationRequest<{ unread_count: number }>('/unread-count', token)
  return result.data.unread_count
}
