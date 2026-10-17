export const chartTimeRanges = [
    { key: '1m', description: '1 month', label: '1M', days: 22 },
    { key: '6m', description: '6 months', label: '6M', days: 126 },
    { key: '1y', description: '1 year', label: '1Yr', days: 252 },
    { key: '3y', description: '3 years', label: '3Yr', days: 756 },
    { key: '5y', description: '5 years', label: '5Yr', days: 1260 },
    { key: '10y', description: '10 years', label: '10Yr', days: 2520 },
    { key: 'max', description: 'all available history', label: 'Max', days: Infinity },
] as const;

export type ChartTimeRangeKey = (typeof chartTimeRanges)[number]['key'];
