import type { Backtest } from '@/types/app/Models/Backtest';

type Frequency = Backtest['rebalance_frequency'];
const weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
export const weekdayOptions = weekdays.map((name, i) => ({ id: i + 1, name }));
export const rebalanceMonthOptions = months.map((name, i) => ({ id: i + 1, name }));
const intervals = { quarterly: 3, six_monthly: 6, yearly: 12 } as const;

export function usesRebalanceMonth(frequency: Frequency): frequency is keyof typeof intervals {
    return Object.hasOwn(intervals, frequency);
}

export function rebalanceDescription(frequency: Frequency, day: number, month: number | null): string {
    const weekday = weekdays[day - 1] ?? `day ${day}`;
    if (frequency === 'weekly') return `Weekly · ${weekday}`;
    if (frequency === 'fortnightly') return `Every other ${weekday}`;
    if (frequency === 'monthly') return `Monthly day ${day}`;
    if (frequency === 'yearly') return month ? `Every year on ${day} ${months[month - 1]}` : 'Yearly';
    const interval = `Every ${intervals[frequency]} months`;
    return month ? `${interval} from ${day} ${months[month - 1]}` : interval;
}

export function suggestedRebalanceMonth(startDate: string, day: number): number {
    const [, month, startDay] = startDate.slice(0, 10).split('-').map(Number);
    if (!month) return 1;
    return day < startDay ? (month % 12) + 1 : month;
}

// Calendar target only. Actual decision and execution dates depend on the trading sessions in the engine.
export function firstRebalanceTarget(frequency: Frequency, startDate: string, day: number, month: number | null): string | null {
    const start = new Date(`${startDate.slice(0, 10)}T00:00:00Z`);
    if (!Number.isFinite(start.getTime()) || !Number.isInteger(day) || day < 1) return null;
    let target: Date;
    if (frequency === 'fortnightly' && day <= 5) {
        target = new Date(start);
        target.setUTCDate(start.getUTCDate() + ((day - start.getUTCDay() + 7) % 7));
    } else if (usesRebalanceMonth(frequency) && month && month >= 1 && month <= 12 && day <= 28) {
        target = new Date(Date.UTC(start.getUTCFullYear(), month - 1, day));
        if (target < start) target.setUTCFullYear(target.getUTCFullYear() + 1);
    } else {
        return null;
    }
    return target.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
}
