// All application exports must use this filename contract.
export function buildAnalyticsExportFilename(from: string | null | undefined, to: string | null | undefined, now = new Date()): string {
    const timestamp = `${localDateKey(now)}_${String(now.getHours()).padStart(2, '0')}${String(now.getMinutes()).padStart(2, '0')}${String(now.getSeconds()).padStart(2, '0')}`

    return `analytics_${from ?? 'awal'}_sampai_${to ?? 'akhir'}_${timestamp}.xlsx`
}

function localDateKey(date: Date): string {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}
