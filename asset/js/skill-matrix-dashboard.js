// Skill matrix dashboard skillMatrixCharts, shared by admin/skill-matrix/skill-matrix.php and
// staff/hod/skill-matrix/skill-matrix.php. Needs jQuery and Chart.js 2.9, and draws
// into the dashboard-* elements both pages lay out the same way.

var STATUS_ORDER = ['NOT SUBMITTED', 'DRAFT', 'WAITING APPROVAL', 'APPROVED'];
var STATUS_COLORS = {
    'NOT SUBMITTED': '#d03b3b',
    'DRAFT': '#ec835a',
    'WAITING APPROVAL': '#fab219',
    'APPROVED': '#0ca30c'
};
var skillMatrixCharts = { status: null, section: null, plant: null, scoreGroup: null, below50: null, scorePlant: null, scoreQuarter: null };

// Same bands and rounding-down as matrix-chart.php (matrixPieLevel).
var SCORE_LEVELS = [
    { min: 100, label: 'Highly Skilled' },
    { min: 75, label: 'Competent' },
    { min: 50, label: 'Medium Competency' },
    { min: 25, label: 'Novice' },
    { min: 0, label: 'Minimal Competency' }
];
// One colour per bar, cycled when there are more bars than colours.
var SCORE_BAR_COLORS = [
    '#4e79a7', '#f28e2b', '#59a14f', '#e15759', '#76b7b2',
    '#edc948', '#b07aa1', '#ff9da7', '#9c755f', '#bab0ac',
    '#1f77b4', '#17becf', '#8c564b', '#bcbd22', '#7f7f7f',
    '#2ca02c', '#9467bd', '#d62728', '#ff7f0e', '#e377c2'
];

// Splits a long name over several lines so the x-axis labels stay readable.
function wrapChartLabel(text, maxChars) {
    var lines = [];
    var line = '';
    text.split(' ').forEach(function (word) {
        if (line && (line + ' ' + word).length > maxChars) {
            lines.push(line);
            line = word;
        } else {
            line = line ? line + ' ' + word : word;
        }
    });
    if (line) {
        lines.push(line);
    }
    return lines;
}

function scoreLevelLabel(score) {
    for (var i = 0; i < SCORE_LEVELS.length; i++) {
        if (score >= SCORE_LEVELS[i].min) {
            return SCORE_LEVELS[i].label;
        }
    }
    return SCORE_LEVELS[SCORE_LEVELS.length - 1].label;
}

// Average overall_score per group, ignoring staff without an approved matrix.
function buildScoreGroups(rows, keyFn) {
    var groups = {};
    rows.forEach(function (r) {
        if (r.overall_score === null || r.overall_score === undefined) {
            return;
        }
        var key = (keyFn(r) || '').toString().trim() || '(Unspecified)';
        if (!groups[key]) {
            groups[key] = { sum: 0, count: 0 };
        }
        groups[key].sum += parseFloat(r.overall_score);
        groups[key].count++;
    });
    $.each(groups, function (key, g) {
        g.average = g.sum / g.count;
    });
    return groups;
}

// Writes the average on top of each bar (or the chart's own options.valueLabel text).
var scoreValueLabelPlugin = {
    afterDatasetsDraw: function (chart) {
        var ctx = chart.ctx;
        var meta = chart.getDatasetMeta(0);
        ctx.save();
        ctx.font = 'bold 11px sans-serif';
        ctx.fillStyle = '#333';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';
        meta.data.forEach(function (bar, index) {
            var value = chart.data.datasets[0].data[index];
            if (value === null) {
                ctx.fillStyle = '#999';
                ctx.fillText('No result', bar._model.x, chart.chartArea.bottom - 3);
                ctx.fillStyle = '#333';
                return;
            }
            var text = chart.options.valueLabel ? chart.options.valueLabel(value) : Math.round(value) + '%';
            ctx.fillText(text, bar._model.x, bar._model.y - 3);
        });
        ctx.restore();
    }
};

// Staff below 50% per group: { name: { below, novice, minimal, approved } },
// only groups that have at least one.
function buildBelow50Groups(rows, keyFn) {
    var groups = {};
    rows.forEach(function (r) {
        if (r.overall_score === null || r.overall_score === undefined) {
            return;
        }
        var key = (keyFn(r) || '').toString().trim() || '(Unspecified)';
        if (!groups[key]) {
            groups[key] = { below: 0, novice: 0, minimal: 0, approved: 0 };
        }
        var score = parseFloat(r.overall_score);
        groups[key].approved++;
        if (score < 50) {
            groups[key].below++;
            groups[key][score >= 25 ? 'novice' : 'minimal']++;
        }
    });
    $.each(groups, function (key, g) {
        if (g.below === 0) {
            delete groups[key];
        }
    });
    return groups;
}

function updateBelow50Chart(canvas, emptyEl, groups) {
    if (!canvas) {
        return;
    }
    var groupNames = Object.keys(groups).sort(function (a, b) {
        return groups[b].below - groups[a].below;
    });

    if (groupNames.length === 0) {
        $(emptyEl).show();
        $(canvas).hide();
        if (skillMatrixCharts.below50) {
            skillMatrixCharts.below50.destroy();
            skillMatrixCharts.below50 = null;
        }
        return;
    }
    $(emptyEl).hide();
    $(canvas).show();

    var chartData = {
        labels: groupNames.map(function (name) { return wrapChartLabel(name, 12); }),
        datasets: [{
            label: 'Staff Below 50%',
            backgroundColor: groupNames.map(function (name, index) {
                return SCORE_BAR_COLORS[index % SCORE_BAR_COLORS.length];
            }),
            data: groupNames.map(function (name) { return groups[name].below; })
        }]
    };
    var chartOptions = {
        maintainAspectRatio: false,
        legend: { display: false },
        layout: { padding: { top: 20 } },
        plugins: { labels: false },
        valueLabel: function (value) { return value; },
        tooltips: {
            callbacks: {
                title: function (tooltipItems) {
                    return groupNames[tooltipItems[0].index];
                },
                label: function (tooltipItem) {
                    var group = groups[groupNames[tooltipItem.index]];
                    return group.below + ' of ' + group.approved + ' approved staff below 50% (' +
                        Math.round(group.below / group.approved * 100) + '%)';
                },
                afterLabel: function (tooltipItem) {
                    var group = groups[groupNames[tooltipItem.index]];
                    return 'Novice: ' + group.novice + ', Minimal Competency: ' + group.minimal;
                }
            }
        },
        scales: {
            xAxes: [{
                barPercentage: 0.7,
                maxBarThickness: 60,
                gridLines: { display: false },
                ticks: { autoSkip: false, maxRotation: 0, fontSize: 10 }
            }],
            yAxes: [{
                ticks: { beginAtZero: true, precision: 0 }
            }]
        }
    };

    if (skillMatrixCharts.below50) {
        skillMatrixCharts.below50.data = chartData;
        skillMatrixCharts.below50.options = chartOptions;
        skillMatrixCharts.below50.update();
    } else {
        skillMatrixCharts.below50 = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: chartData,
            options: chartOptions,
            plugins: [scoreValueLabelPlugin]
        });
    }
}

// groups: { name: { average, count } }, average null for a group with no result.
// Bars are sorted highest first unless groupNames gives the order.
function updateScoreChart(chartKey, canvas, emptyEl, groups, groupNames) {
    // A page may leave a chart out (the HOD page has no score-by-plant chart).
    if (!canvas) {
        return;
    }
    groupNames = groupNames || Object.keys(groups).sort(function (a, b) {
        return groups[b].average - groups[a].average;
    });

    if (groupNames.length === 0) {
        $(emptyEl).show();
        $(canvas).hide();
        if (skillMatrixCharts[chartKey]) {
            skillMatrixCharts[chartKey].destroy();
            skillMatrixCharts[chartKey] = null;
        }
        return;
    }
    $(emptyEl).hide();
    $(canvas).show();

    var chartData = {
        labels: groupNames.map(function (name) { return wrapChartLabel(name, 12); }),
        datasets: [{
            label: 'Average Skill Score',
            backgroundColor: groupNames.map(function (name, index) {
                return SCORE_BAR_COLORS[index % SCORE_BAR_COLORS.length];
            }),
            data: groupNames.map(function (name) {
                return groups[name].average === null ? null : Math.round(groups[name].average * 100) / 100;
            })
        }]
    };
    var chartOptions = {
        maintainAspectRatio: false,
        legend: { display: false },
        // Room above the bars for the % labels on scores near 100%.
        layout: { padding: { top: 20 } },
        // chartjs-plugin-labels also draws on 'bar' skillMatrixCharts; this chart has its own labels.
        plugins: { labels: false },
        tooltips: {
            callbacks: {
                title: function (tooltipItems) {
                    return groupNames[tooltipItems[0].index];
                },
                label: function (tooltipItem) {
                    var group = groups[groupNames[tooltipItem.index]];
                    if (group.average === null) {
                        return 'No result';
                    }
                    return 'Average: ' + group.average.toFixed(1) + '% (' + scoreLevelLabel(group.average) + ')';
                },
                afterLabel: function (tooltipItem) {
                    var count = groups[groupNames[tooltipItem.index]].count;
                    return count + ' approved matri' + (count == 1 ? 'x' : 'ces');
                }
            }
        },
        scales: {
            xAxes: [{
                barPercentage: 0.7,
                maxBarThickness: 60,
                gridLines: { display: false },
                ticks: { autoSkip: false, maxRotation: 0, fontSize: 10 }
            }],
            yAxes: [{
                ticks: {
                    min: 0,
                    max: 100,
                    stepSize: 25,
                    callback: function (value) { return value + '%'; }
                }
            }]
        }
    };

    if (skillMatrixCharts[chartKey]) {
        skillMatrixCharts[chartKey].data = chartData;
        skillMatrixCharts[chartKey].options = chartOptions;
        skillMatrixCharts[chartKey].update();
    } else {
        skillMatrixCharts[chartKey] = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: chartData,
            options: chartOptions,
            plugins: [scoreValueLabelPlugin]
        });
    }
}

function emptyStatusCounts() {
    return { 'NOT SUBMITTED': 0, 'DRAFT': 0, 'WAITING APPROVAL': 0, 'APPROVED': 0, total: 0 };
}

function buildGroups(rows, keyFn) {
    var groups = {};
    rows.forEach(function (r) {
        var key = (keyFn(r) || '').toString().trim() || '(Unspecified)';
        if (!groups[key]) {
            groups[key] = emptyStatusCounts();
        }
        if (groups[key].hasOwnProperty(r.approval_status_raw)) {
            groups[key][r.approval_status_raw]++;
            groups[key].total++;
        }
    });
    return groups;
}

function sortedGroupNames(groups) {
    return Object.keys(groups).sort(function (a, b) {
        return groups[b].total - groups[a].total;
    });
}

function updateStackedBarChart(chartKey, canvas, emptyEl, groupNames, groups, fixedHeight) {
    if (!canvas) {
        return;
    }
    if (groupNames.length === 0) {
        $(emptyEl).show();
        $(canvas).hide();
        if (skillMatrixCharts[chartKey]) {
            skillMatrixCharts[chartKey].destroy();
            skillMatrixCharts[chartKey] = null;
        }
        return;
    }
    $(emptyEl).hide();
    $(canvas).show();
    canvas.parentElement.style.height = (fixedHeight || Math.max(140, groupNames.length * 26 + 40)) + 'px';

    var datasets = STATUS_ORDER.map(function (status) {
        return {
            label: status,
            backgroundColor: STATUS_COLORS[status],
            data: groupNames.map(function (name) { return groups[name][status]; })
        };
    });

    var chartData = { labels: groupNames, datasets: datasets };
    var chartOptions = {
        maintainAspectRatio: false,
        legend: { position: 'top', labels: { boxWidth: 14 } },
        tooltips: {
            mode: 'index',
            callbacks: {
                label: function (tooltipItem, data) {
                    var dataset = data.datasets[tooltipItem.datasetIndex];
                    return dataset.label + ': ' + dataset.data[tooltipItem.index];
                }
            }
        },
        scales: {
            xAxes: [{ stacked: true, ticks: { beginAtZero: true, precision: 0 } }],
            yAxes: [{ stacked: true }]
        }
    };

    if (skillMatrixCharts[chartKey]) {
        skillMatrixCharts[chartKey].data = chartData;
        skillMatrixCharts[chartKey].options = chartOptions;
        skillMatrixCharts[chartKey].update();
    } else {
        skillMatrixCharts[chartKey] = new Chart(canvas.getContext('2d'), {
            type: 'horizontalBar',
            data: chartData,
            options: chartOptions
        });
    }
}

// Draws every chart except the quarter one. rows: one per staff with department,
// section, plant, approval_status_raw and overall_score (null unless approved).
// byDepartment false means a single department is in view: the skillMatrixCharts group by
// section and the Status by Department row is hidden, since it would only repeat
// the Status by Section chart.
function renderSkillMatrixCharts(rows, byDepartment) {
    rows = rows || [];
    var groupKey = byDepartment
        ? function (r) { return r.department; }
        : function (r) { return r.section; };
    var groupLabel = byDepartment ? 'Department' : 'Section';

    // ===== Status by Department =====
    $('#dashboard-chart-row').toggle(byDepartment);
    if (byDepartment) {
        var deptGroups = buildGroups(rows, function (r) { return r.department; });
        updateStackedBarChart(
            'status',
            document.getElementById('dashboard-status-chart'),
            '#dashboard-empty-state',
            sortedGroupNames(deptGroups),
            deptGroups
        );
    }

    // ===== Status by Section =====
    var sectionGroups = buildGroups(rows, function (r) { return r.section; });
    updateStackedBarChart(
        'section',
        document.getElementById('dashboard-section-chart'),
        '#dashboard-section-empty',
        sortedGroupNames(sectionGroups),
        sectionGroups
    );

    // ===== Status by Plant =====
    var plantGroups = buildGroups(rows, function (r) { return r.plant; });
    updateStackedBarChart(
        'plant',
        document.getElementById('dashboard-plant-chart'),
        '#dashboard-plant-empty',
        sortedGroupNames(plantGroups),
        plantGroups
    );

    // ===== Average Skill Score by Department / Section =====
    $('#dashboard-score-group-title').text('Average Skill Score by ' + groupLabel);
    updateScoreChart(
        'scoreGroup',
        document.getElementById('dashboard-score-group-chart'),
        '#dashboard-score-group-empty',
        buildScoreGroups(rows, groupKey)
    );

    // ===== Staff Below 50% by Department / Section =====
    $('#dashboard-below50-title').text('Staff Below 50% by ' + groupLabel);
    updateBelow50Chart(
        document.getElementById('dashboard-below50-chart'),
        '#dashboard-below50-empty',
        buildBelow50Groups(rows, groupKey)
    );

    // ===== Average Skill Score by Plant =====
    updateScoreChart(
        'scorePlant',
        document.getElementById('dashboard-score-plant-chart'),
        '#dashboard-score-plant-empty',
        buildScoreGroups(rows, function (r) { return r.plant; })
    );
}

// ===== Average Skill Score by Quarter =====
// quarters: [{ label, average, count }] in time order, average null for a quarter
// with nothing approved.
function renderSkillMatrixQuarterChart(quarters) {
    var groups = {};
    var quarterNames = [];
    $.each(quarters || [], function (i, q) {
        groups[q.label] = { average: q.average, count: q.count };
        quarterNames.push(q.label);
    });
    updateScoreChart(
        'scoreQuarter',
        document.getElementById('dashboard-score-quarter-chart'),
        '#dashboard-score-quarter-empty',
        groups,
        quarterNames
    );
}

// Charts drawn while their tab was hidden have no width; call once it is shown.
function resizeSkillMatrixCharts() {
    $.each(skillMatrixCharts, function (key, chart) {
        if (chart) {
            chart.resize();
        }
    });
}
