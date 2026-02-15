import type { Bar } from 'oakscriptjs';

export interface PriceHistoryRecord {
    date: string;
    open_adjusted: number;
    high_adjusted: number;
    low_adjusted: number;
    close_adjusted: number;
    volume_adjusted: number;
}

export function pricesToBars(prices: PriceHistoryRecord[]): Bar[] {
    return prices.map((p) => ({
        time: Math.floor(new Date(p.date).getTime() / 1000),
        open: Number(p.open_adjusted),
        high: Number(p.high_adjusted),
        low: Number(p.low_adjusted),
        close: Number(p.close_adjusted),
        volume: Number(p.volume_adjusted),
    }));
}

export function unixToDateString(unix: number): string {
    const d = new Date(unix * 1000);
    const year = d.getUTCFullYear();
    const month = String(d.getUTCMonth() + 1).padStart(2, '0');
    const day = String(d.getUTCDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}
