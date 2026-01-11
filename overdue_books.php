<?php
/**
 * Overdue Books Report Template
 */
$data = $report_data;
?>

<div class="report-header">
    <h2><i class="fas fa-exclamation-triangle"></i> Overdue Books Report</h2>
    <p>Books past their due date as of <?php echo date('M j, Y g:i A'); ?></p>
</div>

<!-- Summary Statistics -->
<div class="report-summary">
    <div class="summary-card">
        <h3 class="text-danger"><?php echo $data['total_count']; ?></h3>
        <p>Total Overdue Books</p>
    </div>
    <div class="summary-card">
        <h3 class="text-warning"><?php echo number_format($data['total_fine_amount'], 2); ?> ETB</h3>
        <p>Total Potential Fines</p>
    </div>
    <div class="summary-card">
        <h3 class="text-info"><?php echo $data['avg_days_overdue']; ?></h3>
        <p>Average Days Overdue</p>
    </div>
    <div class="summary-card">
        <h3 class="text-secondary">2.00 ETB</h3>
        <p>Fine Per Day</p>
    </div>
</div>

<div style="padding: 1.5rem;">
    <?php if (!empty($data['overdue_books'])): ?>
        <div class="alert alert-warning" style="margin-bottom: 1.5rem;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Action Required:</strong> The following books are overdue and require immediate attention. 
            Contact borrowers to arrange returns and process any applicable fines.
        </div>
        
        <table class="report-table">
            <thead>
                <tr>
                    <th>Borrower Information</th>
                    <th>Contact Details</th>
                    <th>Book Details</th>
                    <th>Due Date</th>
                    <th>Days Overdue</th>
                    <th>Potential Fine</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['overdue_books'] as $book): ?>
                    <tr class="overdue-row">
                        <td>
                            <strong><?php echo htmlspecialchars($book['full_name']); ?></strong><br>
                            <small class="text-muted">ID: <?php echo htmlspecialchars($book['id_number']); ?></small><br>
                            <small class="text-muted">Dept: <?php echo htmlspecialchars($book['department']); ?></small>
                        </td>
                        <td>
                            <?php if ($book['phone']): ?>
                                <small><i class="fas fa-phone"></i> <?php echo htmlspecialchars($book['phone']); ?></small><br>
                            <?php endif; ?>
                            <?php if ($book['email']): ?>
                                <small><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($book['email']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($book['title']); ?></strong><br>
                            <small>by <?php echo htmlspecialchars($book['author']); ?></small><br>
                            <small class="text-muted">ISBN: <?php echo htmlspecialchars($book['isbn']); ?></small><br>
                            <span class="category-badge"><?php echo htmlspecialchars($book['category_name']); ?></span>
                        </td>
                        <td>
                            <span class="text-danger">
                                <?php echo date('M j, Y', strtotime($book['due_date'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="days-overdue">
                                <strong><?php echo $book['days_overdue']; ?> days</strong>
                            </span>
                        </td>
                        <td>
                            <span class="fine-amount">
                                <strong><?php echo number_format($book['potential_fine'], 2); ?> ETB</strong>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Action Items -->
        <div class="action-items" style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
            <h4><i class="fas fa-tasks"></i> Recommended Actions</h4>
            <ul>
                <li><strong>Immediate Contact:</strong> Contact borrowers with books overdue more than 7 days</li>
                <li><strong>Fine Processing:</strong> Process fines for returned overdue books</li>
                <li><strong>Follow-up:</strong> Schedule follow-up for books overdue more than 14 days</li>
                <li><strong>Escalation:</strong> Consider escalation procedures for books overdue more than 30 days</li>
            </ul>
        </div>
        
    <?php else: ?>
        <div class="text-center" style="padding: 3rem;">
            <i class="fas fa-check-circle fa-3x text-success"></i>
            <h3>No Overdue Books</h3>
            <p class="text-muted">Excellent! All borrowed books are within their due dates.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.overdue-row {
    background-color: #fff5f5;
    border-left: 4px solid #dc3545;
}

.days-overdue {
    color: #dc3545;
    font-size: 1.1em;
}

.fine-amount {
    color: #dc3545;
    font-size: 1.1em;
}

.category-badge {
    background: #e9ecef;
    color: #495057;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
}

.action-items ul {
    margin: 0;
    padding-left: 1.5rem;
}

.action-items li {
    margin-bottom: 0.5rem;
}

.alert {
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid transparent;
}

.alert-warning {
    background-color: #fff3cd;
    border-color: #ffeaa7;
    color: #856404;
}
</style>