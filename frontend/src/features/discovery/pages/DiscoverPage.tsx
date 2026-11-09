import { useMemo, useState, type ReactNode } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { ArrowRight, Bookmark, CalendarDays, Compass, Filter, Hash, Sparkles, Users } from 'lucide-react'
import { useTranslation } from '../../../layouts/AppLayouts'
import { formatLocaleDate, interpolate } from '../../../i18n'
import { Alert, Badge, Button, Card, CardHeader, Drawer, Pagination, Skeleton, Tabs, useToast } from '../../../components/ui'
import { DEFAULT_PEOPLE_FILTERS, filtersToSearchParams, parsePeopleFilters } from '../hooks/useDiscoveryFilters'
import type { DiscoveryProfile, FeedbackType, PeopleFilters, PeoplePerPage } from '../types'
import { useDiscoverPeople, useDiscoveryFeedback, useDiscoveryInterests, useDiscoveryOverview, useRecommendedPeople } from '../hooks/useDiscovery'
import { useDiscoveryFeed, useDiscoverySaved, useDiscoveryTrending } from '../hooks/useDiscoveryContent'
import type { DiscoveryPost } from '../api/discoveryContentApi'
import { RecommendedPeopleSection } from '../components/RecommendedPeopleSection'
import { PeopleGrid } from '../components/PeopleGrid'
import { DiscoveryEmptyState } from '../components/DiscoveryEmptyState'
import { DiscoverySkeleton } from '../components/DiscoverySkeleton'
import { DiscoverySearchBar } from '../components/DiscoverySearchBar'
import { DiscoveryFilterToolbar } from '../components/DiscoveryFilterToolbar'
import { InterestFilterChips } from '../components/InterestFilterChips'

type DiscoverTab = 'for-you' | 'people' | 'posts' | 'communities' | 'events' | 'trending' | 'saved'
const tabValues: DiscoverTab[] = ['for-you', 'people', 'posts', 'communities', 'events', 'trending', 'saved']

/** Route /discover — a URL-addressable discovery workspace over the existing APIs. */
export function DiscoverPage() {
  const { t, locale } = useTranslation()
  const toast = useToast()
  const [searchParams, setSearchParams] = useSearchParams()
  const [view, setView] = useState<'grid' | 'list'>('grid')
  const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false)
  const filters = useMemo(() => parsePeopleFilters(searchParams), [searchParams])
  const rawTab = searchParams.get('tab') as DiscoverTab | null
  const tab: DiscoverTab = rawTab && tabValues.includes(rawTab) ? rawTab : 'for-you'

  const overview = useDiscoveryOverview()
  const enabled = overview.data?.discovery_enabled ?? false
  const recommended = useRecommendedPeople(enabled)
  const people = useDiscoverPeople(filters, enabled)
  const interests = useDiscoveryInterests(enabled)
  const feedback = useDiscoveryFeedback()
  const posts = useDiscoveryFeed(enabled && tab === 'posts')
  const saved = useDiscoverySaved(enabled && tab === 'saved')
  const trending = useDiscoveryTrending(enabled && tab === 'trending')

  const interestNames = useMemo(() => {
    const map = new Map<number, string>()
    for (const group of interests.data ?? []) for (const interest of group.interests) map.set(interest.id, interest.name)
    return map
  }, [interests.data])
  const recommendedIds = new Set((recommended.data ?? []).map((profile) => profile.id))
  const explorePeople = (people.data?.data ?? []).filter((profile) => tab !== 'for-you' || !recommendedIds.has(profile.id))
  const activeFilterCount = Number(Boolean(filters.search)) + filters.interest_ids.length + Number(filters.sort !== 'relevance')

  const onChange = (patch: Partial<PeopleFilters>) => {
    const next = { ...filters, ...patch }
    setSearchParams((previous) => filtersToSearchParams(previous, next), { replace: false })
  }

  const onTabChange = (nextTab: string) => {
    if (!tabValues.includes(nextTab as DiscoverTab)) return
    setSearchParams((previous) => {
      const next = new URLSearchParams(previous)
      if (nextTab === 'for-you') next.delete('tab')
      else next.set('tab', nextTab)
      return next
    }, { replace: false })
  }

  const onFeedback = (profile: DiscoveryProfile, type: FeedbackType) => {
    feedback.give.mutate({ targetUserId: profile.id, feedbackType: type }, {
      onSuccess: () => toast('success', type === 'hide_profile' ? t.discovery.feedback.hiddenTitle : t.discovery.feedback.notInterestedTitle, t.discovery.feedback.hiddenCopy),
      onError: () => toast('danger', t.discovery.feedback.failed),
    })
  }

  const tabs = [
    { id: 'for-you', label: locale === 'my' ? 'သင့်အတွက်' : 'For You' },
    { id: 'people', label: locale === 'my' ? 'လူများ' : 'People' },
    { id: 'posts', label: locale === 'my' ? 'ပို့စ်များ' : 'Posts' },
    { id: 'communities', label: locale === 'my' ? 'အသိုင်းအဝိုင်းများ' : 'Communities' },
    { id: 'events', label: locale === 'my' ? 'ပွဲများ' : 'Events' },
    { id: 'trending', label: locale === 'my' ? 'ရေပန်းစားသည်များ' : 'Trending' },
    { id: 'saved', label: locale === 'my' ? 'သိမ်းထားသည်များ' : 'Saved' },
  ] as const

  return (
    <div className="discovery-page discovery-page-redesign discovery-workspace">
      <header className="discovery-page-header discovery-hero">
        <div className="discovery-hero-copy">
          <div className="discovery-hero-label"><span className="feature-icon"><Compass size={16} /></span><p className="eyebrow">{t.nav.discover}</p></div>
          <h1>{t.discovery.title}</h1>
          <p>{t.discovery.intro}</p>
        </div>
        <div className="discovery-hero-tools">
          <DiscoverySearchBar value={filters.search} onDebouncedChange={(value) => onChange({ search: value, page: 1 })} />
          <Button variant="soft" size="sm" className="discovery-filter-trigger" onClick={() => setMobileFiltersOpen(true)}><Filter size={15} />{locale === 'my' ? 'စစ်ထုတ်ရန်' : 'Filters'}{activeFilterCount ? <Badge tone="primary">{activeFilterCount}</Badge> : null}</Button>
        </div>
      </header>

      {overview.isLoading ? <DiscoverySkeleton count={4} /> : overview.isError ? (
        <Card><DiscoveryEmptyState variant="noEligible" action={<Button variant="soft" onClick={() => void overview.refetch()}>{t.discovery.error.retry}</Button>} /></Card>
      ) : overview.data ? (
        <>
          <section aria-label={t.discovery.title} className="discovery-summary">
            <div className="discovery-summary-top"><div><Badge tone={enabled ? 'success' : 'warning'}>{enabled ? t.discovery.badgeOn : t.discovery.badgeOff}</Badge>{enabled ? <p>{t.discovery.recommended.copy}</p> : null}</div><Link className="ui-button ui-button-soft ui-button-sm" to="/settings/discovery">{t.discovery.openSettings}<ArrowRight size={14} /></Link></div>
            <dl className="discovery-summary-counts">
              <div><dt><Users size={15} aria-hidden="true" /> {t.discovery.overview.discoverable}</dt><dd>{overview.data.counts.discoverable_people}</dd></div>
              <div><dt><Sparkles size={15} aria-hidden="true" /> {t.discovery.overview.recommended}</dt><dd>{overview.data.counts.recommended_people}</dd></div>
              <div><dt>{t.discovery.overview.mine}</dt><dd>{overview.data.counts.my_interests}</dd></div>
              <div><dt>{t.discovery.overview.hidden}</dt><dd>{overview.data.counts.hidden_profiles}</dd></div>
            </dl>
          </section>

          <Tabs items={tabs} active={tab} onChange={onTabChange} label={locale === 'my' ? 'ရှာဖွေမှုအမျိုးအစားများ' : 'Discovery sections'} />

          {!enabled && <Alert tone="warning" title={t.discovery.empty.disabled}>{t.discovery.empty.disabledCopy}</Alert>}

          {enabled && (
            <div className="discovery-content-layout">
              <main className="discovery-content-main">
                <div className="discovery-desktop-filters">
                  <DiscoveryFilterToolbar filters={filters} onChange={onChange} onClearAll={() => onChange({ ...DEFAULT_PEOPLE_FILTERS, per_page: filters.per_page })} interestNames={interestNames} view={view} onViewChange={setView} />
                </div>
                {tab === 'for-you' && <>
                  <RecommendedPeopleSection recommendations={recommended.data ?? []} loading={recommended.isLoading} onFeedback={onFeedback} />
                  <section aria-labelledby="interests-title" className="discovery-interest-section">
                    <div className="discovery-section-heading"><div><p className="eyebrow">{t.discovery.explore.copy}</p><h2 id="interests-title">{t.discovery.explore.title}</h2></div><span className="discovery-section-icon"><Filter size={16} /></span></div>
                    <InterestFilterChips selected={filters.interest_ids} onToggle={(id) => onChange({ interest_ids: filters.interest_ids.includes(id) ? filters.interest_ids.filter((x) => x !== id) : [...filters.interest_ids, id], page: 1 })} showCounts />
                  </section>
                </>}
                {tab === 'people' || tab === 'for-you' ? <PeopleResults people={explorePeople} query={people} filters={filters} view={view} onFeedback={onFeedback} onChange={onChange} /> : null}
                {tab === 'posts' ? <PostResults query={posts} locale={locale} /> : null}
                {tab === 'saved' ? <SavedResults query={saved} locale={locale} /> : null}
                {tab === 'trending' ? <TrendingResults query={trending} locale={locale} /> : null}
                {tab === 'communities' ? <UnavailableDiscovery kind="communities" locale={locale} /> : null}
                {tab === 'events' ? <UnavailableDiscovery kind="events" locale={locale} /> : null}
              </main>
              <DiscoveryRail overview={overview.data} locale={locale} tab={tab} />
            </div>
          )}
        </>
      ) : null}

      <Drawer open={mobileFiltersOpen} title={locale === 'my' ? 'ရှာဖွေမှု စစ်ထုတ်များ' : 'Discovery filters'} onClose={() => setMobileFiltersOpen(false)}>
        <DiscoveryFilterToolbar filters={filters} onChange={onChange} onClearAll={() => onChange({ ...DEFAULT_PEOPLE_FILTERS, per_page: filters.per_page })} interestNames={interestNames} view={view} onViewChange={setView} />
        <Button className="discovery-drawer-done" onClick={() => setMobileFiltersOpen(false)}>{locale === 'my' ? 'ပြီးပါပြီ' : 'Done'}</Button>
      </Drawer>
    </div>
  )
}

function PeopleResults({ people, query, filters, view, onFeedback, onChange }: { people: DiscoveryProfile[]; query: ReturnType<typeof useDiscoverPeople>; filters: PeopleFilters; view: 'grid' | 'list'; onFeedback: (profile: DiscoveryProfile, type: FeedbackType) => void; onChange: (patch: Partial<PeopleFilters>) => void }) {
  const { t } = useTranslation()
  const meta = query.data?.meta
  const total = meta?.total ?? 0
  if (query.isLoading) return <DiscoverySkeleton count={filters.per_page} />
  if (query.isError) return <Card className="discovery-error-card" role="alert"><h2>{t.discovery.error.title}</h2><p>{t.discovery.error.copy}</p><Button variant="soft" onClick={() => void query.refetch()}>{t.discovery.error.retry}</Button></Card>
  if (total === 0) return filters.interest_ids.length ? <DiscoveryEmptyState variant="noMatchingInterests" action={<Button variant="soft" onClick={() => onChange({ interest_ids: [], page: 1 })}>{t.filters.clear}</Button>} /> : filters.search ? <DiscoveryEmptyState variant="noResults" action={<Button variant="soft" onClick={() => onChange({ search: '', page: 1 })}>{t.filters.clear}</Button>} /> : <DiscoveryEmptyState variant="noEligible" />
  return <><div className="discovery-section-heading"><div><p className="eyebrow">{t.discovery.people.intro}</p><h2>{t.discovery.explore.byInterests}</h2></div><span className="discovery-result-count">{interpolate(t.discovery.people.results, { count: total })}</span></div><PeopleGrid people={people} variant={view} onFeedback={onFeedback} /><Pagination page={filters.page} pageSize={filters.per_page} total={total} onPage={(page) => onChange({ page })} onPageSize={(size) => onChange({ per_page: size as PeoplePerPage, page: 1 })} pageSizeOptions={[10, 20, 50]} rowsPerPageLabel={t.discovery.pagination.rowsPerPage} rangeLabel={interpolate(t.discovery.pagination.range, { from: Math.min(total, (filters.page - 1) * filters.per_page + 1), to: Math.min(total, filters.page * filters.per_page), total })} /></>
}

function PostResults({ query, locale }: { query: ReturnType<typeof useDiscoveryFeed>; locale: 'en' | 'my' }) {
  if (query.isLoading) return <ContentSkeleton />
  if (query.isError) return <ContentError onRetry={() => void query.refetch()} locale={locale} />
  if (!query.data?.data.length) return <ContentEmpty icon={<Sparkles size={24} />} title={locale === 'my' ? 'ပို့စ် မရှိသေးပါ' : 'No posts to show'} copy={locale === 'my' ? 'သင်ကြည့်ခွင့်ရှိသော public posts များ ဤနေရာတွင် ပေါ်လာပါမည်။' : 'Public posts you can access will appear here.'} />
  return <section className="discovery-post-list"><CardHeader eyebrow={locale === 'my' ? 'လတ်တလောအကြောင်းအရာ' : 'RECENT CONTENT'} title={locale === 'my' ? 'သင့်အတွက် ပို့စ်များ' : 'Posts for you'} /><div className="discovery-posts">{query.data.data.map((post) => <PostPreview key={post.id} post={post} locale={locale} />)}</div></section>
}

function SavedResults({ query, locale }: { query: ReturnType<typeof useDiscoverySaved>; locale: 'en' | 'my' }) {
  if (query.isLoading) return <ContentSkeleton />
  if (query.isError) return <ContentError onRetry={() => void query.refetch()} locale={locale} />
  if (!query.data?.data.length) return <ContentEmpty icon={<Bookmark size={24} />} title={locale === 'my' ? 'သိမ်းထားသော ပို့စ် မရှိသေးပါ' : 'Nothing saved yet'} copy={locale === 'my' ? 'ဖိဒ်မှ ပို့စ်များကို သိမ်းထားပါက ဤနေရာတွင် ပြန်တွေ့နိုင်ပါမည်။' : 'Posts you save from the feed will appear here.'} />
  return <section className="discovery-post-list"><CardHeader eyebrow={locale === 'my' ? 'သီးသန့်စာရင်း' : 'PRIVATE LIST'} title={locale === 'my' ? 'သိမ်းထားသည်များ' : 'Saved posts'} /><div className="discovery-posts">{query.data.data.map((row) => <PostPreview key={row.post.id} post={row.post} locale={locale} />)}</div></section>
}

function TrendingResults({ query, locale }: { query: ReturnType<typeof useDiscoveryTrending>; locale: 'en' | 'my' }) {
  if (query.isLoading) return <ContentSkeleton />
  if (query.isError) return <ContentError onRetry={() => void query.refetch()} locale={locale} />
  if (!query.data?.data.length) return <ContentEmpty icon={<Hash size={24} />} title={locale === 'my' ? 'ရေပန်းစားသော tag မရှိသေးပါ' : 'No trending interests yet'} copy={locale === 'my' ? 'အများပြည်သူကြည့်ရှုနိုင်သော ပို့စ်များ ရှိလာသောအခါ tag များ ပေါ်လာပါမည်။' : 'Trending tags will appear as public posts are published.'} />
  return <section className="discovery-trending-list"><CardHeader eyebrow={locale === 'my' ? 'ယခုလက်ရှိ' : 'RIGHT NOW'} title={locale === 'my' ? 'ရေပန်းစားသော hashtags' : 'Trending hashtags'} /><div className="discovery-tag-grid">{query.data.data.map((tag) => <Link className="discovery-tag-card" key={tag.id} to={`/feed?hashtag=${encodeURIComponent(tag.slug)}`}><Hash size={17} /><span><strong>{tag.name}</strong><small>{tag.posts_count} {locale === 'my' ? 'ပို့စ်' : tag.posts_count === 1 ? 'post' : 'posts'}</small></span><ArrowRight size={15} /></Link>)}</div></section>
}

function PostPreview({ post, locale }: { post: DiscoveryPost; locale: 'en' | 'my' }) {
  return <article className="discovery-post-preview"><div className="discovery-post-preview-top"><div><strong>{post.author.display_name}</strong><span>@{post.author.username ?? 'member'}</span></div><time>{post.published_at ? formatLocaleDate(post.published_at, locale) : ''}</time></div><p>{post.body || (locale === 'my' ? 'ပုံနှင့်အတူ အပ်ဒိတ်တစ်ခု မျှဝေထားသည်။' : 'Shared a visual update.')}</p><footer><span>♡ {post.counts.reactions}</span><span>◌ {post.counts.comments}</span><span>↗ {post.counts.shares}</span>{post.hashtags.slice(0, 3).map((tag) => <Link key={tag.slug} to={`/feed?hashtag=${encodeURIComponent(tag.slug)}`}>#{tag.name}</Link>)}</footer></article>
}

function DiscoveryRail({ overview, locale, tab }: { overview: { counts: { discoverable_people: number; active_interests: number; my_interests: number } }; locale: 'en' | 'my'; tab: DiscoverTab }) {
  return <aside className="discovery-rail"><Card><CardHeader eyebrow={locale === 'my' ? 'ရှာဖွေမှုအခြေအနေ' : 'DISCOVERY STATUS'} title={locale === 'my' ? 'သင့်နေရာ' : 'Your space'} /><dl className="discovery-rail-stats"><div><dt>{locale === 'my' ? 'ရှာဖွေနိုင်သူ' : 'Discoverable people'}</dt><dd>{overview.counts.discoverable_people}</dd></div><div><dt>{locale === 'my' ? 'သင့်စိတ်ဝင်စားမှု' : 'Your interests'}</dt><dd>{overview.counts.my_interests}</dd></div><div><dt>{locale === 'my' ? 'ရနိုင်သောစိတ်ဝင်စားမှု' : 'Active interests'}</dt><dd>{overview.counts.active_interests}</dd></div></dl><p className="discovery-rail-note">{tab === 'for-you' ? (locale === 'my' ? 'တူညီသောအရာများမှ စတင်ပြီး သင့်အတွက်အရေးကြီးသော လူများကို ရှာပါ။' : 'Start with shared interests and find people who feel relevant.') : (locale === 'my' ? 'သင့် filters များသည် URL ထဲတွင် သိမ်းထားပြီး နောက်တစ်ကြိမ် ပြန်သုံးနိုင်ပါသည်။' : 'Your filters stay in the URL so you can return to the same view.')}</p></Card><Card className="discovery-rail-links"><Link to="/settings/discovery">{locale === 'my' ? 'Discovery settings' : 'Manage discovery settings'}<ArrowRight size={14} /></Link><Link to="/people">{locale === 'my' ? 'လူများအားလုံးကြည့်ရန်' : 'Open full people explorer'}<ArrowRight size={14} /></Link></Card></aside>
}

function UnavailableDiscovery({ kind, locale }: { kind: 'communities' | 'events'; locale: 'en' | 'my' }) {
  const community = kind === 'communities'
  return <Card className="discovery-unavailable"><span className="discovery-unavailable-icon">{community ? <Users size={25} /> : <CalendarDays size={25} />}</span><h2>{community ? (locale === 'my' ? 'အသိုင်းအဝိုင်း module မရရှိသေးပါ' : 'Communities are not available yet') : (locale === 'my' ? 'Events module မရရှိသေးပါ' : 'Events are not available yet')}</h2><p>{community ? (locale === 'my' ? 'ထုတ်ပြန်ပြီးသော community များ ရှိလာသောအခါ အမှန်တကယ် data ဖြင့် ဤနေရာတွင် ပြသပါမည်။' : 'Published communities will appear here when the community module is connected.') : (locale === 'my' ? 'Public events များ ရှိလာသောအခါ အမှန်တကယ် data ဖြင့် ဤနေရာတွင် ပြသပါမည်။' : 'Public events will appear here when the events module is connected.')}</p><Link className="ui-button ui-button-soft ui-button-sm" to={community ? '/communities' : '/events'}>{locale === 'my' ? 'Module ကိုကြည့်ရန်' : 'Open module'}<ArrowRight size={14} /></Link></Card>
}

function ContentSkeleton() { return <div className="discovery-content-skeleton">{Array.from({ length: 3 }, (_, index) => <Skeleton key={index} />)}</div> }
function ContentError({ onRetry, locale }: { onRetry: () => void; locale: 'en' | 'my' }) { return <Card className="discovery-error-card" role="alert"><h2>{locale === 'my' ? 'အကြောင်းအရာ မရနိုင်ပါ' : 'Content could not load'}</h2><p>{locale === 'my' ? 'ခဏနေပြီး ထပ်စမ်းပါ။' : 'Please try again in a moment.'}</p><Button variant="soft" onClick={onRetry}>{locale === 'my' ? 'ထပ်စမ်းရန်' : 'Try again'}</Button></Card> }
function ContentEmpty({ icon, title, copy }: { icon: ReactNode; title: string; copy: string }) { return <Card className="discovery-content-empty"><span>{icon}</span><h2>{title}</h2><p>{copy}</p></Card> }
