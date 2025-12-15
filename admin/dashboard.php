<?php
// dashboard.php
require('db.php'); 
require('xml_utils.php'); 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- SECURITY CHECK ---
if (!isset($_SESSION['admin_id'])) {
    if (isset($_COOKIE['admin_id'])) {
        $_SESSION['admin_id'] = $_COOKIE['admin_id'];
    } else {
        header('Location: login.php');
        exit();
    }
}
$admin_id = $_SESSION['admin_id'];

// --- FETCH ADMIN INFO ---
$admin_name = 'Admin'; 
if (isset($con) && $con !== false) {
    $stmt = $con->prepare("SELECT name FROM admins WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $admin_name = htmlspecialchars($row['name']);
    }
    if (isset($stmt)) $stmt->close();
}


// --------------------------------------------------------------------------
// --- FETCH DATA FOR CHARTS FROM XML FILE ---
// --------------------------------------------------------------------------

$chart_data = get_dashboard_chart_data();

$profit_data = $chart_data['profit'];
$items_sold_data = $chart_data['items_sold'];
$income_data = $chart_data['income'];
$stock_data = $chart_data['stock'];


// --- Convert PHP arrays to JSON for JavaScript consumption ---
$json_profit = json_encode($profit_data);
$json_sold = json_encode($items_sold_data);
$json_income = json_encode($income_data);
$json_stock = json_encode($stock_data);

// Load recent orders for 'Recent Activity' panel
$orders_xml = load_xml_file(ORDER_FILE);
$recent_orders = [];
if ($orders_xml !== false) {
    // Get the last 5 orders (by traversal order)
    $count = 0;
    foreach (array_reverse(iterator_to_array($orders_xml->order), true) as $order) {
        $recent_orders[] = [
            'id' => (string)$order['id'],
            'date' => (string)$order->order_date,
            'user_id' => (string)$order->user_id,
            'total' => (string)$order->total_amount,
        ];
        $count++;
        if ($count >= 5) break;
    }
}

// --------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Baked by the Crater</title>
    <link rel="stylesheet" href="admin_style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="dashboard-container">

        <aside class="sidebar">
            <div class="logo">
                <h3>Baked by the Crater</h3>
            </div>
            <nav class="nav-links">
                <a href="dashboard.php" class="active"><i class='bx bxs-dashboard'></i> Dashboard</a>
                <a href="products.php"><i class='bx bxs-box'></i> Products</a>
                <a href="orders.php"><i class='bx bxs-cart-alt'></i> Orders</a>
                <a href="users.php"><i class='bx bxs-group'></i> Users</a>
                <a href="chats.php"><i class='bx bxs-chat'></i> Chats</a> 
                <a href="settings.php"><i class='bx bxs-cog'></i> Settings</a>
            </nav>
            <a href="logout.php" class="logout-btn"><i class='bx bx-log-out'></i> Logout</a>
        </aside>

        <main class="main-content">
            
            <header class="header">
                <h2>Welcome back, <?= $admin_name; ?>!</h2>
                <div class="profile">
                    <i class='bx bxs-user-circle'></i>
                </div>
            </header>

            <div class="content-grid">
                
                <div class="card">
                    <h4>Monthly Profit Trend</h4>
                    <div class="chart-container">
                        <canvas id="profitChart"></canvas>
                    </div>
                </div>
                
                <div class="card">
                    <h4>Top 5 Items Sold (Quantity)</h4>
                    <div class="chart-container">
                        <canvas id="itemsSoldChart"></canvas>
                    </div>
                </div>

                <div class="card">
                    <h4>Quarterly Income</h4>
                    <div class="chart-container">
                        <canvas id="incomeChart"></canvas>
                    </div>
                </div>
                
                <div class="card">
                    <h4>Food Stock Breakdown</h4>
                    <div class="chart-container">
                        <canvas id="stockChart"></canvas>
                    </div>
                </div>
            </div>

            <section class="recent-activity">
                <h3>Recent Activity</h3>
                <div class="activity-box">
                    <?php if (empty($recent_orders)): ?>
                        <p>No recent orders available.</p>
                    <?php else: ?>
                        <table class="recent-orders">
                            <thead>
                                <tr><th>ID</th><th>Date</th><th>User</th><th>Total</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $ro): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ro['id']); ?></td>
                                        <td><?php echo htmlspecialchars($ro['date']); ?></td>
                                        <td><?php echo htmlspecialchars($ro['user_id']); ?></td>
                                        <td><?php echo format_currency($ro['total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>

        </main>

    </div> <script src="script.js"></script>

<script>
        // Get CSS variables for theming
        const getCssVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        const accent = getCssVar('--chart-color-1'); // Using chart colors from CSS
        const accentSoft = getCssVar('--chart-color-2');
        const textMuted = getCssVar('--text-muted');

        // ----------------------------------------------------------
        // Helper: safe hex -> rgba conversion and model initialization
        // ----------------------------------------------------------
        const hexToRgba = (hex, alpha = 1.0) => {
            try {
                const h = hex.replace('#','');
                const r = parseInt(h.substring(0,2), 16);
                const g = parseInt(h.substring(2,4), 16);
                const b = parseInt(h.substring(4,6), 16);
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            } catch (e) { return hex; }
        };

        const safeInitChart = (elementId, cfgFn) => {
            try {
                const el = document.getElementById(elementId);
                if (!el) return;
                const ctx = el.getContext('2d');
                const cfg = cfgFn();
                if (!cfg || !cfg.data) return;
                const chart = new Chart(ctx, cfg);
                return chart;
            } catch (e) {
                console.error('Chart init error for', elementId, e);
            }
        };

        const repeatColors = (count, colors) => {
            if (!Array.isArray(colors) || colors.length === 0) return [];
            const out = [];
            for (let i = 0; i < count; i++) {
                out.push(colors[i % colors.length]);
            }
            return out;
        };

        const showNoData = (elementId, message = 'No data') => {
            const el = document.getElementById(elementId);
            if (!el) return;
            const container = el.closest('.chart-container');
            if (!container) return;
            // remove previous overlay if exists
            const existing = container.querySelector('.no-data-overlay');
            if (existing) existing.remove();
            const overlay = document.createElement('div');
            overlay.className = 'no-data-overlay';
            overlay.style.position = 'absolute';
            overlay.style.top = '50%';
            overlay.style.left = '50%';
            overlay.style.transform = 'translate(-50%, -50%)';
            overlay.style.color = textMuted || '#bbb';
            overlay.style.fontSize = '0.95rem';
            overlay.style.pointerEvents = 'none';
            overlay.textContent = message;
            container.appendChild(overlay);
        };

        // ----------------------------------------------------------
        // 1. PROFIT (Line Graph)
        // ----------------------------------------------------------
        const profitData = <?= $json_profit; ?>;
        if (!profitData || !Array.isArray(profitData.data) || profitData.data.length === 0) {
            showNoData('profitChart', 'No data for profit');
        } else {
            safeInitChart('profitChart', () => ({
                type: 'line',
                data: {
                    labels: profitData.labels,
                    datasets: [{
                        label: 'Profit (\u20B1)',
                        data: profitData.data,
                        borderColor: accentSoft,
                        backgroundColor: hexToRgba(accentSoft, 0.12), // Subtle fill
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: accent
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: textMuted }, grid: { color: textMuted + '20' } },
                        y: { beginAtZero: true, ticks: { color: textMuted, callback: function(value) { return '\u20B1' + value; } }, grid: { color: textMuted + '20' } }
                    }
                }
            })); // <-- FIXED
        } // <-- FIXED

        // ----------------------------------------------------------
        // 2. ITEMS SOLD (Bar Graph)
        // ----------------------------------------------------------
        const itemsSoldData = <?= $json_sold; ?>;
        if (!itemsSoldData || !Array.isArray(itemsSoldData.data) || itemsSoldData.data.length === 0) {
            showNoData('itemsSoldChart', 'No items sold data');
        } else {
            safeInitChart('itemsSoldChart', () => ({
                type: 'bar',
                data: {
                    labels: itemsSoldData.labels,
                    datasets: [{
                        label: 'Items Sold',
                        data: itemsSoldData.data,
                        backgroundColor: repeatColors(itemsSoldData.data.length, [
                            accent,
                            getCssVar('--chart-color-3'),
                            accentSoft,
                            getCssVar('--chart-color-4'),
                            hexToRgba(accent, 0.5)
                        ]),
                        borderColor: accentSoft,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: textMuted }, grid: { display: false } },
                        y: { beginAtZero: true, ticks: { color: textMuted, precision: 0 }, grid: { color: textMuted + '20' } }
                    }
                }
            })); // <-- FIXED
        } // <-- FIXED

        // ----------------------------------------------------------
        // 3. INCOME (Bar Graph)
        // ----------------------------------------------------------
        const incomeData = <?= $json_income; ?>;
        if (!incomeData || !Array.isArray(incomeData.data) || incomeData.data.length === 0) {
            showNoData('incomeChart', 'No income data');
        } else {
            safeInitChart('incomeChart', () => ({
                type: 'bar',
                data: {
                    labels: incomeData.labels,
                    datasets: [{
                        label: 'Income (\u20B1)',
                        data: incomeData.data,
                        backgroundColor: hexToRgba(accent, 0.9),
                        borderColor: accentSoft,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: textMuted }, grid: { display: false } },
                        y: { beginAtZero: true, ticks: { color: textMuted, callback: function(value) { return '\u20B1' + (value / 1000) + 'k'; } }, grid: { color: textMuted + '20' } }
                    }
                }
            })); // <-- FIXED
        } // <-- FIXED

        // ----------------------------------------------------------
        // 4. FOOD STOCK (Pie Chart)
        // ----------------------------------------------------------
        const stockData = <?= $json_stock; ?>;
        if (!stockData || !Array.isArray(stockData.data) || stockData.data.length === 0) {
            showNoData('stockChart', 'No stock data');
        } else {
            safeInitChart('stockChart', () => ({
                type: 'pie',
                data: {
                    labels: stockData.labels,
                    datasets: [{
                        label: 'Stock Percentage',
                        data: stockData.data,
                        backgroundColor: repeatColors(stockData.data.length, [accent, accentSoft, getCssVar('--chart-color-3'), getCssVar('--chart-color-4')]),
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right', labels: { color: textMuted } },
                        title: { display: false }
                    }
                }
            })); // <-- FIXED
        } // <-- FIXED
    </script>

</body>
</html>