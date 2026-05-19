// main.js – Pilnas veikiantis kodas (Chart.js + viskas)
document.addEventListener('DOMContentLoaded', function () {
    // === 1. Pranešimų išnykimas ===
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.style.display = 'none', 500);
        }, 5000);
    });

    // === 2. Formų validacija ===
    document.querySelectorAll('form.needs-validation').forEach(form => {
        form.addEventListener('submit', e => {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // === 3. Lentelių rikiavimas ===
    document.querySelectorAll('table.sortable').forEach(table => {
        table.querySelectorAll('th.sortable').forEach((th, i) => {
            th.addEventListener('click', () => sortTable(table, i));
        });
    });

    // === 4. Paieška ===
    document.querySelectorAll('input.table-search').forEach(input => {
        input.addEventListener('keyup', () => {
            const table = document.getElementById(input.dataset.table);
            if (table) searchTable(table, input.value);
        });
    });

    // === 5. CHART.JS INICIALIZAVIMAS (100% VEIKIANTIS!) ===
    function initCharts() {
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js dar neįkeltas, laukiame...');
            setTimeout(initCharts, 100);
            return;
        }

        document.querySelectorAll('.chart-canvas').forEach(canvas => {
            const parent = canvas.parentElement;
            if (!parent) return;

            // Svarbu: PAŠALINTI HTML width/height atributus
            canvas.removeAttribute('width');
            canvas.removeAttribute('height');

            // Nustatyti per CSS
            canvas.style.width = '100%';
            canvas.style.height = '100%';

            // Nustatyti realų pikselių dydį (Chart.js reikia)
            canvas.width = parent.clientWidth;
            canvas.height = parent.clientHeight;

            // Gauti duomenis
            let labels = [], data = [], colors = [], title = '', label = '';
            try {
                labels = JSON.parse(canvas.dataset.labels || '[]');
                data = JSON.parse(canvas.dataset.data || '[]');
                colors = JSON.parse(canvas.dataset.colors || '[]');
                title = canvas.dataset.title || '';
                label = canvas.dataset.label || 'Duomenys';
            } catch (e) {
                console.error('JSON klaida:', e);
                parent.innerHTML = '<p class="text-muted text-center">Klaida</p>';
                return;
            }

            if (!labels.length || !data.length) {
                parent.innerHTML = '<p class="text-muted text-center">Nėra duomenų</p>';
                return;
            }

            // Sukurti diagramą
            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: data,
                        backgroundColor: colors,
                        borderColor: colors.map(c => c.replace(/[\d.]+(?=\)$)/, '1')),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: title, font: { size: 14 } }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });
        });
    }

    // Pradėti inicializavimą
    initCharts();
});

// === Lentelių rikiavimas ===
function sortTable(table, i) {
    const tbody = table.tBodies[0];
    const rows = Array.from(tbody.rows);
    const th = table.querySelectorAll('th.sortable')[i];
    const asc = th.classList.contains('asc');

    th.classList.remove('asc', 'desc');
    th.classList.add(asc ? 'desc' : 'asc');

    rows.sort((a, b) => {
        const aVal = a.cells[i].textContent.trim();
        const bVal = b.cells[i].textContent.trim();
        const aNum = parseFloat(aVal), bNum = parseFloat(bVal);

        if (!isNaN(aNum) && !isNaN(bNum)) {
            return asc ? bNum - aNum : aNum - bNum;
        }
        return asc ? bVal.localeCompare(aVal, 'lt') : aVal.localeCompare(bVal, 'lt');
    });

    rows.forEach(row => tbody.appendChild(row));
}

// === Paieška ===
function searchTable(table, term) {
    term = term.toLowerCase();
    table.tBodies[0].querySelectorAll('tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
}

// === Dalyvių valdymas ===
function addParticipant() {
    const container = document.getElementById('participants-container');
    const count = container.querySelectorAll('.participant-form').length;
    const template = document.getElementById('participant-template');
    if (!template || !container) return;

    const clone = template.content.cloneNode(true);
    clone.querySelectorAll('input, select, textarea').forEach(input => {
        const name = input.getAttribute('name');
        if (name) {
            input.name = name.replace('index', count);
            input.id = name.replace('index', count);
        }
    });
    clone.querySelectorAll('label').forEach(label => {
        const forAttr = label.getAttribute('for');
        if (forAttr) label.setAttribute('for', forAttr.replace('index', count));
    });
    container.appendChild(clone);
}

function removeParticipant(btn) {
    const form = btn.closest('.participant-form');
    if (form) form.remove();
}

// === Rezultatai ===
function calculateRanks() {
    const table = document.getElementById('results-table');
    if (!table) return;

    const rows = Array.from(table.tBodies[0].rows);
    rows.sort((a, b) => {
        const aScore = parseFloat(a.querySelector('input[name*="score"]').value) || 0;
        const bScore = parseFloat(b.querySelector('input[name*="score"]').value) || 0;
        return bScore - aScore;
    });

    let rank = 1, score = -1, same = 0;
    rows.forEach((row, i) => {
        const sIn = row.querySelector('input[name*="score"]');
        const rIn = row.querySelector('input[name*="rank"]');
        const s = parseFloat(sIn.value) || 0;
        if (s === 0) {
            rIn.value = '';
        } else if (s === score) {
            rIn.value = rank;
            same++;
        } else {
            rank = i + 1 - same;
            rIn.value = rank;
            score = s;
            same = 0;
        }
    });
}

// === Ataskaitos ===
function printReport() { window.print(); }
function exportToPDF() { alert('PDF eksportas bus įgyvendintas serveryje'); }
function exportToExcel() { alert('Excel eksportas bus įgyvendintas serveryje'); }