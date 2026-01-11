<?php
/**
 * Fines Summary Report Template
 */
$data = $report_data;
?>

<div class="report-header">
    <h2><i class="fas fa-money-bill-wave"></i> Fines Summary Report</h2>
    <p>Fine collection overview from <?php echo date('M j, Y', strtotime($date_from)); ?> to <?php echo date('M j, Y', strtotime($date_to)); ?></p>
</div>

<!-- Summary Statistics -->
<div class="report-summary">
    <div class="summary-card">
        <h3><?php echo $data['summary']['total_fines']; ?></h3>
        <p>Total Fines Generated</p>
    </div>
    <div class="summary-card">
        <h3 class="text-success"><?php echo $data['summary']['paid_fines']; ?></h3>
        <p>Paid Fines</p>
    </div>
    <div class="summary-card">
        <h3 class="text-warning"><?php echo $data['summary']['waived_fines']; ?></h3>
        <p>Waived Fines</p>
    </div>
    <div class="summary-card">
        <h3 class="text-danger"><?php echo $data['summary']['unpaid_fines']; ?></h3>
        <p>Unpaid Fines</p>
    </div>
</div>

<div style="padding: 1.5rem;">
    <!-- Financial Summary -->
    <div class="financial-summary" style="margin-bottom: 2rem;">
        <div class="financial-grid">
            <div class="financial-card total">
                <h4>Total Amount</h4>
                <p class="amount"><?php echo number_format($data['summary']['total_amount'], 2); ?> ETB</p>
            </div>
            <div class="financial-card collected">
                <h4>Amount Collected</h4>
                <p class="amount text-success"><?php echo number_format($data['summary']['paid_amount'], 2); ?> ETB</p>
            </div>
            <div class="financial-card outstanding">
                <h4>Outstanding Amount</h4>
                <p class="amount text-danger"><?php echo number_format($data['summary']['unpaid_amount'], 2); ?> ETB</p>
            </div>
            <div class="financial-card rate">
                <h4>Collection Rate</h4>
                <p class="amount <?php echo $data['summary']['collection_rate'] >= 80 ? 'text-success' : ($data['summary']['collection_rate'] >= 60 ? 'text-warning' : 'text-danger'); ?>">
                    <?php echo round($data['summary']['collection_rate'], 1); ?>%
                </p>
            </div>
        </div>
    </div>
    
    <?php if (!empty($data['fines'])): ?>
        <!-- Fines List -->
        <div class="fines-list">
            <h3><i class="fas fa-list"></i> Detailed Fines List</h3>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>User Information</th>
                        <th>Book Details</th>
                        <th>Due Date</th>
                        <th>Return Date</th>
                        <th>Days Overdue</th>
                        <th>Fine Amount</th>
                        <th>Status</th>
                        <th>Payment Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['fines'] as $fine): ?>
                        <tr class="fine-<?php echo $fine['payment_status']; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($fine['full_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($fine['id_number']); ?></small><br>
                                <small class="text-muted"><?php echo htmlspecialchars($fine['department']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($fine['title']); ?></strong><br>
                                <small>by <?php echo htmlspecialchars($fine['author']); ?></small>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($fine['due_date'])); ?></td>
                            <td><?php echo date('M j, Y', strtotime($fine['return_date'])); ?></td>
                            <td>
                                <span class="days-overdue"><?php echo $fine['days_overdue']; ?> days</span>
                            </td>
                            <td>
                                <span class="fine-amount"><?php echo number_format($fine['fine_amount'], 2); ?> ETB</span>
                            </td>
                            <td>
                                <?php if ($fine['payment_status'] === 'paid'): ?>
                                    <span class="status-badge status-paid">Paid</span>
                                <?php elseif ($fine['payment_status'] === 'waived'): ?>
                                    <span class="status-badge status-waived">Waived</span>
                                <?php else: ?>
                                    <span class="status-badge status-unpaid">Unpaid</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($fine['payment_status'] === 'paid'): ?>
                                    <small>
                                        <strong>Date:</strong> <?php echo date('M j, Y', strtotime($fine['payment_date'])); ?><br>
                                        <strong>Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $fine['payment_method'])); ?>
                                    </small>
                                <?php elseif ($fine['payment_status'] === 'waived'): ?>
                                    <small class="text-muted">
                                        Waived on <?php echo date('M j, Y', strtotime($fine['payment_date'])); ?>
                                    </small>
                                <?php else: ?>
                                    <small class="text-danger">Payment pending</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Payment Method Analysis -->
        <div class="payment-analysis" style="margin-top: 2rem;">
            <h3><i class="fas fa-chart-pie"></i> Payment Method Analysis</h3>
            <?php
            $payment_methods = [];
            foreach ($data['fines'] as $fine) {
                if ($fine['payment_status'] === 'paid' && $fine['payment_method']) {
                    $method = $fine['payment_method'];
                    if (!isset($payment_methods[$method])) {
                        $payment_methods[$method] = ['count' => 0, 'amount' => 0];
                    }
                    $payment_methods[$method]['count']++;
                    $payment_methods[$method]['amount'] += $fine['fine_amount'];
                }
            }
            ?>
            
            <?php if (!empty($payment_methods)): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Payment Method</th>
                            <th>Number of Payments</th>
                            <th>Total Amount</th>
                            <th>Percentage of Payments</th>
                            <th>Average Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payment_methods as $method => $stats): ?>
                            <tr>
                                <td><strong><?php echo ucfirst(str_replace('_', ' ', $method)); ?></strong></td>
                                <td><?php echo $stats['count']; ?></td>
                                <td><?php echo number_format($stats['amount'], 2); ?> ETB</td>
                                <td><?php echo round(($stats['count'] / $data['summary']['paid_fines']) * 100, 1); ?>%</td>
                                <td><?php echo number_format($stats['amount'] / $stats['count'], 2); ?> ETB</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">No payment method data available for paid fines.</p>
            <?php endif; ?>
        </div>
        
        <!-- Department Fine Analysis -->
        <div class="department-fines" style="margin-top: 2rem;">
            <h3><i class="fas fa-building"></i> Fines by Department</h3>
            <?php
            $dept_fines = [];
            foreach ($data['fines'] as $fine) {
                $dept = $fine['department'];
                if (!isset($dept_fines[$dept])) {
                    $dept_fines[$dept] = [
                        'total_fines' => 0,
                        'paid_fines' => 0,
                        'unpaid_fines' => 0,
                        'total_amount' => 0,
                        'paid_amount' => 0,
                        'unpaid_amount' => 0
                    ];
                }
                $dept_fines[$dept]['total_fines']++;
                $dept_fines[$dept]['total_amount'] += $fine['fine_amount'];
                
                if ($fine['payment_status'] === 'paid') {
                    $dept_fines[$dept]['paid_fines']++;
                    $dept_fines[$dept]['paid_amount'] += $fine['fine_amount'];
                } elseif ($fine['payment_status'] === 'unpaid') {
                    $dept_fines[$dept]['unpaid_fines']++;
                    $dept_fines[$dept]['unpaid_amount'] += $fine['fine_amount'];
                }
            }
            ?>
            
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Total Fines</th>
                        <th>Paid</th>
                        <th>Unpaid</th>
                        <th>Total Amount</th>
                        <th>Collection Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dept_fines as $dept => $stats): ?>
                        <?php $collection_rate = $stats['total_amount'] > 0 ? ($stats['paid_amount'] / $stats['total_amount']) * 100 : 0; ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($dept); ?></strong></td>
                            <td><?php echo $stats['total_fines']; ?></td>
                            <td class="text-success"><?php echo $stats['paid_fines']; ?></td>
                            <td class="text-danger"><?php echo $stats['unpaid_fines']; ?></td>
                            <td><?php echo number_format($stats['total_amount'], 2); ?> ETB</td>
                            <td class="<?php echo $collection_rate >= 80 ? 'text-success' : ($collection_rate >= 60 ? 'text-warning' : 'text-danger'); ?>">
                                <?php echo round($collection_rate, 1); ?>%
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Insights and Recommendations -->
        <div class="insights" style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
            <h4><i class="fas fa-lightbulb"></i> Fine Management Insights</h4>
            <ul>
                <li><strong>Collection Performance:</strong> 
                    <?php if ($data['summary']['collection_rate'] >= 80): ?>
                        Excellent collection rate of <?php echo round($data['summary']['collection_rate'], 1); ?>%
                    <?php elseif ($data['summary']['collection_rate'] >= 60): ?>
                        Good collection rate of <?php echo round($data['summary']['collection_rate'], 1); ?>%, room for improvement
                    <?php else: ?>
                        Collection rate of <?php echo round($data['summary']['collection_rate'], 1); ?>% needs attention
                    <?php endif; ?>
                </li>
                <li><strong>Outstanding Amount:</strong> <?php echo number_format($data['summary']['unpaid_amount'], 2); ?> ETB in unpaid fines requires follow-up</li>
                <li><strong>Average Fine:</strong> <?php echo $data['summary']['total_fines'] > 0 ? number_format($data['summary']['total_amount'] / $data['summary']['total_fines'], 2) : '0.00'; ?> ETB per fine</li>
                <li><strong>Waiver Rate:</strong> <?php echo $data['summary']['total_fines'] > 0 ? round(($data['summary']['waived_fines'] / $data['summary']['total_fines']) * 100, 1) : 0; ?>% of fines have been waived</li>
            </ul>
        </div>
        
    <?php else: ?>
        <div class="text-center" style="padding: 3rem;">
            <i class="fas fa-check-circle fa-3x text-success"></i>
            <h3>No Fines Generated</h3>
            <p class="text-muted">No fines were generated during the selected period. Excellent compliance!</p>
        </div>
    <?php endif; ?>
</div>

<style>
.financial-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.financial-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    text-align: center;
    border-left: 4px solid #007bff;
}

.financial-card.total { border-left-color: #6c757d; }
.financial-card.collected { border-left-color: #28a745; }
.financial-card.outstanding { border-left-color: #dc3545; }
.financial-card.rate { border-left-color: #17a2b8; }

.financial-card h4 {
    margin: 0 0 1rem 0;
    color: #495057;
    font-size: 1rem;
}

.financial-card .amount {
    margin: 0;
    font-size: 1.5rem;
    font-weight: bold;
    color: #1e3c72;
}

.fine-paid {
    background-color: #f8fff8;
    border-left: 4px solid #28a745;
}

.fine-waived {
    background-color: #fff9e6;
    border-left: 4px solid #ffc107;
}

.fine-unpaid {
    background-color: #fff5f5;
    border-left: 4px solid #dc3545;
}

.days-overdue {
    color: #dc3545;
    font-weight: bold;
}

.fine-amount {
    color: #dc3545;
    font-weight: bold;
    font-size: 1.1em;
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-paid {
    background: #d4edda;
    color: #155724;
}

.status-waived {
    background: #fff3cd;
    color: #856404;
}

.status-unpaid {
    background: #f8d7da;
    color: #721c24;
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
    .financial-grid {
        grid-template-columns: 1fr;
    }
    
    .report-summary {
        grid-template-columns: 1fr;
    }
}
</style>