/*
| Chart.js wrapped as an Alpine component.
|
| Chart.js is imported dynamically, so only pages with a chart pay for it, and
| it is registered once and shared.
|
| Colours come from the DaisyUI theme rather than being hard-coded, so both
| themes render legibly; the chart is rebuilt when the theme changes.
|
| Usage from Blade:
|
|     <div x-data="chart({ type: 'bar', data: @js($data), options: {...} })"
|          class="h-72">
|         <canvas x-ref="canvas"></canvas>
|     </div>
*/

let chartLibrary = null;

/** Load and register Chart.js once. */
async function library() {
    if (chartLibrary === null) {
        const module = await import('chart.js/auto');

        chartLibrary = module.default ?? module.Chart;
    }

    return chartLibrary;
}

/** Read a CSS custom property off the document, with a fallback. */
function token(name, fallback) {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    return value === '' ? fallback : value;
}

/**
 * Theme-aware palette. The first colours are DaisyUI's semantic tokens so the
 * charts match the rest of the interface, then a fixed spread for series that
 * need more than a handful.
 */
function palette() {
    return [
        token('--color-primary', '#4f46e5'),
        token('--color-success', '#16a34a'),
        token('--color-warning', '#d97706'),
        token('--color-error', '#dc2626'),
        token('--color-info', '#0284c7'),
        token('--color-secondary', '#7c3aed'),
        '#0891b2',
        '#65a30d',
        '#c026d3',
        '#ea580c',
    ];
}

export default function chart({ type = 'bar', data = {}, options = {}, currency = '' } = {}) {
    return {
        instance: null,

        async init() {
            await this.build();

            // Rebuild on theme change so the axes and grid stay readable.
            this.observer = new MutationObserver(() => this.build());
            this.observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['data-theme'],
            });

            // Livewire replaces the DOM on navigation; drop the instance with it.
            this.$el.addEventListener('livewire:navigating', () => this.destroy(), { once: true });
        },

        destroy() {
            this.observer?.disconnect();
            this.instance?.destroy();
            this.instance = null;
        },

        async build() {
            const Chart = await library();

            this.instance?.destroy();

            const colors = palette();
            const text = token('--color-base-content', '#1f2937');
            const grid = `color-mix(in oklch, ${text} 12%, transparent)`;

            // Give every dataset a colour unless it brought its own.
            const datasets = (data.datasets ?? []).map((dataset, index) => ({
                borderColor: colors[index % colors.length],
                backgroundColor: type === 'line'
                    ? `color-mix(in oklch, ${colors[index % colors.length]} 20%, transparent)`
                    : colors[index % colors.length],
                borderWidth: type === 'line' ? 2 : 0,
                fill: type === 'line',
                tension: 0.35,
                ...dataset,
            }));

            // Pie and doughnut colour by slice, not by series.
            if (['pie', 'doughnut'].includes(type) && datasets[0]) {
                datasets[0].backgroundColor = (data.labels ?? []).map((_, index) => colors[index % colors.length]);
                datasets[0].borderColor = token('--color-base-100', '#ffffff');
                datasets[0].borderWidth = 2;
            }

            const money = (value) => {
                const formatted = new Intl.NumberFormat(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }).format(value ?? 0);

                return currency === '' ? formatted : `${currency} ${formatted}`;
            };

            this.instance = new Chart(this.$refs.canvas, {
                type,
                data: { ...data, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            display: (datasets.length > 1) || ['pie', 'doughnut'].includes(type),
                            labels: { color: text, usePointStyle: true, boxWidth: 8 },
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const label = context.dataset.label ?? context.label ?? '';

                                    return `${label}: ${money(context.parsed.y ?? context.parsed)}`;
                                },
                            },
                        },
                    },
                    scales: ['pie', 'doughnut'].includes(type) ? {} : {
                        x: {
                            ticks: { color: text },
                            grid: { display: false },
                        },
                        y: {
                            ticks: {
                                color: text,
                                callback: (value) => Intl.NumberFormat(undefined, {
                                    notation: 'compact',
                                    maximumFractionDigits: 1,
                                }).format(value),
                            },
                            grid: { color: grid },
                            beginAtZero: true,
                        },
                    },
                    ...options,
                },
            });
        },
    };
}
