import { useEffect, useRef, useState, type ButtonHTMLAttributes, type HTMLAttributes, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react'
import { Link } from 'react-router-dom'
import { create } from 'zustand'
import { Check, ChevronDown, LoaderCircle, Search, X } from 'lucide-react'

type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'soft' | 'danger'
type ButtonSize = 'sm' | 'md' | 'lg'

export function Button({ variant = 'primary', size = 'md', loading = false, children, className = '', disabled, ...props }: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: ButtonVariant; size?: ButtonSize; loading?: boolean }) {
  return <button className={`ui-button ui-button-${variant} ui-button-${size} ${className}`} disabled={disabled || loading} {...props}>{loading && <LoaderCircle className="spin" size={16} aria-hidden="true" />}{children}</button>
}

export function IconButton({ label, children, className = '', ...props }: ButtonHTMLAttributes<HTMLButtonElement> & { label: string }) {
  return <button className={`ui-icon-button ${className}`} aria-label={label} title={label} {...props}>{children}</button>
}

export function Input({ label, error, hint, className = '', id, ...props }: InputHTMLAttributes<HTMLInputElement> & { label?: string; error?: string; hint?: string }) {
  const inputId = id ?? `input-${label?.toLowerCase().replace(/[^a-z0-9]+/g, '-') ?? 'field'}`
  return <label className="ui-field" htmlFor={inputId}>{label && <span className="ui-label">{label}</span>}<input id={inputId} className={`ui-input ${error ? 'is-invalid' : ''} ${className}`} aria-invalid={Boolean(error)} {...props} />{error ? <span className="ui-field-error" role="alert">{error}</span> : hint ? <span className="ui-field-hint">{hint}</span> : null}</label>
}

export function SearchBar({ value, onChange, onClear, placeholder = 'Search', className = '' }: { value?: string; onChange?: (value: string) => void; onClear?: () => void; placeholder?: string; className?: string }) {
  return <label className={`ui-search ${className}`}><Search size={17} aria-hidden="true" /><input value={value} onChange={(event) => onChange?.(event.target.value)} placeholder={placeholder} aria-label={placeholder} />{value && onClear ? <IconButton label="Clear search" onClick={onClear}><X size={15} /></IconButton> : null}</label>
}

export function Avatar({ name, src, size = 'md', className = '' }: { name: string; src?: string; size?: 'sm' | 'md' | 'lg' | 'xl'; className?: string }) {
  const initials = name.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase()
  return <span className={`ui-avatar ui-avatar-${size} ${className}`}>{src ? <img src={src} alt="" loading="lazy" /> : initials}</span>
}

export function Badge({ children, tone = 'neutral' }: { children: ReactNode; tone?: 'neutral' | 'primary' | 'success' | 'warning' | 'danger' }) { return <span className={`ui-badge ui-badge-${tone}`}>{children}</span> }
export function InterestChip({ children, selected = false, onClick }: { children: ReactNode; selected?: boolean; onClick?: () => void }) { const Tag = onClick ? 'button' : 'span'; return <Tag className={`ui-chip ${selected ? 'is-selected' : ''}`} onClick={onClick}>{selected && <Check size={13} aria-hidden="true" />}{children}</Tag> }
export function Card({ children, className = '', ...props }: HTMLAttributes<HTMLDivElement>) { return <div className={`ui-card ${className}`} {...props}>{children}</div> }
export function CardHeader({ title, eyebrow, action }: { title: string; eyebrow?: string; action?: ReactNode }) { return <div className="ui-card-header"><div>{eyebrow && <p className="eyebrow">{eyebrow}</p>}<h2>{title}</h2></div>{action}</div> }
export function EmptyState({ icon, title, description, action }: { icon?: ReactNode; title: string; description: string; action?: ReactNode }) { return <div className="ui-empty-state"><span className="ui-empty-icon">{icon}</span><h2>{title}</h2><p>{description}</p>{action}</div> }
export function ErrorState({ title = 'Something went wrong', description = 'Please try again in a moment.', action }: { title?: string; description?: string; action?: ReactNode }) { return <div className="ui-error-state" role="alert"><h2>{title}</h2><p>{description}</p>{action}</div> }
export function Skeleton({ className = '' }: { className?: string }) { return <span className={`ui-skeleton ${className}`} aria-hidden="true" /> }
export function Spinner({ label = 'Loading' }: { label?: string }) { return <span className="ui-spinner" role="status" aria-label={label}><LoaderCircle className="spin" size={18} /></span> }

export function Dropdown({ label, children, align = 'right' }: { label: ReactNode; children: ReactNode; align?: 'left' | 'right' }) {
  const [open, setOpen] = useState(false); const ref = useRef<HTMLDivElement>(null)
  useEffect(() => { const close = (event: MouseEvent) => { if (!ref.current?.contains(event.target as Node)) setOpen(false) }; const key = (event: KeyboardEvent) => { if (event.key === 'Escape') setOpen(false) }; document.addEventListener('mousedown', close); document.addEventListener('keydown', key); return () => { document.removeEventListener('mousedown', close); document.removeEventListener('keydown', key) } }, [])
  return <div ref={ref} className="ui-dropdown"><button className="ui-dropdown-trigger" onClick={() => setOpen((value) => !value)} aria-expanded={open}>{label}<ChevronDown size={15} aria-hidden="true" /></button>{open && <div className={`ui-dropdown-menu ui-dropdown-${align}`} role="menu" onClick={() => setOpen(false)}>{children}</div>}</div>
}

export function Modal({ open, title, onClose, children }: { open: boolean; title: string; onClose: () => void; children: ReactNode }) {
  const backdropRef = useRef<HTMLDivElement>(null)
  useEffect(() => {
    if (!open) return
    const key = (event: KeyboardEvent) => { if (event.key === 'Escape') onClose() }
    document.addEventListener('keydown', key)
    const previousOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden'
    const container = backdropRef.current
    if (container) {
      const focusable = container.querySelectorAll<HTMLElement>('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')
      const first = focusable[0] ?? container; const last = focusable[focusable.length - 1]
      const trap = (event: KeyboardEvent) => { if (event.key !== 'Tab' || !focusable.length) return; if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() } }
      first.focus(); document.addEventListener('keydown', trap)
      return () => { document.removeEventListener('keydown', trap); document.body.style.overflow = previousOverflow; document.removeEventListener('keydown', key) }
    }
    return () => document.removeEventListener('keydown', key)
  }, [open, onClose])
  if (!open) return null
  return <div ref={backdropRef} className="ui-modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}><section className="ui-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title"><div className="ui-modal-header"><h2 id="modal-title">{title}</h2><IconButton label="Close" onClick={onClose}><X size={18} /></IconButton></div>{children}</section></div>
}

export function Drawer({ open, title, onClose, children }: { open: boolean; title: string; onClose: () => void; children: ReactNode }) {
  if (!open) return null
  return <div className="ui-drawer-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}><aside className="ui-drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-title"><div className="ui-modal-header"><h2 id="drawer-title">{title}</h2><IconButton label="Close" onClick={onClose}><X size={18} /></IconButton></div>{children}</aside></div>
}

export function Switch({ checked, onChange, label }: { checked: boolean; onChange: (checked: boolean) => void; label: string }) { return <label className="ui-switch"><span>{label}</span><input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} /><span className="ui-switch-track" aria-hidden="true" /></label> }

export function Textarea({ label, error, hint, id, rows = 4, ...props }: TextareaHTMLAttributes<HTMLTextAreaElement> & { label?: string; error?: string; hint?: string }) {
  const fieldId = id ?? `textarea-${label?.toLowerCase().replace(/[^a-z0-9]+/g, '-') ?? 'field'}`
  return <label className="ui-field" htmlFor={fieldId}>{label && <span className="ui-label">{label}</span>}<textarea id={fieldId} rows={rows} className={`ui-input ${error ? 'is-invalid' : ''}`} aria-invalid={Boolean(error)} {...props} />{error ? <span className="ui-field-error" role="alert">{error}</span> : hint ? <span className="ui-field-hint">{hint}</span> : null}</label>
}

export function Select({ label, error, hint, id, options, ...props }: SelectHTMLAttributes<HTMLSelectElement> & { label?: string; error?: string; hint?: string; options: readonly { value: string; label: string }[] }) {
  const fieldId = id ?? `select-${label?.toLowerCase().replace(/[^a-z0-9]+/g, '-') ?? 'field'}`
  return <label className="ui-field" htmlFor={fieldId}>{label && <span className="ui-label">{label}</span>}<span className="ui-select-wrap"><select id={fieldId} className={`ui-input ${error ? 'is-invalid' : ''}`} aria-invalid={Boolean(error)} {...props}>{options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select><ChevronDown className="ui-select-caret" size={15} aria-hidden="true" /></span>{error ? <span className="ui-field-error" role="alert">{error}</span> : hint ? <span className="ui-field-hint">{hint}</span> : null}</label>
}

export function Checkbox({ label, className = '', ...props }: InputHTMLAttributes<HTMLInputElement> & { label: ReactNode }) {
  return <label className={`ui-check ${className}`}><input type="checkbox" {...props} /><span className="ui-check-mark" aria-hidden="true"><Check size={12} /></span><span className="ui-check-label">{label}</span></label>
}

export function Radio({ label, className = '', ...props }: InputHTMLAttributes<HTMLInputElement> & { label: ReactNode }) {
  return <label className={`ui-radio ${className}`}><input type="radio" {...props} /><span className="ui-radio-dot" aria-hidden="true" /><span className="ui-check-label">{label}</span></label>
}

export function Alert({ tone = 'info', title, children }: { tone?: 'info' | 'success' | 'warning' | 'danger'; title: string; children?: ReactNode }) {
  return <div className={`ui-alert ui-alert-${tone}`} role={tone === 'danger' ? 'alert' : 'status'}><strong>{title}</strong>{children ? <span>{children}</span> : null}</div>
}

export function Tabs({ items, active, onChange, label }: { items: readonly { id: string; label: string }[]; active: string; onChange: (id: string) => void; label?: string }) {
  const ref = useRef<HTMLDivElement>(null)
  const move = (delta: number) => { const index = items.findIndex((item) => item.id === active); const next = items[(index + delta + items.length) % items.length]; onChange(next.id); ref.current?.querySelector<HTMLButtonElement>(`[data-tab="${next.id}"]`)?.focus() }
  return <div ref={ref} className="ui-tabs" role="tablist" aria-label={label} onKeyDown={(event) => { if (event.key === 'ArrowRight') { event.preventDefault(); move(1) } else if (event.key === 'ArrowLeft') { event.preventDefault(); move(-1) } }}>{items.map((item) => <button key={item.id} type="button" data-tab={item.id} role="tab" aria-selected={item.id === active} tabIndex={item.id === active ? 0 : -1} className={`ui-tab ${item.id === active ? 'is-active' : ''}`} onClick={() => onChange(item.id)}>{item.label}</button>)}</div>
}

export function Tooltip({ content, children, position = 'right' }: { content: string; children: ReactNode; position?: 'top' | 'right' | 'bottom' | 'left' }) {
  const [visible, setVisible] = useState(false)
  return <span className={`ui-tooltip ui-tooltip-${position} ${visible ? 'is-visible' : ''}`} onMouseEnter={() => setVisible(true)} onMouseLeave={() => setVisible(false)} onFocus={() => setVisible(true)} onBlur={() => setVisible(false)} tabIndex={-1}>{children}<span role="tooltip" className="ui-tooltip-bubble">{content}</span></span>
}

const toastTones = { info: 'info', success: 'success', warning: 'warning', danger: 'danger' } as const
export type ToastTone = keyof typeof toastTones
type ToastEntry = { id: number; tone: ToastTone; title: string; description?: string }
type ToastState = { toasts: ToastEntry[]; push: (tone: ToastTone, title: string, description?: string) => void; dismiss: (id: number) => void }
let toastId = 0
export const useToastStore = create<ToastState>()((set) => ({
  toasts: [],
  push: (tone, title, description) => { const id = ++toastId; set((state) => ({ toasts: [...state.toasts, { id, tone, title, description }].slice(-4) })); setTimeout(() => set((state) => ({ toasts: state.toasts.filter((toast) => toast.id !== id) })), 5000) },
  dismiss: (id) => set((state) => ({ toasts: state.toasts.filter((toast) => toast.id !== id) })),
}))
export function useToast() { return useToastStore((state) => state.push) }

export function ToastViewport() {
  const { toasts, dismiss } = useToastStore()
  if (!toasts.length) return null
  return <div className="ui-toast-viewport" aria-live="polite" aria-atomic="false">{toasts.map((toast) => <div key={toast.id} className={`ui-toast ui-toast-${toast.tone}`} role="status"><span className="ui-toast-copy"><strong>{toast.title}</strong>{toast.description && <small>{toast.description}</small>}</span><IconButton label="Dismiss notification" onClick={() => dismiss(toast.id)}><X size={14} /></IconButton></div>)}</div>
}

export function Breadcrumb({ items, separator = '/' }: { items: readonly { label: string; href?: string }[]; separator?: string }) {
  const last = items.length - 1
  return <nav className="ui-breadcrumb" aria-label="Breadcrumb">{items.map((item, index) => <span key={item.label} className="ui-breadcrumb-segment">{index > 0 && <span className="ui-breadcrumb-sep" aria-hidden="true">{separator}</span>}{index < last && item.href ? <Link to={item.href}>{item.label}</Link> : <span aria-current="page">{item.label}</span>}</span>)}</nav>
}

export function Pagination({ page, pageSize, total, onPage, onPageSize, pageSizeOptions = [10, 20, 50], rowsPerPageLabel = 'Rows per page', rangeLabel }: { page: number; pageSize: number; total: number; onPage: (page: number) => void; onPageSize: (size: number) => void; pageSizeOptions?: readonly number[]; rowsPerPageLabel: string; rangeLabel: string }) {
  const pages = Math.max(1, Math.ceil(total / pageSize))
  return <div className="ui-pagination"><p className="ui-pagination-range">{rangeLabel}</p><div className="ui-pagination-pages"><IconButton label="Previous page" disabled={page <= 1} onClick={() => onPage(page - 1)}><ChevronDown style={{ transform: 'rotate(90deg)' }} size={16} /></IconButton><span className="ui-pagination-page-text" aria-live="polite">{page} / {pages}</span><IconButton label="Next page" disabled={page >= pages} onClick={() => onPage(page + 1)}><ChevronDown style={{ transform: 'rotate(270deg)' }} size={16} /></IconButton><label className="ui-pagination-size"><span>{rowsPerPageLabel}</span><select value={pageSize} onChange={(event) => onPageSize(Number(event.target.value))}>{pageSizeOptions.map((option) => <option key={option} value={option}>{option}</option>)}</select></label></div></div>
}

export function FilterToolbar({ search, onSearch, filters, activeFilters, onRemoveFilter, onReset, sortOptions, sortValue, onSort, resultCount, resultsLabel, searchPlaceholder = 'Search…', filterLabel = 'Filter', sortLabel = 'Sort', resetLabel = 'Reset filters', clearLabel = 'Clear all' }: { search: string; onSearch: (value: string) => void; filters?: { label: string; value: string; options: readonly { value: string; label: string }[]; onChange: (value: string) => void }[]; activeFilters?: readonly { key: string; label: string }[]; onRemoveFilter?: (key: string) => void; onReset?: () => void; sortOptions?: readonly { value: string; label: string }[]; sortValue?: string; onSort?: (value: string) => void; resultCount?: number; /** Already-localized string, e.g. "12 results". */ resultsLabel: string; searchPlaceholder?: string; filterLabel?: string; sortLabel?: string; resetLabel?: string; clearLabel?: string }) {
  return <div className="ui-filter-toolbar"><SearchBar value={search} onChange={onSearch} placeholder={searchPlaceholder} className="ui-filter-search" />{filters?.map((filter) => <Dropdown key={filter.label} align="left" label={<span>{filter.label}</span>}><button className="menu-item" role="menuitem" onClick={() => filter.onChange('')}>— {filterLabel}</button>{filter.options.map((option) => <button key={option.value} className={`menu-item ${filter.value === option.value ? 'is-active-option' : ''}`} role="menuitem" onClick={() => filter.onChange(option.value)}>{option.label}</button>)}</Dropdown>)}{sortOptions && onSort ? <Dropdown align="left" label={<span>{sortLabel}</span>}><button className="menu-item" onClick={() => onSort('')}>—</button>{sortOptions.map((option) => <button key={option.value} className={`menu-item ${sortValue === option.value ? 'is-active-option' : ''}`} onClick={() => onSort(option.value)}>{option.label}</button>)}</Dropdown> : null}{onReset && (search || activeFilters?.length || sortValue) ? <Button variant="ghost" size="sm" onClick={onReset}>{resetLabel}</Button> : null}{resultCount !== undefined && <span className="ui-filter-count" aria-live="polite">{resultsLabel}</span>}{activeFilters?.length ? <div className="ui-filter-chips">{activeFilters.map((chip) => <InterestChip key={chip.key} onClick={() => onRemoveFilter?.(chip.key)}>{chip.label}<X size={11} aria-label={clearLabel} /></InterestChip>)}</div> : null}</div>
}

export function ConfirmDialog({ open, title, description, confirmLabel = 'Confirm', cancelLabel = 'Cancel', danger = false, loading = false, onConfirm, onClose, children }: { open: boolean; title: string; description?: string; confirmLabel?: string; cancelLabel?: string; danger?: boolean; loading?: boolean; onConfirm: () => void; onClose: () => void; children?: ReactNode }) {
  return <Modal open={open} title={title} onClose={onClose}>{description && <p className="ui-confirm-description">{description}</p>}{children}<div className="ui-confirm-actions"><Button variant="ghost" onClick={onClose} disabled={loading}>{cancelLabel}</Button><Button variant={danger ? 'danger' : 'primary'} onClick={onConfirm} loading={loading} autoFocus>{confirmLabel}</Button></div></Modal>
}


