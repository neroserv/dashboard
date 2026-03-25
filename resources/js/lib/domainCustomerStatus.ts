/**
 * Customer-facing labels for reseller domain status (Skrime / Realtime Register).
 */
export function customerDomainStatusLabel(status: string): string {
  const raw = (status || '').trim()
  const s = raw.toLowerCase().replace(/_/g, ' ')
  if (s.includes('pending') && s.includes('valid')) {
    return 'Validierung ausstehend'
  }
  if (s === 'active' || s === 'aktiv') {
    return 'Aktiv'
  }
  if (s === 'expired' || s === 'abgelaufen') {
    return 'Abgelaufen'
  }
  if (s === 'cancelled') {
    return 'Gekündigt'
  }
  if (s === 'pending' || s === 'pendingfoa') {
    return 'Ausstehend'
  }
  return raw || '–'
}

export function customerDomainStatusBadgeVariant(status: string): 'success' | 'secondary' | 'danger' | 'warning' {
  const s = (status || '').toLowerCase().replace(/_/g, ' ')
  if (s.includes('pending') && s.includes('valid')) {
    return 'warning'
  }
  if (s === 'active' || s === 'aktiv') {
    return 'success'
  }
  if (s === 'expired' || s === 'abgelaufen' || s === 'cancelled') {
    return 'danger'
  }
  return 'secondary'
}
