<?php
/**
 * Collection Status Report Template
 */
$data = $report_data;
?>

<div class="report-header">
    <h2><i class="fas fa-books"></i> Collection Status Report</h2>
    <p>Library collection overview as of <?php echo date('M j, Y g:i A'); ?></p>
</div>

<!-- Overall Statistics -->
<div class="report-summary">
    <div class="summary-card">
        <h3><?php echo number_format($data['totals']['total_books']); ?></h3>
        <p>Total Book Titles</p>
    </div>
    <div class="summary-card">
        <h3><?php echo number_format($data['totals']['total_copies']); ?></h3>
        <p>Total Book Copies</p>
    </div>
    <div class="summary-card">
        <h3 class="text-success"><?php echo number_format($data['totals']['available_copies']); ?></h3>
        <p>Available Copies</p>
    </div>
    <div class="summary-card">
        <h3 class="text-info"><?php echo number_format($data['totals']['borrowed_copies']); ?></h3>
        <p>Currently Borrowed</p>
    </div>
</div>

<div style="padding: 1.5rem;">
    <!-- Utilization Overview -->
    <div class="utilization-overview" style="margin-bottom: 2rem;">
        <div class="utilization-grid">
            <div class="utilization-card">
                <h4>Collection Utilization Rate</h4>
                <div class="utilization-bar">
                    <div class="utilization-fill" style="width: <?php echo round($data['totals']['utilization_rate']); ?>%"></div>
                </div>
                <p class="utilization-text">
                    <?php echo round($data['totals']['utilization_rate'], 1); ?>% of collection currently in use
                </p>
            </div>
            <div class="utilization-stats">
                <div class="stat-item">
                    <span class="stat-label">Availability Rate:</span>
                    <span class="stat-value text-success">
                        <?php echo round(($data['totals']['available_copies'] / $data['totals']['total_copies']) * 100, 1); ?>%
                    </span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Average Copies per Title:</span>
                    <span class="stat-value">
                        <?php echo $data['totals']['total_books'] > 0 ? round($data['totals']['total_copies'] / $data['totals']['total_books'], 1) : 0; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($data['collection_data'])): ?>
        <!-- Collection by Category -->
        <div class="category-analysis">
            <h3><i class="fas fa-chart-bar"></i> Collection Analysis by Category</h3>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Book Titles</th>
                        <th>Total Copies</th>
                        <th>Available</th>
                        <th>Borrowed</th>
                        <th>Utilization Rate</th>
                        <th>Status Distribution</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['collection_data'] as $category): ?>
                        <?php 
                        $utilization = $category['total_copies'] > 0 ? 
                            ($category['borrowed_copies'] / $category['total_copies']) * 100 : 0;
                        $availability = $category['total_copies'] > 0 ? 
                            ($category['available_copies'] / $category['total_copies']) * 100 : 0;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($category['category_name']); ?></strong>
                            </td>
                            <td><?php echo number_format($category['total_books']); ?></td>
                            <td><?php echo number_format($category['total_copies']); ?></td>
                            <td class="text-success"><?php echo number_format($category['available_copies']); ?></td>
                            <td class="text-info"><?php echo number_format($category['borrowed_copies']); ?></td>
                            <td>
                                <div class="mini-utilization-bar">
                                    <div class="mini-utilization-fill" style="width: <?php echo round($utilization); ?>%"></div>
                                </div>
                                <span class="<?php echo $utilization > 70 ? 'text-success' : ($utilization > 40 ? 'text-warning' : 'text-muted'); ?>">
                                    <?php echo round($utilization, 1); ?>%
                                </span>
                            </td>
                            <td>
                                <div class="status-distribution">
                                    <div class="status-item">
                                        <span class="status-dot status-available"></span>
                                        <small><?php echo $category['available_titles']; ?> available</small>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-dot status-borrowed"></span>
                                        <small><?php echo $category['borrowed_titles']; ?> borrowed</small>
                                    </div>
                                    <?php if ($category['maintenance_titles'] > 0): ?>
                                        <div class="status-item">
                                            <span class="status-dot status-maintenance"></span>
                                            <small><?php echo $category['maintenance_titles']; ?> maintenance</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Category Performance Analysis -->
        <div class="performance-analysis" style="margin-top: 2rem;">
            <h3><i class="fas fa-trophy"></i> Category Performance</h3>
            
            <?php
            // Sort categories by utilization rate
            $sorted_categories = $data['collection_data'];
            usort($sorted_categories, function($a, $b) {
                $util_a = $a['total_copies'] > 0 ? ($a['borrowed_copies'] / $a['total_copies']) * 100 : 0;
                $util_b = $b['total_copies'] > 0 ? ($b['borrowed_copies'] / $b['total_copies']) * 100 : 0;
                return $util_b <=> $util_a;
            });
            
            $high_demand = array_slice($sorted_categories, 0, 3);
            $low_demand = array_slice($sorted_categories, -3);
            ?>
            
            <div class="performance-grid">
                <div class="performance-card high-demand">
                    <h4><i class="fas fa-fire"></i> High Demand Categories</h4>
                    <?php foreach ($high_demand as $category): ?>
                        <?php $utilization = $category['total_copies'] > 0 ? ($category['borrowed_copies'] / $category['total_copies']) * 100 : 0; ?>
                        <div class="category-item">
                            <strong><?php echo htmlspecialchars($category['category_name']); ?></strong>
                            <span class="utilization-rate text-success"><?php echo round($utilization, 1); ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="performance-card low-demand">
                    <h4><i class="fas fa-chart-line-down"></i> Low Demand Categories</h4>
                    <?php foreach (array_reverse($low_demand) as $category): ?>
                        <?php $utilization = $category['total_copies'] > 0 ? ($category['borrowed_copies'] / $category['total_copies']) * 100 : 0; ?>
                        <div class="category-item">
                            <strong><?php echo htmlspecialchars($category['category_name']); ?></strong>
                            <span class="utilization-rate text-muted"><?php echo round($utilization, 1); ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Collection Health Indicators -->
        <div class="health-indicators" style="margin-top: 2rem;">
            <h3><i class="fas fa-heartbeat"></i> Collection Health Indicators</h3>
            
            <?php
            $total_maintenance = array_sum(array_column($data['collection_data'], 'maintenance_titles'));
            $avg_utilization = count($data['collection_data']) > 0 ? 
                array_sum(array_map(function($cat) {
                    return $cat['total_copies'] > 0 ? ($cat['borrowed_copies'] / $cat['total_copies']) * 100 : 0;
                }, $data['collection_data'])) / count($data['collection_data']) : 0;
            
            $underutilized = count(array_filter($data['collection_data'], function($cat) {
                $util = $cat['total_copies'] > 0 ? ($cat['borrowed_copies'] / $cat['total_copies']) * 100 : 0;
                return $util < 10;
            }));
            
            $overutilized = count(array_filter($data['collection_data'], function($cat) {
                $util = $cat['total_copies'] > 0 ? ($cat['borrowed_copies'] / $cat['total_copies']) * 100 : 0;
                return $util > 80;
            }));
            ?>
            
            <div class="health-grid">
                <div class="health-card">
                    <div class="health-icon">
                        <i class="fas fa-tools <?php echo $total_maintenance > 0 ? 'text-warning' : 'text-success'; ?>"></i>
                    </div>
                    <div class="health-info">
                        <h5>Maintenance Status</h5>
                        <p><?php echo $total_maintenance; ?> books in maintenance</p>
                        <small class="<?php echo $total_maintenance > 0 ? 'text-warning' : 'text-success'; ?>">
                            <?php echo $total_maintenance > 0 ? 'Requires attention' : 'All books operational'; ?>
                        </small>
                    </div>
                </div>
                
                <div class="health-card">
                    <div class="health-icon">
                        <i class="fas fa-chart-line <?php echo $avg_utilization > 50 ? 'text-success' : ($avg_utilization > 25 ? 'text-warning' : 'text-danger'); ?>"></i>
                    </div>
                    <div class="health-info">
                        <h5>Average Utilization</h5>
                        <p><?php echo round($avg_utilization, 1); ?>%</p>
                        <small class="<?php echo $avg_utilization > 50 ? 'text-success' : ($avg_utilization > 25 ? 'text-warning' : 'text-danger'); ?>">
                            <?php 
                            if ($avg_utilization > 50) echo 'Excellent usage';
                            elseif ($avg_utilization > 25) echo 'Good usage';
                            else echo 'Low usage';
                            ?>
                        </small>
                    </div>
                </div>
                
                <div class="health-card">
                    <div class="health-icon">
                        <i class="fas fa-exclamation-triangle <?php echo $underutilized > 0 ? 'text-warning' : 'text-success'; ?>"></i>
                    </div>
                    <div class="health-info">
                        <h5>Underutilized Categories</h5>
                        <p><?php echo $underutilized; ?> categories</p>
                        <small class="<?php echo $underutilized > 0 ? 'text-warning' : 'text-success'; ?>">
                            <?php echo $underutilized > 0 ? 'Need promotion' : 'All well utilized'; ?>
                        </small>
                    </div>
                </div>
                
                <div class="health-card">
                    <div class="health-icon">
                        <i class="fas fa-fire <?php echo $overutilized > 0 ? 'text-danger' : 'text-success'; ?>"></i>
                    </div>
                    <div class="health-info">
                        <h5>High Demand Categories</h5>
                        <p><?php echo $overutilized; ?> categories</p>
                        <small class="<?php echo $overutilized > 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo $overutilized > 0 ? 'Need more copies' : 'Adequate supply'; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recommendations -->
        <div class="recommendations" style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
            <h4><i class="fas fa-lightbulb"></i> Collection Management Recommendations</h4>
            <ul>
                <?php if ($overutilized > 0): ?>
                    <li><strong>Acquisition Priority:</strong> Consider acquiring additional copies for high-demand categories (>80% utilization)</li>
                <?php endif; ?>
                
                <?php if ($underutilized > 0): ?>
                    <li><strong>Promotion Needed:</strong> <?php echo $underutilized; ?> categories have low utilization (<10%) and may benefit from promotion</li>
                <?php endif; ?>
                
                <?php if ($total_maintenance > 0): ?>
                    <li><strong>Maintenance Alert:</strong> <?php echo $total_maintenance; ?> books require maintenance attention</li>
                <?php endif; ?>
                
                <li><strong>Collection Balance:</strong> 
                    <?php if ($avg_utilization > 60): ?>
                        Excellent collection utilization indicates good balance between supply and demand
                    <?php elseif ($avg_utilization > 30): ?>
                        Good collection utilization with room for improvement in some categories
                    <?php else: ?>
                        Low overall utilization suggests need for collection review and user engagement
                    <?php endif; ?>
                </li>
                
                <li><strong>Optimal Utilization:</strong> Target utilization rate of 40-70% ensures good availability while maximizing usage</li>
            </ul>
        </div>
        
    <?php else: ?>
        <div class="text-center" style="padding: 3rem;">
            <i class="fas fa-books fa-3x text-muted"></i>
            <h3>No Collection Data</h3>
            <p class="text-muted">No books found in the collection.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.utilization-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    align-items: center;
}

.utilization-card {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.utilization-card h4 {
    margin: 0 0 1rem 0;
    color: #1e3c72;
}

.utilization-bar {
    width: 100%;
    height: 20px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 1rem;
}

.utilization-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #ffc107, #dc3545);
    transition: width 0.3s ease;
}

.utilization-text {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #495057;
}

.utilization-stats {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stat-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.stat-label {
    color: #6c757d;
}

.stat-value {
    font-weight: bold;
}

.mini-utilization-bar {
    width: 60px;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    display: inline-block;
    margin-right: 0.5rem;
    vertical-align: middle;
}

.mini-utilization-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #ffc107, #dc3545);
}

.status-distribution {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.status-available { background: #28a745; }
.status-borrowed { background: #17a2b8; }
.status-maintenance { background: #ffc107; }

.performance-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.performance-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.performance-card h4 {
    margin: 0 0 1rem 0;
    color: #1e3c72;
}

.category-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f8f9fa;
}

.category-item:last-child {
    border-bottom: none;
}

.utilization-rate {
    font-weight: bold;
}

.health-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.health-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.health-icon {
    font-size: 2rem;
}

.health-info h5 {
    margin: 0 0 0.5rem 0;
    color: #1e3c72;
}

.health-info p {
    margin: 0 0 0.25rem 0;
    font-size: 1.2rem;
    font-weight: bold;
    color: #495057;
}

.health-info small {
    font-size: 0.8rem;
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

.recommendations ul {
    margin: 0;
    padding-left: 1.5rem;
}

.recommendations li {
    margin-bottom: 0.5rem;
}

@media (max-width: 768px) {
    .utilization-grid,
    .performance-grid {
        grid-template-columns: 1fr;
    }
    
    .health-grid {
        grid-template-columns: 1fr;
    }
    
    .report-summary {
        grid-template-columns: 1fr;
    }
}
</style>