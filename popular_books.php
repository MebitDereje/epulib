<?php
/**
 * Popular Books Report Template
 */
$data = $report_data;
?>

<div class="report-header">
    <h2><i class="fas fa-star"></i> Popular Books Report</h2>
    <p>Most borrowed books from <?php echo date('M j, Y', strtotime($date_from)); ?> to <?php echo date('M j, Y', strtotime($date_to)); ?></p>
</div>

<div style="padding: 1.5rem;">
    <?php if (!empty($data)): ?>
        <!-- Top 5 Most Popular -->
        <div class="top-books" style="margin-bottom: 2rem;">
            <h3><i class="fas fa-trophy"></i> Top 5 Most Popular Books</h3>
            <div class="top-books-grid">
                <?php for ($i = 0; $i < min(5, count($data)); $i++): ?>
                    <?php $book = $data[$i]; ?>
                    <div class="top-book-card rank-<?php echo $i + 1; ?>">
                        <div class="rank-badge">#<?php echo $i + 1; ?></div>
                        <div class="book-info">
                            <h4><?php echo htmlspecialchars($book['title']); ?></h4>
                            <p class="author">by <?php echo htmlspecialchars($book['author']); ?></p>
                            <p class="category"><?php echo htmlspecialchars($book['category_name']); ?></p>
                            <div class="stats">
                                <span class="borrow-count"><?php echo $book['borrow_count']; ?> borrows</span>
                                <span class="return-rate"><?php echo $book['return_count'] > 0 ? round(($book['return_count'] / $book['borrow_count']) * 100) : 0; ?>% returned</span>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        
        <!-- Complete List -->
        <div class="complete-list">
            <h3><i class="fas fa-list"></i> Complete Popular Books List</h3>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Book Details</th>
                        <th>Category</th>
                        <th>Times Borrowed</th>
                        <th>Times Returned</th>
                        <th>Late Returns</th>
                        <th>Avg. Borrow Period</th>
                        <th>Return Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $index => $book): ?>
                        <tr class="<?php echo $index < 3 ? 'top-rank' : ''; ?>">
                            <td>
                                <span class="rank-number">#<?php echo $index + 1; ?></span>
                                <?php if ($index < 3): ?>
                                    <i class="fas fa-medal rank-icon rank-<?php echo $index + 1; ?>"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($book['title']); ?></strong><br>
                                <small>by <?php echo htmlspecialchars($book['author']); ?></small><br>
                                <small class="text-muted">ISBN: <?php echo htmlspecialchars($book['isbn']); ?></small>
                            </td>
                            <td>
                                <span class="category-badge"><?php echo htmlspecialchars($book['category_name']); ?></span>
                            </td>
                            <td>
                                <span class="borrow-count-large"><?php echo $book['borrow_count']; ?></span>
                            </td>
                            <td><?php echo $book['return_count']; ?></td>
                            <td>
                                <?php if ($book['late_returns'] > 0): ?>
                                    <span class="text-warning"><?php echo $book['late_returns']; ?></span>
                                <?php else: ?>
                                    <span class="text-success">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($book['avg_borrow_days']): ?>
                                    <?php echo round($book['avg_borrow_days'], 1); ?> days
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $return_rate = $book['borrow_count'] > 0 ? ($book['return_count'] / $book['borrow_count']) * 100 : 0;
                                $rate_class = $return_rate >= 90 ? 'text-success' : ($return_rate >= 70 ? 'text-warning' : 'text-danger');
                                ?>
                                <span class="<?php echo $rate_class; ?>">
                                    <?php echo round($return_rate); ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Insights -->
        <div class="insights" style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
            <h4><i class="fas fa-lightbulb"></i> Insights & Recommendations</h4>
            <?php
            $total_borrows = array_sum(array_column($data, 'borrow_count'));
            $avg_borrows = count($data) > 0 ? $total_borrows / count($data) : 0;
            $high_demand_books = array_filter($data, fn($book) => $book['borrow_count'] > $avg_borrows * 1.5);
            $low_return_books = array_filter($data, fn($book) => $book['borrow_count'] > 0 && ($book['return_count'] / $book['borrow_count']) < 0.8);
            ?>
            <ul>
                <li><strong>High Demand:</strong> <?php echo count($high_demand_books); ?> books have significantly higher than average borrowing rates</li>
                <li><strong>Collection Development:</strong> Consider acquiring additional copies of the top 10 most popular books</li>
                <li><strong>Return Monitoring:</strong> <?php echo count($low_return_books); ?> popular books have return rates below 80%</li>
                <li><strong>Average Borrowing:</strong> Books are borrowed an average of <?php echo round($avg_borrows, 1); ?> times during this period</li>
            </ul>
        </div>
        
    <?php else: ?>
        <div class="text-center" style="padding: 3rem;">
            <i class="fas fa-book fa-3x text-muted"></i>
            <h3>No Borrowing Data</h3>
            <p class="text-muted">No books were borrowed during the selected period.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.top-books-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.top-book-card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    position: relative;
    border-left: 4px solid #007bff;
}

.top-book-card.rank-1 { border-left-color: #ffd700; }
.top-book-card.rank-2 { border-left-color: #c0c0c0; }
.top-book-card.rank-3 { border-left-color: #cd7f32; }

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

.top-book-card h4 {
    margin: 0 0 0.5rem 0;
    color: #1e3c72;
    font-size: 1.1rem;
}

.top-book-card .author {
    margin: 0 0 0.5rem 0;
    color: #6c757d;
    font-style: italic;
}

.top-book-card .category {
    margin: 0 0 1rem 0;
    font-size: 0.9rem;
    color: #495057;
}

.top-book-card .stats {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
}

.borrow-count {
    color: #007bff;
    font-weight: bold;
}

.return-rate {
    color: #28a745;
}

.top-rank {
    background-color: #fff9e6;
}

.rank-number {
    font-weight: bold;
    font-size: 1.1rem;
}

.rank-icon {
    margin-left: 0.5rem;
}

.rank-icon.rank-1 { color: #ffd700; }
.rank-icon.rank-2 { color: #c0c0c0; }
.rank-icon.rank-3 { color: #cd7f32; }

.borrow-count-large {
    font-size: 1.2rem;
    font-weight: bold;
    color: #007bff;
}

.category-badge {
    background: #e9ecef;
    color: #495057;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
}

.insights ul {
    margin: 0;
    padding-left: 1.5rem;
}

.insights li {
    margin-bottom: 0.5rem;
}

@media (max-width: 768px) {
    .top-books-grid {
        grid-template-columns: 1fr;
    }
}
</style>