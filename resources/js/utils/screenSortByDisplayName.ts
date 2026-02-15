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
