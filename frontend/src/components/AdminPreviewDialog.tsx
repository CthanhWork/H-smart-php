import type { ReactNode } from 'react'
import { X } from 'lucide-react'

type Props = { title: string; subtitle?: string; onClose: () => void; children: ReactNode }

export default function AdminPreviewDialog({ title, subtitle, onClose, children }: Props) {
  return <div className="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/55 p-3 backdrop-blur-sm sm:p-6" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}>
    <section role="dialog" aria-modal="true" aria-label={title} className="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
      <header className="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
        <div className="min-w-0"><p className="text-xs font-semibold uppercase tracking-wider text-orange-600">Xem trước</p><h2 className="mt-1 truncate text-lg font-bold text-slate-900">{title}</h2>{subtitle && <p className="mt-1 text-sm text-slate-500">{subtitle}</p>}</div>
        <button type="button" onClick={onClose} aria-label="Đóng khung xem trước" className="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900"><X size={18} /></button>
      </header>
      <div className="overflow-y-auto px-5 py-5 sm:px-6">{children}</div>
    </section>
  </div>
}
