<?php
/**
 * SIMPLE PAYMENTS PAGE - No Filters, Just Show Data
 */
require_once '../config.php';
requireRole(['admin']);

$pageTitle = "Payment Management";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= SITE_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #f5f5f5; padding: 12px; text-align: left; border: 1px solid #ddd; }
        td { padding: 12px; border: 1px solid #ddd; }
        tr:hover { background: #fafafa; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 5px; }
        .alert-info { background: #e3f2fd; border-left: 4px solid #2196f3; color: #1976d2; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; }
        .badge-completed { background: #c8e6c9; color: #2e7d32; }
        .badge-pending { background: #fff9c4; color: #f57f17; }
        .badge-failed { background: #ffcdd2; color: #c62828; }
    </style>
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>
    
    <main style="padding: 20px;">
        <div class="container">
            <h1>💳 Payment Management</h1>
            
            <div class="alert alert-info">
                <strong>Simple View:</strong> All students with their payments (no filters)
            </div>
            
            <?php
            try {
                // Get ALL students
                $query = "
                    SELECT 
                        s.student_id,
                        s.student_name,
                        s.photo_url,
                        b.bus_number,
                        p.parent_name,
                        p.phone_primary,
                        pay.payment_id,
                        pay.status,
                        pay.amount,
                        pay.payment_date
                    FROM students s
                    LEFT JOIN parents p ON s.parent_id = p.parent_id
                    LEFT JOIN buses b ON s.bus_id = b.bus_id
                    LEFT JOIN payments pay ON s.student_id = pay.student_id
                    ORDER BY s.student_name
                ";
                
                $stmt = $pdo->query($query);
                $students = $stmt->fetchAll();
                
                echo "<p><strong>Total Students Found:</strong> " . count($students) . "</p>";
                
                if (empty($students)) {
                    echo "<div class='alert alert-info'><strong>No students found!</strong> Check if students table has data.</div>";
                } else {
                    echo "<table>";
                    echo "<thead><tr>";
                    echo "<th>#</th>";
                    echo "<th>Student Name</th>";
                    echo "<th>Bus</th>";
                    echo "<th>Parent</th>";
                    echo "<th>Phone</th>";
                    echo "<th>Payment Status</th>";
                    echo "<th>Amount</th>";
                    echo "<th>Payment Date</th>";
                    echo "</tr></thead>";
                    echo "<tbody>";
                    
                    $num = 1;
                    foreach ($students as $student) {
                        $status = $student['status'] ?? 'no-record';
                        $statusBadge = 'badge-pending';
                        if ($status === 'completed') $statusBadge = 'badge-completed';
                        elseif ($status === 'failed') $statusBadge = 'badge-failed';
                        
                        echo "<tr>";
                        echo "<td>" . ($num++) . "</td>";
                        echo "<td><strong>" . htmlspecialchars($student['student_name']) . "</strong></td>";
                        echo "<td>" . htmlspecialchars($student['bus_number'] ?? 'N/A') . "</td>";
                        echo "<td>" . htmlspecialchars($student['parent_name'] ?? 'N/A') . "</td>";
                        echo "<td>" . htmlspecialchars($student['phone_primary'] ?? 'N/A') . "</td>";
                        echo "<td>";
                        if ($student['payment_id']) {
                            echo "<span class='badge $statusBadge'>" . ucfirst($status) . "</span>";
                        } else {
                            echo "<span class='badge badge-pending'>No Record</span>";
                        }
                        echo "</td>";
                        echo "<td>RM " . number_format($student['amount'] ?? 0, 2) . "</td>";
                        echo "<td>" . ($student['payment_date'] ? date('d M Y', strtotime($student['payment_date'])) : '-') . "</td>";
                        echo "</tr>";
                    }
                    
                    echo "</tbody>";
                    echo "</table>";
                }
                
            } catch (Exception $e) {
                echo "<div class='alert' style='background: #ffebee; border-left: 4px solid #f44336; color: #c62828;'>";
                echo "<strong>Database Error:</strong> " . htmlspecialchars($e->getMessage());
                echo "</div>";
                error_log("Payment Query Error: " . $e->getMessage());
            }
            ?>
            
        </div>
    </main>
    
    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
