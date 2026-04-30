/* ============================================
   LCR DIGITAL ARCHIVING SYSTEM - DASHBOARD JS
   Municipality of San Julian, Eastern Samar
   ============================================ */

   $(document).ready(function () {

    function animateCount(selector, target) {
        const el = $(selector);
        let current = 0;
        const step = Math.max(1, Math.ceil(target / 40));
        const timer = setInterval(function () {
            current += step;
            if (current >= target) { current = target; clearInterval(timer); }
            el.text(current.toLocaleString());
        }, 30);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Counts ──
    $.ajax({
        url: 'dashboard_data.php?action=get_counts', type: 'GET', dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                animateCount('#count-birth',    res.data.birth);
                animateCount('#count-marriage', res.data.marriage);
                animateCount('#count-death',    res.data.death);
                animateCount('#count-users',    res.data.users);
            }
        }
    });

    // ── Recent Records ──
    $.ajax({
        url: 'dashboard_data.php?action=get_recent', type: 'GET', dataType: 'json',
        success: function (res) {
            const tbody = $('#recent-records-body');
            tbody.empty();
            if (res.status === 'success' && res.data.length > 0) {
                $.each(res.data, function (i, row) {
                    const badge = 'badge-' + row.type.toLowerCase();
                    const rdate = new Date(row.record_date).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' });
                    const cdate = new Date(row.created_at).toLocaleDateString('en-PH',  { year:'numeric', month:'short', day:'numeric' });
                    tbody.append(`<tr>
                        <td><span class="badge ${badge}">${row.type}</span></td>
                        <td>${escapeHtml(row.registry_number)}</td>
                        <td>${escapeHtml(row.name)}</td>
                        <td>${rdate}</td>
                        <td>${cdate}</td>
                    </tr>`);
                });
            } else {
                tbody.append('<tr><td colspan="5" style="text-align:center;color:#aaa;padding:20px;">No records found.</td></tr>');
            }
        }
    });

    // ── Audit Logs ──
    const auditContainer = $('#audit-log-list');
    if (auditContainer.length) {
        $.ajax({
            url: 'dashboard_data.php?action=get_audit', type: 'GET', dataType: 'json',
            success: function (res) {
                auditContainer.empty();
                if (res.status === 'success' && res.data.length > 0) {
                    $.each(res.data, function (i, log) {
                        const time = new Date(log.created_at).toLocaleString('en-PH');
                        auditContainer.append(`
                            <div class="audit-item">
                                <div class="audit-dot"></div>
                                <div>
                                    <div class="audit-action">${escapeHtml(log.action)}</div>
                                    <div class="audit-user">by ${escapeHtml(log.full_name || 'Unknown')} &bull; ${escapeHtml(log.ip_address || '')}</div>
                                    <div class="audit-time">${time}</div>
                                </div>
                            </div>`);
                    });
                } else {
                    auditContainer.html('<p style="font-size:0.8rem;color:#aaa;text-align:center;padding:10px;">No recent activity.</p>');
                }
            }
        });
    }

    // ── Bar Chart ──
    $.ajax({
        url: 'dashboard_data.php?action=get_chart', type: 'GET', dataType: 'json',
        success: function (res) {
            if (res.status !== 'success') return;
            const ctx = document.getElementById('recordsChart');
            if (!ctx) return;
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: res.data.labels,
                    datasets: [{
                        label: 'Total Records',
                        data: res.data.values,
                        backgroundColor: ['rgba(21,101,192,0.8)','rgba(106,27,154,0.8)','rgba(55,71,79,0.8)'],
                        borderColor:     ['rgba(21,101,192,1)',  'rgba(106,27,154,1)',  'rgba(55,71,79,1)'],
                        borderWidth: 2, borderRadius: 8,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: c => ' ' + c.parsed.y + ' records' } }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 11 } }, grid: { color: 'rgba(0,0,0,0.05)' } },
                        x: { ticks: { font: { size: 11 } }, grid: { display: false } }
                    }
                }
            });
        }
    });
});