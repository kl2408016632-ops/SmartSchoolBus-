<?php
/**
 * Debug Payments Data
 * Check what's in the database
 */
require_once '../config.php';
requireRole(['admin']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Debug Payments</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
        .error { color: red; }
        .success { color: green; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Payment System Debug</h1>
    
    <?php
    try {
        // Check 1: Count tables
        echo "<h2>1. Row Counts</h2>";
        $counts = [];
        foreach (['students', 'parents', 'buses', 'payments'] as $table) {
            $result = $pdo->query("SELECT COUNT(*) as cnt FROM $table")->fetch();
            $counts[$table] = $result['cnt'];
            echo "<p><strong>$table:</strong> " . $result['cnt'] . " rows</p>";
        }
        
        // Check 2: Show students data
        echo "<h2>2. Students Data</h2>";
        $students = $pdo->query("SELECT * FROM students LIMIT 10")->fetchAll();
        if (empty($students)) {
            echo '<p class="error">No students found!</p>';
        } else {
            echo '<table><tr><th>ID</th><th>Name</th><th>Bus ID</th><th>Status</th><th>Payment Status</th></tr>';
            foreach ($students as $s) {
                echo "<tr>";
                echo "<td>{$s['student_id']}</td>";
                echo "<td>{$s['student_name']}</td>";
                echo "<td>{$s['bus_id']}</td>";
                echo "<td>{$s['status']}</td>";
                echo "<td>{$s['payment_status']}</td>";
                echo "</tr>";
            }
            echo '</table>';
        }
        
        // Check 3: Show payments data
        echo "<h2>3. Payments Data</h2>";
        $payments = $pdo->query("SELECT * FROM payments LIMIT 10")->fetchAll();
        if (empty($payments)) {
            echo '<p class="error">No payments found!</p>';
        } else {
            echo '<table><tr><th>Payment ID</th><th>Student ID</th><th>Amount</th><th>Date</th><th>Status</th></tr>';
            foreach ($payments as $p) {
                echo "<tr>";
                echo "<td>{$p['payment_id']}</td>";
                echo "<td>{$p['student_id']}</td>";
                echo "<td>{$p['amount']}</td>";
                echo "<td>{$p['payment_date']}</td>";
                echo "<td>{$p['status']}</td>";
                echo "</tr>";
            }
            echo '</table>';
        }
        
        // Check 4: Test the exact query from payments.php
        echo "<h2>4. Test Payments Query (Current Month)</h2>";
        $month = date('n');
        $year = date('Y');
        $query = "
            SELECT 
                s.student_id,
                s.student_name,
                s.payment_status,
                b.bus_number,
                pay.payment_id,
                pay.status,
                pay.amount
            FROM students s
            LEFT JOIN parents p ON s.parent_id = p.parent_id
            LEFT JOIN buses b ON s.bus_id = b.bus_id
            LEFT JOIN payments pay ON s.student_id = pay.student_id 
                AND MONTH(pay.payment_date) = ? 
                AND YEAR(pay.payment_date) = ?
            WHERE s.status = 'active'
            ORDER BY s.student_name
            LIMIT 20
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$month, $year]);
        $results = $stmt->fetchAll();
        
        echo "<p><strong>Month:</strong> $month, <strong>Year:</strong> $year</p>";
        echo "<p><strong>Results:</strong> " . count($results) . " rows</p>";
        
        if (empty($results)) {
            echo '<p class="error">Query returned NO RESULTS</p>';
        } else {
            echo '<table><tr><th>Student ID</th><th>Name</th><th>Bus</th><th>Payment Status</th><th>Payment Amount</th></tr>';
            foreach ($results as $r) {
                echo "<tr>";
                echo "<td>{$r['student_id']}</td>";
                echo "<td>{$r['student_name']}</td>";
                echo "<td>{$r['bus_number']}</td>";
                echo "<td>{$r['payment_status']}</td>";
                echo "<td>{$r['amount']}</td>";
                echo "</tr>";
            }
            echo '</table>';
        }
        
        // Check 5: Check if student_id exists in both tables
        echo "<h2>5. Student-Payment ID Match</h2>";
        $matchQuery = "
            SELECT 
                s.student_id,
                s.student_name,
                COUNT(p.payment_id) as payment_count
            FROM students s
            LEFT JOIN payments p ON s.student_id = p.student_id
            GROUP BY s.student_id
            ORDER BY s.student_id
            LIMIT 20
        ";
        $stmt = $pdo->prepare($matchQuery);
        $stmt->execute();
        $matches = $stmt->fetchAll();
        
        echo '<table><tr><th>Student ID</th><th>Name</th><th>Payment Records</th></tr>';
        foreach ($matches as $m) {
            $badge = $m['payment_count'] > 0 ? '<span class="success">✓ ' . $m['payment_count'] . '</span>' : '<span class="error">✗ 0</span>';
            echo "<tr>";
            echo "<td>{$m['student_id']}</td>";
            echo "<td>{$m['student_name']}</td>";
            echo "<td>$badge</td>";
            echo "</tr>";
        }
        echo '</table>';
        
    } catch (Exception $e) {
        echo '<p class="error">Error: ' . $e->getMessage() . '</p>';
    }
    ?>
    
    <hr>
    <p><a href="payments.php">← Back to Payments</a></p>
</body>
</html>
