export type PersonalDashboard = { id: string; workspaceId: string; name: string; slug: string; widgets: { type: string; tagId: string; title: string }[] }
export type PersonalTag = { tagId: string; displayName: string | null; favorite: boolean }
