import { useState } from 'react'
import { useMutation, useQuery, useQueryClient, keepPreviousData } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import { LockKeyhole, ShieldCheck, Users } from 'lucide-react'
import { adminService } from '../../services/adminService'
import { useTranslation } from '../../layouts/AppLayouts'
import { interpolate, formatLocaleDate } from '../../i18n'
import type { AdminUser, StatusValue } from '../../types'
import { Avatar, Badge, Button, ConfirmDialog, Drawer, ErrorState, FilterToolbar, Pagination, Spinner, useToast } from '../../components/ui'

const statusTone: Record<string, 'success' | 'warning' | 'danger' | 'neutral'> = { active: 'success', deactivated: 'warning', suspended: 'danger', pending_verification: 'neutral' }
const perPageOptions = [10, 20, 50] as const
const statusValues: readonly ['active', 'suspended', 'deactivated'] = ['active', 'suspended', 'deactivated']

/** Page, rows-per-page, search, and filters are synchronized with URL query parameters. Pagination itself is server-side. */
function useAdminUserPage() {
  const [params, setParams] = useSearchParams()
  const page = Math.max(1, Number(params.get('page') ?? 1) || 1)
  const requestedPerPage = Number(params.get('per_page'))
  const perPage = (perPageOptions.includes(requestedPerPage as 10 | 20 | 50) ? requestedPerPage : 20) as 10 | 20 | 50
  const search = params.get('q') ?? ''
  const statusFilter = params.get('status') ?? ''
  const update = (patch: Record<string, string>) => {
    const next = new URLSearchParams(params.toString())
    for (const [key, value] of Object.entries(patch)) { if (value) next.set(key, value); else next.delete(key) }
    setParams(next, { replace: true })
  }
  return { page, perPage, search, statusFilter, update }
}

export function AdminUsersPage() {
  const { t, locale } = useTranslation()
  const { page, perPage, search, statusFilter, update } = useAdminUserPage()
  const [selected, setSelected] = useState<AdminUser | null>(null)
  const [confirmStatus, setConfirmStatus] = useState<{ user: AdminUser; status: StatusValue } | null>(null)
  const toast = useToast(); const client = useQueryClient()
  const list = useQuery({ queryKey: ['admin-users', page, perPage], queryFn: () => adminService.users(page, perPage), placeholderData: keepPreviousData })
  const detail = useQuery({ queryKey: ['admin-user', selected?.id], queryFn: () => adminService.user(selected!.id), enabled: Boolean(selected) })
  const statusChange = useMutation({
    mutationFn: () => adminService.updateStatus(confirmStatus!.user.id, confirmStatus!.status),
    onSuccess: (user) => { toast('success', t.adminUsers.statusUpdated); setConfirmStatus(null); setSelected(user); void client.invalidateQueries({ queryKey: ['admin-users'] }) },
    onError: () => toast('danger', t.adminUsers.statusFailed),
  })

  const roleLabel = (user: AdminUser) => t.roles[user.role as keyof typeof t.roles] ?? user.role
  const rows: AdminUser[] | undefined = list.data?.users
  // The admin API paginates server-side; quick text/status narrowing happens over the current page only.
  const visible = rows?.filter((user) => !search || `${user.name} ${user.username} ${user.email ?? ''}`.toLowerCase().includes(search.toLowerCase())).filter((user) => !statusFilter || user.status === statusFilter)
  const total = list.data?.meta.total ?? 0
  const from = total === 0 ? 0 : (page - 1) * perPage + 1
  const to = Math.min(page * perPage, total)

  return <section className="page-shell admin-users-page">
    <div className="page-heading"><div><p className="eyebrow">{t.admin.title}</p><h1>{t.admin.userManagement}</h1><p className="page-description">{t.adminUsers.description}</p></div><span className="heading-icon"><Users size={22} /></span></div>
    <FilterToolbar search={search} onSearch={(value) => update({ q: value, page: '' })} filterLabel={t.adminUsers.status} clearLabel={t.filters.clear} resetLabel={t.filters.reset}
      filters={[{ label: t.adminUsers.status, value: statusFilter, options: statusValues.map((status) => ({ value: status, label: t.adminUsers[status] })), onChange: (value) => update({ status: value, page: '' }) }]}
      activeFilters={statusFilter ? [{ key: 'status', label: `${t.adminUsers.status}: ${t.adminUsers[statusFilter as StatusValue] ?? statusFilter}` }] : []}
      onRemoveFilter={() => update({ status: '' })} onReset={() => update({ q: '', status: '', page: '' })}
      resultCount={visible?.length} resultsLabel={interpolate(t.filters.results, { count: visible?.length ?? 0 })} searchPlaceholder={t.filters.searchPlaceholder} />
    {list.isPending && <div className="table-loading"><Spinner label={t.adminUsers.loading} /></div>}
    {list.isError && <ErrorState title={t.adminUsers.loadFailed} action={<Button variant="soft" size="sm" onClick={() => list.refetch()}>{t.actions.reset}</Button>} />}
    {list.isSuccess && (
      <>
        <div className="table-scroll"><table className="data-table"><caption className="sr-only">{t.admin.userManagement}</caption>
          <thead><tr><th scope="col">{t.adminUsers.tableUser}</th><th scope="col">{t.adminUsers.tableRole}</th><th scope="col">{t.adminUsers.status}</th><th scope="col">{t.adminUsers.tableJoined}</th><th scope="col"><span className="sr-only">{t.adminUsers.details}</span></th></tr></thead>
          <tbody>
            {visible?.map((user) => <tr key={user.id}>
              <td><div className="table-user"><Avatar name={user.name} size="sm" /><span><strong>{user.name}</strong><small>@{user.username}</small></span></div></td>
              <td><Badge tone={user.role === 'super_admin' ? 'primary' : user.role === 'moderator' ? 'warning' : 'neutral'}>{roleLabel(user)}</Badge></td>
              <td><Badge tone={statusTone[user.status] ?? 'neutral'}>{t.adminUsers[user.status as StatusValue] ?? user.status}</Badge></td>
              <td className="muted">{user.created_at ? formatLocaleDate(user.created_at, locale) : '—'}</td>
              <td><Button variant="ghost" size="sm" onClick={() => setSelected(user)}>{t.adminUsers.details}</Button></td>
            </tr>)}
            {visible?.length === 0 && <tr><td colSpan={5}><div className="table-empty"><LockKeyhole size={18} />{t.empty.title} · {t.empty.description}</div></td></tr>}
          </tbody>
        </table></div>
        <Pagination page={page} pageSize={perPage} total={total} rowsPerPageLabel={t.pagination.rowsPerPage}
          rangeLabel={interpolate(t.pagination.range, { from, to, total })}
          onPage={(nextPage) => update({ page: String(nextPage) })} onPageSize={(size) => update({ per_page: String(size), page: '' })} />
      </>
    )}

    <Drawer open={Boolean(selected)} title={t.adminUsers.details} onClose={() => setSelected(null)}>
      {detail.isPending && <Spinner label={t.adminUsers.loading} />}
      {detail.isSuccess && <div className="admin-user-detail">
        <div className="drawer-user"><Avatar name={detail.data.name} size="lg" /><div><h3>{detail.data.name}</h3><p className="muted">@{detail.data.username}</p></div></div>
        <dl className="detail-list">
          <div><dt>{t.auth.email}</dt><dd>{detail.data.email ?? t.adminUsers.emailHidden}</dd></div>
          <div><dt>{t.adminUsers.tableRole}</dt><dd><Badge tone={detail.data.role === 'super_admin' ? 'primary' : 'neutral'}>{roleLabel(detail.data)}</Badge></dd></div>
          <div><dt>{t.adminUsers.status}</dt><dd><Badge tone={statusTone[detail.data.status] ?? 'neutral'}>{t.adminUsers[detail.data.status as StatusValue] ?? detail.data.status}</Badge></dd></div>
          <div><dt>{t.adminUsers.tableJoined}</dt><dd>{detail.data.created_at ? formatLocaleDate(detail.data.created_at, locale) : '—'}</dd></div>
        </dl>
        <div className="detail-note"><ShieldCheck size={14} /> {t.admin.accountConfirm}</div>
        {detail.data.role !== 'super_admin' && <div className="detail-actions">
          {statusValues.filter((status) => status !== detail.data.status).map((status) => <Button key={status} variant={status === 'active' ? 'soft' : 'ghost'} size="sm" onClick={() => setConfirmStatus({ user: detail.data, status })}>{t.adminUsers[`set_${status}` as 'set_active' | 'set_deactivated' | 'set_suspended']}</Button>)}
        </div>}
      </div>}
    </Drawer>

    <ConfirmDialog open={Boolean(confirmStatus)} danger loading={statusChange.isPending}
      title={interpolate(t.adminUsers.statusConfirmTitle, { name: confirmStatus?.user.name ?? '', status: confirmStatus ? (t.adminUsers[confirmStatus.status] ?? confirmStatus.status) : '' })}
      description={t.admin.accountConfirm} confirmLabel={t.actions.confirm} cancelLabel={t.actions.cancel}
      onConfirm={() => statusChange.mutate()} onClose={() => setConfirmStatus(null)} />
  </section>
}
