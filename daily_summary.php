<?php
/**
 * Daily Summary Report Template
 */
$data = $report_data;
?>

<div class="report-header">
    <h2><i class="fas fa-calendar-day"></i> Daily Summary Report</h2>
    <p>Period: <?php echo date('M j, Y', strtotime($data['date_range']['from'])); ?> - <?php echo date('M j, Y', strtotime($data['date_range']['to'])); ?></p>
</div>

<!-- Current Status Summary -->
<div class="report-summary">
    <div class="summary-card">
        <h3><?php echo $data['current_status']['active_borrowings']; ?></h3>
        <p>Active Borrowings</p>
    </div>
    <div class="summary-card">
        <h3 class="text-danger"><?php echo $data['current_status']['overdue_books']; ?></h3>
        <p>Overdue Books</p>
    </div>
    <div class="summary-card">
        <h3 class="text-warning"><?php echo $data['current_status']['unpaid_fines']; ?></h3>
        <p>Unpaid Fines</p>
    </div>
    <div class="summary-card">
        <h3 class="text-info"><?php echo number_format($data['current_status']['total_unpaid_amount'], 2); ?> ETB</h3>
        <p>Total Unpaid Amount</p>
    </div>
</div>

<div style="padding: 1.5rem;">
    <!-- Daily Borrowings -->
    <div class="report-section">
        <h3><i class="fas fa-handshake"></i> Daily Borrowing Activity</h3>
        <?php if (!empty($data['daily_data'])): ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>New Borrowings</th>
                        <th>Same Day Returns</th>
                        <th>Net Borrowings</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['daily_data'] as $day): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($day['date'])); ?></td>
                            <td><?php echo $day['new_borrowings']; ?></td>
                            <td><?php echo $day['same_day_returns']; ?></td>
                            <td><?php echo $day['new_borrowings'] - $day['same_day_returns']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted">No borrowing activity found for the selected period.</p>
        <?php endif; ?>
    </div>

    <!-- Daily Returns -->
    <div class="report-section" style="margin-top: 2rem;">
        <h3><i class="fas fa-undo"></i> Daily Return Activity</h3>
        <?php if (!empty($data['returns_data'])): ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Total Returns</th>
                        <th>Late Returns</th>
                        <th>On-Time Returns</th>
                        <th>Late Return Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['returns_data'] as $day): ?>
                        <?php
                        $on_time = $day['returns'] - $day['late_returns'];
                        $late_rate = $day['returns'] > 0 ? ($day['late_returns'] / $day['returns']) * 100 : 0;
                        ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($day['date'])); ?></td>
                            <td><?php echo $day['returns']; ?></td>
                            <td class="<?php echo $day['late_returns'] > 0 ? 'text-danger' : ''; ?>">
                                <?php echo $day['late_returns']; ?>
                            </td>
                            <td class="text-success"><?php echo $on_time; ?></td>
                            <td class="<?php echo $late_rate > 20 ? 'text-danger' : ($late_rate > 10 ? 'text-warning' : 'text-success'); ?>">
                                <?php echo number_format($late_rate, 1); ?>%
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted">No return activity found for the selected period.</p>
        <?php endif; ?>
    </div>
</div>

<style>
.report-section {
    margin-bottom: 2rem;
}

.report-section h3 {
    color: #1e3c72;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e9ecef;
}

.text-danger { color: #dc3545 !important; }
.text-warning { color: #ffc107 !important; }
.text-success { color: #28a745 !important; }
.text-info { color: #17a2b8 !important; }
.text-muted { color: #6c757d !important; }
</style>