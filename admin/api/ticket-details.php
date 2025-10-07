<?php
/**
 * Ticket Details API
 * admin/api/ticket-details.php
 */

require_once '../../config/database.php';
requireAdmin();

// Set content type
header('Content-Type: text/html; charset=utf-8');

$ticket_id = intval($_GET['id'] ?? 0);

if (!$ticket_id) {
    echo '<div class="p-6 text-center text-red-400">Invalid ticket ID</div>';
    exit;
}

try {
    // Get ticket details
    $stmt = $db->prepare("
        SELECT t.*, u.full_name, u.email, u.phone, 
               admin.full_name as admin_name
        FROM support_tickets t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN users admin ON t.admin_id = admin.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        echo '<div class="p-6 text-center text-red-400">Ticket not found</div>';
        exit;
    }

    // Get ticket responses
    $stmt = $db->prepare("
        SELECT tr.*, u.full_name, u.is_admin
        FROM ticket_responses tr
        JOIN users u ON tr.user_id = u.id
        WHERE tr.ticket_id = ?
        ORDER BY tr.created_at ASC
    ");
    $stmt->execute([$ticket_id]);
    $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate ticket number if missing
    $ticket_number = $ticket['ticket_number'] ?: 'TKT' . str_pad($ticket['id'], 6, '0', STR_PAD_LEFT);

} catch (Exception $e) {
    echo '<div class="p-6 text-center text-red-400">Error loading ticket: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="p-6">
    <!-- Ticket Header -->
    <div class="border-b border-gray-700 pb-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-2xl font-bold text-white">#<?php echo htmlspecialchars($ticket_number); ?></h2>
                <p class="text-gray-400"><?php echo htmlspecialchars($ticket['subject']); ?></p>
            </div>
            <div class="flex items-center space-x-3">
                <span class="px-3 py-1 rounded-full text-sm font-medium
                    <?php 
                    echo match($ticket['priority']) {
                        'urgent' => 'bg-red-500/20 text-red-400',
                        'high' => 'bg-orange-500/20 text-orange-400',
                        'medium' => 'bg-yellow-500/20 text-yellow-400',
                        'low' => 'bg-green-500/20 text-green-400',
                        default => 'bg-gray-500/20 text-gray-400'
                    };
                    ?>">
                    <?php echo ucfirst($ticket['priority']); ?> Priority
                </span>
                <span class="px-3 py-1 rounded-full text-sm font-medium
                    <?php 
                    echo match($ticket['status']) {
                        'open' => 'bg-blue-500/20 text-blue-400',
                        'in_progress' => 'bg-yellow-500/20 text-yellow-400',
                        'resolved' => 'bg-emerald-500/20 text-emerald-400',
                        'closed' => 'bg-gray-500/20 text-gray-400',
                        default => 'bg-gray-500/20 text-gray-400'
                    };
                    ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                </span>
            </div>
        </div>

        <!-- Ticket Meta Information -->
        <div class="grid md:grid-cols-3 gap-6">
            <div>
                <h4 class="text-sm font-medium text-gray-400 mb-2">Customer</h4>
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center text-white font-bold">
                        <?php echo strtoupper(substr($ticket['full_name'], 0, 2)); ?>
                    </div>
                    <div>
                        <p class="font-medium text-white"><?php echo htmlspecialchars($ticket['full_name']); ?></p>
                        <p class="text-sm text-gray-400"><?php echo htmlspecialchars($ticket['email']); ?></p>
                        <?php if ($ticket['phone']): ?>
                            <p class="text-sm text-blue-400"><?php echo htmlspecialchars($ticket['phone']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div>
                <h4 class="text-sm font-medium text-gray-400 mb-2">Details</h4>
                <div class="space-y-1">
                    <p class="text-sm text-white">
                        <span class="text-gray-400">Category:</span> 
                        <?php echo ucfirst($ticket['category']); ?>
                    </p>
                    <p class="text-sm text-white">
                        <span class="text-gray-400">Created:</span> 
                        <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
                    </p>
                    <p class="text-sm text-white">
                        <span class="text-gray-400">Updated:</span> 
                        <?php echo date('M j, Y g:i A', strtotime($ticket['updated_at'])); ?>
                    </p>
                </div>
            </div>

            <div>
                <h4 class="text-sm font-medium text-gray-400 mb-2">Assignment</h4>
                <div class="space-y-1">
                    <p class="text-sm text-white">
                        <span class="text-gray-400">Assigned to:</span> 
                        <?php echo $ticket['admin_name'] ? htmlspecialchars($ticket['admin_name']) : '<span class="text-red-400">Unassigned</span>'; ?>
                    </p>
                    <p class="text-sm text-white">
                        <span class="text-gray-400">Last response:</span> 
                        <?php echo ucfirst($ticket['last_response_by'] ?? 'user'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Original Message -->
    <div class="mb-6">
        <h3 class="text-lg font-semibold text-white mb-3">Original Message</h3>
        <div class="bg-gray-800/50 rounded-lg p-4">
            <div class="flex items-start space-x-3">
                <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center text-white text-sm font-bold">
                    <?php echo strtoupper(substr($ticket['full_name'], 0, 2)); ?>
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium text-white"><?php echo htmlspecialchars($ticket['full_name']); ?></span>
                        <span class="text-xs text-gray-500"><?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></span>
                    </div>
                    <div class="text-gray-300 whitespace-pre-wrap"><?php echo htmlspecialchars($ticket['message']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Responses -->
    <?php if (!empty($responses)): ?>
    <div class="mb-6">
        <h3 class="text-lg font-semibold text-white mb-3">Responses (<?php echo count($responses); ?>)</h3>
        <div class="space-y-4 max-h-96 overflow-y-auto">
            <?php foreach ($responses as $response): ?>
            <div class="bg-<?php echo $response['is_admin'] ? 'emerald' : 'blue'; ?>-500/10 border border-<?php echo $response['is_admin'] ? 'emerald' : 'blue'; ?>-500/30 rounded-lg p-4">
                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 bg-gradient-to-r from-<?php echo $response['is_admin'] ? 'emerald' : 'blue'; ?>-500 to-<?php echo $response['is_admin'] ? 'green' : 'purple'; ?>-500 rounded-full flex items-center justify-center text-white text-sm font-bold">
                        <?php if ($response['is_admin']): ?>
                            <i class="fas fa-user-shield"></i>
                        <?php else: ?>
                            <?php echo strtoupper(substr($response['full_name'], 0, 2)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center space-x-2">
                                <span class="font-medium text-white"><?php echo htmlspecialchars($response['full_name']); ?></span>
                                <?php if ($response['is_admin']): ?>
                                    <span class="px-2 py-1 bg-emerald-500/20 text-emerald-400 rounded text-xs font-medium">Admin</span>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs text-gray-500"><?php echo date('M j, Y g:i A', strtotime($response['created_at'])); ?></span>
                        </div>
                        <div class="text-gray-300 whitespace-pre-wrap"><?php echo htmlspecialchars($response['response_text']); ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="border-t border-gray-700 pt-6">
        <h3 class="text-lg font-semibold text-white mb-3">Quick Actions</h3>
        <div class="flex flex-wrap gap-3">
            <button onclick="addResponse(<?php echo $ticket['id']; ?>); closeModal('viewTicketModal');" 
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition">
                <i class="fas fa-reply mr-2"></i>Add Response
            </button>
            
            <button onclick="updateTicket(<?php echo $ticket['id']; ?>, '<?php echo $ticket['status']; ?>'); closeModal('viewTicketModal');" 
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                <i class="fas fa-edit mr-2"></i>Update Status
            </button>
            
            <?php if (!$ticket['admin_id']): ?>
            <button onclick="assignTicket(<?php echo $ticket['id']; ?>); closeModal('viewTicketModal');" 
                    class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition">
                <i class="fas fa-user-plus mr-2"></i>Assign Ticket
            </button>
            <?php endif; ?>
            
            <?php if ($ticket['status'] !== 'resolved'): ?>
            <button onclick="quickStatusChange(<?php echo $ticket['id']; ?>, 'resolved')" 
                    class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg font-medium transition">
                <i class="fas fa-check mr-2"></i>Mark Resolved
            </button>
            <?php endif; ?>
            
            <?php if ($ticket['status'] !== 'closed'): ?>
            <button onclick="quickStatusChange(<?php echo $ticket['id']; ?>, 'closed')" 
                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition">
                <i class="fas fa-times mr-2"></i>Close Ticket
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Admin Notes Section -->
    <?php if ($ticket['admin_response']): ?>
    <div class="border-t border-gray-700 pt-6 mt-6">
        <h3 class="text-lg font-semibold text-white mb-3">Admin Notes</h3>
        <div class="bg-gray-800/50 rounded-lg p-4">
            <div class="text-gray-300 whitespace-pre-wrap"><?php echo htmlspecialchars($ticket['admin_response']); ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Make sure these functions are available in the parent window
if (window.parent && window.parent !== window) {
    window.addResponse = window.parent.addResponse;
    window.updateTicket = window.parent.updateTicket;
    window.assignTicket = window.parent.assignTicket;
    window.closeModal = window.parent.closeModal;
    window.quickStatusChange = window.parent.quickStatusChange;
}
</script>