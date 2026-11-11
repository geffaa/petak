const wholeNumber = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const oneDecimal = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });
const twoDecimals = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

export function formatNumber(value: number): string {
    return wholeNumber.format(value);
}

/** Rupiah in the short form used in Indonesian listings: "Rp 4,5 jt", "Rp 2,31 M". */
export function formatRupiah(value: number): string {
    if (value >= 1_000_000_000_000) {
        return `Rp ${twoDecimals.format(value / 1_000_000_000_000)} T`;
    }

    if (value >= 1_000_000_000) {
        return `Rp ${twoDecimals.format(value / 1_000_000_000)} M`;
    }

    if (value >= 1_000_000) {
        return `Rp ${twoDecimals.format(value / 1_000_000)} jt`;
    }

    return `Rp ${wholeNumber.format(value)}`;
}

export function formatArea(squareMeters: number): string {
    return `${wholeNumber.format(Math.round(squareMeters))} m²`;
}

export function formatHectares(squareMeters: number): string {
    return `${oneDecimal.format(squareMeters / 10_000)} ha`;
}

export function formatDistance(meters: number): string {
    return meters < 1000 ? `${wholeNumber.format(Math.round(meters))} m` : `${oneDecimal.format(meters / 1000)} km`;
}
