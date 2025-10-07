<?php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$export_type = $_GET['type'] ?? 'csv';
$filter = $_GET['filter'] ?? 'all';

// Build filter conditions
$where_conditions = ["t.user_id = ?"];
$params = [$user_id];

if ($filter !== 'all') {
    $where_conditions[] = "t.type = ?";
    $params[] = $filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get transactions
$sql = "
    SELECT t.*, 
           CASE 
               WHEN t.type = 'package_investment' THEN p.name
               ELSE NULL
           END as package_name
    FROM transactions t
    LEFT JOIN active_packages ap ON t.id = ap.id AND t.type = 'package_investment'
    LEFT JOIN packages p ON ap.package_id = p.id
    WHERE $where_clause
    ORDER BY t.created_at DESC 
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get user info for filename
$stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch();

$filename = 'UltraHarvest_Transactions_' . date('Y-m-d_H-i-s');
$user_name = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $user_info['full_name']));
$filename .= '_' . $user_name;

if ($export_type === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'Date',
        'Type',
        'Amount (KSh)',
        'Status',
        'Description',
        'M-Pesa Receipt',
        'Phone Number',
        'Package'
    ]);
    
    // CSV data
    foreach ($transactions as $transaction) {
        fputcsv($output, [
            date('Y-m-d H:i:s', strtotime($transaction['created_at'])),
            ucfirst(str_replace('_', ' ', $transaction['type'])),
            number_format($transaction['amount'], 2),
            ucfirst($transaction['status']),
            $transaction['description'] ?? '',
            $transaction['mpesa_receipt'] ?? '',
            $transaction['phone_number'] ?? '',
            $transaction['package_name'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;

} elseif ($export_type === 'pdf') {
    // For PDF export, we'll create a simple HTML that can be printed as PDF
    header('Content-Type: text/html');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Transaction Export - Ultra Harvest Global</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #10b981; padding-bottom: 20px; }
            .header h1 { color: #10b981; margin: 0; }
            .header p { color: #666; margin: 5px 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #10b981; color: white; }
            tr:nth-child(even) { background-color: #f2f2f2; }
            .footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Ultra Harvest Global</h1>
            <p>Transaction History Report</p>
            <p>User: <?php echo htmlspecialchars($user_info['full_name']); ?></p>
            <p>Generated: <?php echo date('F j, Y g:i A'); ?></p>
            <?php if ($filter !== 'all'): ?>
            <p>Filter: <?php echo ucfirst(str_replace('_', ' ', $filter)); ?></p>
            <?php endif; ?>
        </div>
        
        <button onclick="window.print()" class="no-print" style="padding: 10px 20px; background: #10b981; color: white; border: none; border-radius: 5px; cursor: pointer; margin-bottom: 20px;">Print/Save as PDF</button>
        
        <table>
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Description</th>
                    <th>Receipt</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $transaction): ?>
                <tr>
                    <td><?php echo date('M j, Y g:i A', strtotime($transaction['created_at'])); ?></td>
                    <td><?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?></td>
                    <td>KSh <?php echo number_format($transaction['amount'], 2); ?></td>
                    <td>
                        <span style="padding: 2px 8px; border-radius: 10px; font-size: 12px; 
                            <?php 
                            echo match($transaction['status']) {
                                'completed' => 'background-color: #dcfce7; color: #16a34a;',
                                'pending' => 'background-color: #fef3c7; color: #d97706;',
                                'failed' => 'background-color: #fecaca; color: #dc2626;',
                                default => 'background-color: #f3f4f6; color: #6b7280;'
                            };
                            ?>">
                            <?php echo ucfirst($transaction['status']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($transaction['description'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($transaction['mpesa_receipt'] ?? 'N/A'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if (empty($transactions)): ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <p>No transactions found for the selected criteria.</p>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p>Ultra Harvest Global - Growing Wealth Together</p>
            <p>This report contains <?php echo count($transactions); ?> transaction(s)</p>
            <p>Report generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Fallback
header('Location: /user/transactions.php');
exit;
?>