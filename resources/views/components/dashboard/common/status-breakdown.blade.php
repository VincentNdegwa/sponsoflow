@props(['breakdown'])

@if (count($breakdown) > 0)
    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
        <flux:heading size="sm" class="mb-5">Booking Status Breakdown</flux:heading>
        <div
            wire:ignore
            x-data="{
                labels: @js(collect($breakdown)->pluck('label')),
                series: @js(collect($breakdown)->pluck('count')),
                colors: ['#22c55e', '#0ea5e9', '#6366f1', '#f97316', '#eab308', '#ef4444'],
                init() {
                    const options = {
                        chart: {
                            type: 'donut',
                            height: 220,
                            toolbar: { show: false },
                            background: 'transparent',
                            foreColor: this.isDark() ? '#e4e4e7' : '#3f3f46',
                        },
                        labels: this.labels,
                        series: this.series,
                        colors: this.colors,
                        legend: {
                            position: 'bottom',
                            fontSize: '12px',
                            labels: { colors: this.isDark() ? '#a1a1aa' : '#52525b' },
                        },
                        dataLabels: { enabled: false },
                        stroke: { width: 0 },
                        plotOptions: { pie: { donut: { size: '72%' } } },
                        theme: { mode: this.isDark() ? 'dark' : 'light' },
                        grid: { borderColor: this.isDark() ? '#3f3f46' : '#e4e4e7' },
                        tooltip: { theme: this.isDark() ? 'dark' : 'light' },
                    };

                    this.chart = new window.ApexCharts(this.$refs.statusChart, options);
                    this.chart.render();
                },
                isDark() {
                    return document.documentElement.classList.contains('dark');
                }
            }"
        >
            <div x-ref="statusChart"></div>
        </div>
    </div>
@endif

