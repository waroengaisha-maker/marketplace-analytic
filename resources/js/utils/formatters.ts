const nominalFormatter = new Intl.NumberFormat('en-US', {
    maximumFractionDigits: 0,
})

export function formatNominal(value: number | string | null | undefined): string {
    const amount = Number(value ?? 0)

    return `Rp ${nominalFormatter.format(Number.isFinite(amount) ? amount : 0)}`
}
