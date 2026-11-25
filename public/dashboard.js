(function () {
    var el = document.getElementById('analytics-charts');
    if (!el || typeof Chart === 'undefined') { return; }

    var payload = JSON.parse(el.textContent);
    var dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    var grid = dark ? 'rgba(255,255,255,.12)' : 'rgba(0,0,0,.08)';
    var text = dark ? '#cbd5e1' : '#475569';

    document.querySelectorAll('canvas[data-chart]').forEach(function (canvas) {
        var chart = payload.charts[parseInt(canvas.getAttribute('data-chart'), 10)];
        if (!chart) { return; }

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: payload.labels,
                datasets: [{
                    label: chart.name,
                    data: chart.data,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,.12)',
                    fill: true,
                    tension: 0.25,
                    pointRadius: payload.labels.length > 60 ? 0 : 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: text, maxTicksLimit: 10 }, grid: { color: grid } },
                    y: { beginAtZero: true, ticks: { color: text, precision: 0 }, grid: { color: grid }, title: { display: true, text: 'Events', color: text } }
                }
            }
        });
    });
})();
