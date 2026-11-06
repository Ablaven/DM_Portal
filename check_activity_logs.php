<?php
require_once 'php/db_connect.php';

$pdo = get_pdo();

// Check if table exists
$stmt = $pdo->query("SHOW TABLES LIKE 'activity_logs'");
$tableExists = $stmt->rowCount() > 0;

echo "<h2>Activity Logs Diagnostic</h2>";
echo "<p>Table exists: " . ($tableExists ? 'YES' : 'NO') . "</p>";

if ($tableExists) {
    // Count total logs
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM activity_logs");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p>Total logs: $total</p>";
    
    // Get latest 10 logs
    $stmt = $pdo->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Latest 10 logs:</h3>";
    echo "<pre>";
    print_r($logs);
    echo "</pre>";
    
    // Get date range
    $stmt = $pdo->query("SELECT MIN(created_at) as earliest, MAX(created_at) as latest FROM activity_logs");
    $range = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<h3>Date Range:</h3>";
    echo "<p>Earliest: " . $range['earliest'] . "</p>";
    echo "<p>Latest: " . $range['latest'] . "</p>";
}
