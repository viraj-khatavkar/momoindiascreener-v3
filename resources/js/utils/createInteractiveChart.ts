import { createChart } from 'lightweight-charts';
import type { ChartOptions, DeepPartial, IChartApi } from 'lightweight-charts';

/** Keep page scrolling available unless the user holds a chart zoom modifier. */
export function createInteractiveChart(container: HTMLElement, options?: DeepPartial<ChartOptions>): IChartApi {
    const chart = createChart(container, options);
    chart.applyOptions({
        handleScroll: { mouseWheel: false, vertTouchDrag: false },
        handleScale: { mouseWheel: false },
    });

    let wheelEnabled = false;

    // Capture runs before the library's wheel handler. The listener belongs to
    // the chart element, so removing the chart also removes its event target.
    chart.chartElement().addEventListener(
        'wheel',
        (event: WheelEvent) => {
            const intentional = event.ctrlKey || event.metaKey;
            if (intentional !== wheelEnabled) {
                chart.applyOptions({
                    handleScroll: { mouseWheel: intentional },
                    handleScale: { mouseWheel: intentional },
                });
                wheelEnabled = intentional;
            }

            if (intentional && event.cancelable) {
                event.preventDefault();
            }
        },
        { capture: true, passive: false },
    );

    return chart;
}
