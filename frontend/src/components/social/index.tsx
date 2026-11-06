import { CalendarDays, Clock3, MapPin, MessageCircle, Users } from 'lucide-react'
import { Link } from 'react-router-dom'
import type { ReactNode } from 'react'
import type { Profile, User } from '../../types'
import { Avatar, Badge, Button, Card, InterestChip } from '../ui'

export function UserCard({ user, profile, action }: { user: User; profile?: Profile; action?: ReactNode }) {
  return <Card className="social-card user-card"><div className="social-card-top"><Avatar name={profile?.display_name ?? user.name} src={profile?.avatar_url} size="lg" /><Badge tone="success">Active</Badge></div><h3>{profile?.display_name ?? user.name}</h3><p className="social-handle">@{profile?.username ?? user.username}</p>{profile?.bio && <p className="social-description">{profile.bio}</p>}<div className="social-actions">{action ?? <Button variant="soft" size="sm" disabled>View profile</Button>}</div></Card>
}

export function ProfilePreviewCard({ profile, action }: { profile: Profile; action?: ReactNode }) { return <Card className="social-card user-card"><div className="social-card-top"><Avatar name={profile.display_name} src={profile.avatar_url} size="lg" /><Badge tone="primary">Profile</Badge></div><h3>{profile.display_name}</h3><p className="social-handle">@{profile.username}</p><p className="social-description">{profile.bio || 'Add an introduction to help people discover what you care about.'}</p><div className="chip-row">{profile.interests?.slice(0, 3).map((interest) => <InterestChip key={interest.id}>{interest.name}</InterestChip>)}</div>{action && <div className="social-actions">{action}</div>}</Card> }

export function ConnectionCard({ name, username, avatarUrl, sharedInterests = 0, action }: { name: string; username: string; avatarUrl?: string; sharedInterests?: number; action?: React.ReactNode }) { return <Card className="social-card connection-card"><Avatar name={name} src={avatarUrl} size="md" /><div className="social-card-content"><h3>{name}</h3><p className="social-handle">@{username}</p><p className="social-description">{sharedInterests ? `${sharedInterests} shared interests` : 'A future connection suggestion'}</p></div>{action ?? <Button variant="ghost" size="sm" disabled><MessageCircle size={15} /> Connect</Button>}</Card> }

export function CommunityCard({ name, description, members = 0 }: { name: string; description: string; members?: number }) { return <Card className="social-card community-card"><span className="community-icon"><Users size={20} /></span><h3>{name}</h3><p className="social-description">{description}</p><p className="social-meta"><Users size={14} /> {members ? `${members} members` : 'Community discovery coming soon'}</p></Card> }

export function EventCard({ name, date, location, description }: { name: string; date: string; location?: string; description: string }) { return <Card className="social-card event-card"><Badge tone="primary">Event</Badge><h3>{name}</h3><p className="social-description">{description}</p><p className="social-meta"><CalendarDays size={14} /> {date}</p>{location && <p className="social-meta"><MapPin size={14} /> {location}</p>}</Card> }

export function PostCard({ author, body, timestamp }: { author: string; body: string; timestamp: string }) { return <Card className="social-card post-card"><div className="post-author"><Avatar name={author} size="sm" /><div><h3>{author}</h3><p className="social-meta"><Clock3 size={13} /> {timestamp}</p></div></div><p className="post-body">{body}</p><Badge>Posts will appear when the feed is connected.</Badge></Card> }

export function ActivityCard({ title, description, timestamp }: { title: string; description: string; timestamp?: string }) { return <Card className="activity-card"><span className="activity-dot" /><div><h3>{title}</h3><p>{description}</p>{timestamp && <small>{timestamp}</small>}</div></Card> }

export function NotificationItem({ title, description, unread = false }: { title: string; description: string; unread?: boolean }) { return <Link className={`notification-item ${unread ? 'is-unread' : ''}`} to="/notifications"><span className="notification-dot" /><span><strong>{title}</strong><small>{description}</small></span></Link> }
