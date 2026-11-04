export type WorkspaceRole = 'viewer' | 'editor' | 'owner'
export type WorkspaceMembership = { id: string; name: string; role: WorkspaceRole }
export type Account = {
  authenticated: boolean
  user?: { id: string; email: string }
  workspaces: WorkspaceMembership[]
}
export type WorkspaceMember = { userId: string; email: string; role: WorkspaceRole }
