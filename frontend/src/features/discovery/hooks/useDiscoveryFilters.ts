import { useEffect, useState } from 'react'
import { PEOPLE_PER_PAGE_OPTIONS, PEOPLE_SORTS, type PeopleFilters, type PeoplePerPage, type PeopleSort } from '../types'

/** General-purpose debounced value hook (used by search inputs). */
export function useDebouncedValue<T>(value: T, delay = 300): T {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const timer = window.setTimeout(() => setDebounced(value), delay)
    return () => window.clearTimeout(timer)
  }, [value, delay])
  return debounced
}

/** Default people filter state. */
export const DEFAULT_PEOPLE_FILTERS: PeopleFilters = { search: '', interest_ids: [], sort: 'relevance', page: 1, per_page: 10 }

/** URL <-> filter-state synchronization for /people. */
export function parsePeopleFilters(searchParams: URLSearchParams): PeopleFilters {
  const query = searchParams.get('q') ?? ''
  const interests = (searchParams.get('interests') ?? '')
    .split(',')
    .map((raw) => Number.parseInt(raw, 10))
    .filter((id) => Number.isInteger(id) && id > 0)
  const sortParam = searchParams.get('sort') as PeopleSort | null
  const perPageParam = Number.parseInt(searchParams.get('per_page') ?? '', 10) as PeoplePerPage
  const pageParam = Number.parseInt(searchParams.get('page') ?? '', 10)

  return {
    search: query,
    interest_ids: interests,
    sort: sortParam && PEOPLE_SORTS.includes(sortParam) ? sortParam : 'relevance',
    per_page: PEOPLE_PER_PAGE_OPTIONS.includes(perPageParam) ? perPageParam : 10,
    page: Number.isInteger(pageParam) && pageParam > 0 ? pageParam : 1,
  }
}

/** Only changed values are written to keep deep links clean. */
export function filtersToSearchParams(previous: URLSearchParams, filters: PeopleFilters): URLSearchParams {
  const next = new URLSearchParams(previous)
  next.delete('q'); next.delete('interests'); next.delete('sort'); next.delete('page'); next.delete('per_page')
  if (filters.search.trim()) next.set('q', filters.search.trim())
  if (filters.interest_ids.length) next.set('interests', filters.interest_ids.join(','))
  if (filters.sort !== 'relevance') next.set('sort', filters.sort)
  if (filters.page > 1) next.set('page', String(filters.page))
  if (filters.per_page !== 10) next.set('per_page', String(filters.per_page))
  return next
}
