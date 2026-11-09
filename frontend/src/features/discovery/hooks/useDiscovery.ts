import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { discoveryApi } from '../api/discoveryApi'
import type { FeedbackType, PeopleFilters } from '../types'

/** Centralized query keys so invalidation after feedback stays precise. */
export const discoveryKeys = {
  all: ['discovery'] as const,
  overview: () => [...discoveryKeys.all, 'overview'] as const,
  people: (filters: PeopleFilters) => [...discoveryKeys.all, 'people', filters] as const,
  recommended: () => [...discoveryKeys.all, 'recommended'] as const,
  interests: () => [...discoveryKeys.all, 'interests'] as const,
  profile: (username: string) => [...discoveryKeys.all, 'profile', username] as const,
  preferences: () => [...discoveryKeys.all, 'preferences'] as const,
}

export function useDiscoveryOverview() {
  return useQuery({ queryKey: discoveryKeys.overview(), queryFn: discoveryApi.overview, staleTime: 30_000 })
}

export function useDiscoverPeople(filters: PeopleFilters) {
  return useQuery({
    queryKey: discoveryKeys.people(filters),
    queryFn: () => discoveryApi.people(filters),
    placeholderData: keepPreviousData,
    staleTime: 15_000,
  })
}

export function useRecommendedPeople(enabled = true) {
  return useQuery({
    queryKey: discoveryKeys.recommended(),
    queryFn: () => discoveryApi.recommended(8),
    enabled,
    staleTime: 30_000,
  })
}

export function useDiscoveryInterests(enabled = true) {
  return useQuery({
    queryKey: discoveryKeys.interests(),
    queryFn: discoveryApi.interests,
    enabled,
    staleTime: 60_000,
  })
}

export function useDiscoveryProfile(username: string) {
  return useQuery({
    queryKey: discoveryKeys.profile(username),
    queryFn: () => discoveryApi.profile(username),
    retry: (failureCount, error) => {
      const status = (error as { response?: { status?: number } }).response?.status
      // Privacy-safe 404s are terminal: no retry, no enumeration probing.
      return status !== 404 && failureCount < 2
    },
    staleTime: 15_000,
  })
}

export function useDiscoveryPreferences() {
  return useQuery({ queryKey: discoveryKeys.preferences(), queryFn: discoveryApi.preferences, staleTime: 30_000 })
}

/** Feedback mutations share one invalidation: every discovery read depends on hidden state. */
export function useDiscoveryFeedback() {
  const queryClient = useQueryClient()
  const onSuccess = () => queryClient.invalidateQueries({ queryKey: discoveryKeys.all })

  const give = useMutation({
    mutationFn: ({ targetUserId, feedbackType }: { targetUserId: number; feedbackType: FeedbackType }) =>
      discoveryApi.giveFeedback(targetUserId, feedbackType),
    onSuccess,
  })
  const undo = useMutation({
    mutationFn: (targetUserId: number) => discoveryApi.undoFeedback(targetUserId),
    onSuccess,
  })

  return { give, undo }
}

export function useUpdateDiscoveryPreferences() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: discoveryApi.updatePreferences,
    onSuccess: (preferences) => {
      queryClient.setQueryData(discoveryKeys.preferences(), preferences)
      void queryClient.invalidateQueries({ queryKey: discoveryKeys.all })
    },
  })
}
