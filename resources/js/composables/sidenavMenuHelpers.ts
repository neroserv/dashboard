import type { MenuItemType } from '@/types/paces'

export function pathnameWithoutQuery(url: string): string {
  const q = url.indexOf('?')
  return q >= 0 ? url.slice(0, q) : url
}

function isChildActiveForPath(children: MenuItemType[], path: string): boolean {
  return children.some(
    (child) =>
      (child.url && path.includes(child.url)) ||
      (child.children && isChildActiveForPath(child.children, path)),
  )
}

/**
 * Erstes Top-Level-Menü mit Kindern, dessen Route zur aktuellen URL passt (Haupt-Sidebar).
 */
export function findActiveTopLevelExpandableSlug(
  menuItems: MenuItemType[],
  pageUrl: string,
): string | null {
  const path = pathnameWithoutQuery(pageUrl)

  for (const item of menuItems) {
    const roots = item.children?.length ? item.children : [item]
    for (const child of roots) {
      if (child.children?.length && isChildActiveForPath(child.children, path)) {
        return child.slug
      }
    }
  }

  return null
}
