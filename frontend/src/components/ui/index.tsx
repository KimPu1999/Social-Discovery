import { useEffect, useRef, useState, type ButtonHTMLAttributes, type HTMLAttributes, type InputHTMLAttributes, type ReactNode } from 'react'
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
  useEffect(() => { if (!open) return; const key = (event: KeyboardEvent) => { if (event.key === 'Escape') onClose() }; document.addEventListener('keydown', key); return () => document.removeEventListener('keydown', key) }, [open, onClose])
  if (!open) return null
  return <div className="ui-modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}><section className="ui-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title"><div className="ui-modal-header"><h2 id="modal-title">{title}</h2><IconButton label="Close" onClick={onClose}><X size={18} /></IconButton></div>{children}</section></div>
}

export function Drawer({ open, title, onClose, children }: { open: boolean; title: string; onClose: () => void; children: ReactNode }) {
  if (!open) return null
  return <div className="ui-drawer-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}><aside className="ui-drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-title"><div className="ui-modal-header"><h2 id="drawer-title">{title}</h2><IconButton label="Close" onClick={onClose}><X size={18} /></IconButton></div>{children}</aside></div>
}

export function Switch({ checked, onChange, label }: { checked: boolean; onChange: (checked: boolean) => void; label: string }) { return <label className="ui-switch"><span>{label}</span><input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} /><span className="ui-switch-track" aria-hidden="true" /></label> }

