export interface PortfolioHoldingValue {
    id: number;
    symbol: string;
    name: string | null;
    series: string | null;
    quantity: number;
    price: number;
    close: number | null;
    cost: number;
    value: number | null;
    profitLoss: number | null;
    percentChange: number | null;
    priceDate: string | null;
    earlierPrice: boolean;
    updateUrl: string;
    destroyUrl: string;
}

export interface PortfolioValuation {
    date: string;
    marketDate: string | null;
    holdings: PortfolioHoldingValue[];
    summary: {
        cost: number;
        value: number;
        profitLoss: number | null;
        percentChange: number | null;
        stocks: number;
        lots: number;
        missingPrices: number;
        earlierPrices: number;
    };
}
