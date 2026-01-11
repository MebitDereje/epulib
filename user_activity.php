<?php
/**
 * User Activity Report Template
 */
$data = $report_data;
?>

<div class="report-header">
    <h2><i class="fas fa-users"></i> User Activity Report</h2>
    <p>User borrowing patterns from <?php echo date('M j, Y', strtotime($date_from)); ?> to <?php echo date('M j, Y', strtotime($date_to)); ?></p>
</div>

<div style="padding: 1.5rem;">
    <?php if (!empty($data)): ?>
        <!-- Summary Statistics -->
        <?php
        $total_users = count($data);
        $total_borrowings = array_sum(array_column($data, 'total_borrowings'));
        $total_current = array_sum(array_column($data, 'current_borrowings'));
        $total_late = array_sum(array_column($data, 'late_returns'));
        $users_with_fines = count(array_filter($data, fn($user) => $user['unpaid_fines'] > 0));
        $avg_borrowings = $total_users > 0 ? $total_borrowings / $total_users : 0;
        ?>
        
        <div class="report-summary" style="margin-bottom: 2rem;">
            <div class="summary-card">
                <h3><?php echo $total_users; ?></h3>
                <p>Active Users</p>
            </div>
            <div class="summary-card">
                <h3><?php echo $total_borrowings; ?></h3>
                <p>Total Borrowings</p>
            </div>
            <div class="summary-card">
                <h3><?php echo $total_current; ?></h3>
                <p>Currently Borrowed</p>
            </div>
            <div class="summary-card">
                <h3 class="text-warning"><?php echo $users_with_fines; ?></h3>
                <p>Users with Fines</p>
            </div>
        </div>
        
        <!-- Top Active Users -->
        <div class="top-users" style="margin-bottom: 2rem;">
            <h3><i class="fas fa-trophy"></i> Most Active Users</h3>
            <div class="top-users-grid">
                <?php 
                $top_users = array_slice($data, 0, 6);
                foreach ($top_users as $index => $user): 
                ?>
                    <div class="user-card rank-<?php echo $index + 1; ?>">
                        <div class="rank-badge">#<?php echo $index + 1; ?></div>
                        <div class="user-info">
                            <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                            <p class="user-id"><?php echo htmlspecialchars($user['id_number']); ?></p>
                            <p class="department"><?php echo htmlspecialchars($user['department']); ?></p>
                            <div class="user-stats">
                                <span class="borrowings"><?php echo $user['total_borrowings']; ?> books</span>
                                <?php if ($user['current_borrowings'] > 0): ?>
                                    <span class="current"><?php echo $user['current_borrowings']; ?> current</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Complete User List -->
        <div class="complete-list">
            <h3><i class="fas fa-list"></i> Complete User Activity</h3>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>User Information</th>
                        <th>Department</th>
                        <th>Role</th>
                        <th>Total Borrowings</th>
                        <th>Returned</th>
                        <th>Current</th>
                        <th>Late Returns</th>
                        <th>Avg. Period</th>
                        <th>Unpaid Fines</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $user): ?>
                        <tr class="<?php echo $user['unpaid_fines'] > 0 ? 'user-with-fines' : ''; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($user['id_number']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($user['department']); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="borrowing-count"><?php echo $user['total_borrowings']; ?></span>
                            </td>
                            <td><?php echo $user['returned_books']; ?></td>
                            <td>
                                <?php if ($user['current_borrowings'] > 0): ?>
                                    <span class="text-info"><?php echo $user['current_borrowings']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['late_returns'] > 0): ?>
                                    <span class="text-warning"><?php echo $user['late_returns']; ?></span>
                                <?php else: ?>
                                    <span class="text-success">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['avg_borrow_days']): ?>
                                    <?php echo round($user['avg_borrow_days'], 1); ?> days
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['unpaid_fines'] > 0): ?>
                                    <span class="fine-amount"><?php echo number_format($user['unpaid_fines'], 2); ?> ETB</span>
                                <?php else: ?>
                                    <span class="text-success">None</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Department Analysis -->
        <div class="department-analysis" style="margin-top: 2rem;">
            <h3><i class="fas fa-building"></i> Department Analysis</h3>
            <?php
            $dept_stats = [];
            foreach ($data as $user) {
                $dept = $user['department'];
                if (!isset($dept_stats[$dept])) {
                    $dept_stats[$dept] = [
                        'users' => 0,
                        'total_borrowings' => 0,
                        'current_borrowings' => 0,
                        'late_returns' => 0,
                        'unpaid_fines' => 0
                    ];
                }
                $dept_stats[$dept]['users']++;
                $dept_stats[$dept]['total_borrowings'] += $user['total_borrowings'];
                $dept_stats[$dept]['current_borrowings'] += $user['current_borrowings'];
                $dept_stats[$dept]['late_returns'] += $user['late_returns'];
                $dept_stats[$dept]['unpaid_fines'] += $user['unpaid_fines'];
            }
            ?>
            
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Active Users</th>
                        <th>Total Borrowings</th>
                        <th>Avg. per User</th>
                        <th>Current Borrowings</th>
                        <th>Late Returns</th>
                        <th>Total Unpaid Fines</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dept_stats as $dept => $stats): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($dept); ?></strong></td>
                            <td><?php echo $stats['users']; ?></td>
                            <td><?php echo $stats['total_borrowings']; ?></td>
                            <td><?php echo round($stats['total_borrowings'] / $stats['users'], 1); ?></td>
                            <td>
                                <?php if ($stats['current_borrowings'] > 0): ?>
                                    <span class="text-info"><?php echo $stats['current_borrowings']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($stats['late_returns'] > 0): ?>
                                    <span class="text-warning"><?php echo $stats['late_returns']; ?></span>
                                <?php else: ?>
                                    <span class="text-success">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($stats['unpaid_fines'] > 0): ?>
                                    <span class="fine-amount"><?php echo number_format($stats['unpaid_fines'], 2); ?> ETB</span>
                                <?php else: ?>
                                    <span class="text-success">None</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Insights -->
        <div class="insights" style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
            <h4><i class="fas fa-lightbulb"></i> User Activity Insights</h4>
            <ul>
                <li><strong>Average Activity:</strong> Users borrow an average of <?php echo round($avg_borrowings, 1); ?> books during this period</li>
                <li><strong>High Activity Users:</strong> <?php echo count(array_filter($data, fn($u) => $u['total_borrowings'] > $avg_borrowings * 2)); ?> users have significantly higher than average activity</li>
                <li><strong>Late Return Rate:</strong> <?php echo $total_borrowings > 0 ? round(($total_late / $total_borrowings) * 100, 1) : 0; ?>% of all borrowings result in late returns</li>
                <li><strong>Fine Collection:</strong> <?php echo round(($users_with_fines / $total_users) * 100, 1); ?>% of active users have unpaid fines</li>
            </ul>
        </div>
        
    <?php else: ?>
        <div class="text-center" style="padding: 3rem;">
            <i class="fas fa-users fa-3x text-muted"></i>
            <h3>No User Activity</h3>
            <p class="text-muted">No user activity found for the selected period.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.top-users-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.user-card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    position: relative;
    border-left: 4px solid #007bff;
}

.user-card.rank-1 { border-left-color: #ffd700; }
.user-card.rank-2 { border-left-color: #c0c0c0; }
.user-card.rank-3 { border-left-color: #cd7f32; }

.rank-badge {
    position: absolute;
    top: -10px;
    right: -10px;
    background: #007bff;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.9rem;
}

.rank-1 .rank-badge { background: #ffd700; color: #333; }
.rank-2 .rank-badge { background: #c0c0c0; color: #333; }
.rank-3 .rank-badge { background: #cd7f32; color: white; }

.user-card h4 {
    margin: 0 0 0.5rem 0;
    color: #1e3c72;
    font-size: 1.1rem;
}

.user-card .user-id {
    margin: 0 0 0.5rem 0;
    color: #6c757d;
    font-family: monospace;
}

.user-card .department {
    margin: 0 0 1rem 0;
    font-size: 0.9rem;
    color: #495057;
}

.user-card .user-stats {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
}

.borrowings {
    color: #007bff;
    font-weight: bold;
}

.current {
    color: #28a745;
}

.user-with-fines {
    background-color: #fff5f5;
    border-left: 4px solid #dc3545;
}

.role-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.role-student {
    background: #e3f2fd;
    color: #1976d2;
}

.role-staff {
    background: #f3e5f5;
    color: #7b1fa2;
}

.borrowing-count {
    font-weight: bold;
    color: #007bff;
}

.fine-amount {
    color: #dc3545;
    font-weight: bold;
}

.report-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.summary-card {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.summary-card h3 {
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
    color: #007bff;
}

.summary-card p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.insights ul {
    margin: 0;
    padding-left: 1.5rem;
}

.insights li {
    margin-bottom: 0.5rem;
}

@media (max-width: 768px) {
    .top-users-grid {
        grid-template-columns: 1fr;
    }
    
    .report-summary {
        grid-template-columns: 1fr;
    }
}
</style>