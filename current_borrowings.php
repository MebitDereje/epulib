<?php
/**
 * Current Borrowings Report Template
 */
$data = $report_data;
?>

<div class="report-header">
    <h2><i class="fas fa-book-reader"></i> Current Borrowings Report</h2>
    <p>All active borrowings as of <?php echo date('M j, Y g:i A'); ?></p>
</div>

<!-- Summary Statistics -->
<div class="report-summary">
    <div class="summary-card">
        <h3><?php echo $data['summary']['total']; ?></h3>
        <p>Total Active Borrowings</p>
    </div>
    <div class="summary-card">
        <h3 class="text-danger"><?php echo $data['summary']['overdue']; ?></h3>
        <p>Overdue Books</p>
    </div>
    <div class="summary-card">
        <h3 class="text-warning"><?php echo $data['summary']['due_soon']; ?></h3>
        <p>Due Soon (3 days)</p>
    </div>
    <div class="summary-card">
        <h3 class="text-success"><?php echo $data['summary']['normal']; ?></h3>
        <p>On Schedule</p>
    </div>
</div>

<div style="padding: 1.5rem;">
    <?php if (!empty($data['borrowings'])): ?>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Borrower</th>
                    <th>Contact</th>
                    <th>Book Details</th>
                    <th>Borrow Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Days</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['borrowings'] as $borrowing): ?>
                    <tr class="<?php echo $borrowing['status']; ?>-row">
                        <td>
                            <strong><?php echo htmlspecialchars($borrowing['full_name']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($borrowing['id_number']); ?></small><br>
                            <small class="text-muted"><?php echo htmlspecialchars($borrowing['department']); ?></small>
                        </td>
                        <td>
                            <?php if ($borrowing['phone']): ?>
                                <small><i class="fas fa-phone"></i> <?php echo htmlspecialchars($borrowing['phone']); ?></small><br>
                            <?php endif; ?>
                            <?php if ($borrowing['email']): ?>
                                <small><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($borrowing['email']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($borrowing['title']); ?></strong><br>
                            <small>by <?php echo htmlspecialchars($borrowing['author']); ?></small><br>
                            <small class="text-muted">ISBN: <?php echo htmlspecialchars($borrowing['isbn']); ?></small><br>
                            <span class="category-badge"><?php echo htmlspecialchars($borrowing['category_name']); ?></span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($borrowing['borrow_date'])); ?></td>
                        <td><?php echo date('M j, Y', strtotime($borrowing['due_date'])); ?></td>
                        <td>
                            <?php if ($borrowing['status'] === 'overdue'): ?>
                                <span class="status-overdue">Overdue</span>
                            <?php elseif ($borrowing['status'] === 'due_soon'): ?>
                                <span class="status-due-soon">Due Soon</span>
                            <?php else: ?>
                                <span class="status-normal">On Schedule</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($borrowing['days_overdue'] > 0): ?>
                                <span class="text-danger"><?php echo $borrowing['days_overdue']; ?> days overdue</span>
                            <?php elseif ($borrowing['days_overdue'] < 0): ?>
                                <span class="text-info"><?php echo abs($borrowing['days_overdue']); ?> days remaining</span>
                            <?php else: ?>
                                <span class="text-warning">Due today</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="text-center" style="padding: 3rem;">
            <i class="fas fa-check-circle fa-3x text-success"></i>
            <h3>No Active Borrowings</h3>
            <p class="text-muted">All books have been returned or no books are currently borrowed.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.overdue-row { background-color: #fff5f5; }
.due_soon-row { background-color: #fffbf0; }
.normal-row { background-color: #f8fff8; }

.category-badge {
    background: #e9ecef;
    color: #495057;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
}

.status-overdue { color: #dc3545; font-weight: 600; }
.status-due-soon { color: #ffc107; font-weight: 600; }
.status-normal { color: #28a745; font-weight: 600; }
</style>