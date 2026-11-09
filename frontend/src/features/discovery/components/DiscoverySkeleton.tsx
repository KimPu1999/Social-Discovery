import { Card, Skeleton } from '../../../components/ui'

/** Loading skeletons for the people grid (used while TanStack Query fetches). */
export function DiscoverySkeleton({ count = 6 }: { count?: number }) {
  return (
    <div className="discovery-grid" aria-label="Loading people" role="status">
      {Array.from({ length: count }, (_, index) => (
        <Card key={index} className="discovery-card discovery-card-skeleton">
          <div className="discovery-card-head">
            <Skeleton className="ui-avatar ui-avatar-lg" />
            <div className="discovery-card-id">
              <Skeleton className="discovery-skeleton-line" />
              <Skeleton className="discovery-skeleton-line discovery-skeleton-line-sm" />
              <Skeleton className="discovery-skeleton-line discovery-skeleton-line-sm" />
            </div>
          </div>
          <div className="discovery-card-interests">
            <Skeleton className="discovery-skeleton-chip" />
            <Skeleton className="discovery-skeleton-chip" />
            <Skeleton className="discovery-skeleton-chip" />
          </div>
          <div className="discovery-card-actions">
            <Skeleton className="discovery-skeleton-chip" />
            <Skeleton className="discovery-skeleton-chip" />
          </div>
        </Card>
      ))}
    </div>
  )
}
