export function screenSortByDisplayName(sortBy: string) {
    return sortBy
        .replace('twelve', '12')
        .replace('nine', '9')
        .replace('six', '6')
        .replace('three', '3')
        .replace('one', '1')
        .replaceAll('_', ' ')
        .toUpperCase();
}

const periodList = new Intl.ListFormat('en-GB', { type: 'conjunction' });

export function screenFactorLabel(key: string): string {
    const label = screenSortByDisplayName(key)
        .toLowerCase()
        .replace(/\btwo\b/g, '2')
        .replace(/((?:\d+ )+\d+) months$/, (_, periods: string) => `(${periodList.format(periods.split(' '))} months)`)
        .replace(/\b1 months\b/g, '1 month')
        .replace(/\bsharpe\b/g, 'Sharpe')
        .replace(/\brsi\b/g, 'RSI')
        .replace('divide beta return', 'return / beta')
        .replace('marketcap', 'market cap');
    return label.charAt(0).toUpperCase() + label.slice(1);
}
