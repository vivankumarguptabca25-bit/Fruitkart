<?php
$pageTitle = "Track Fruit Order";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$orderId = (int)($_GET['order_id'] ?? 0);
$db = getDB();
$userId = $_SESSION['user_id'];

if (isAdmin()) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
} else {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $userId]);
}
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Order not found.');
    header("Location: " . SITE_URL . "/my-orders.php");
    exit;
}

// Fetch tracking timeline logs
$trackStmt = $db->prepare("SELECT * FROM order_tracking WHERE order_id = ? ORDER BY id ASC");
$trackStmt->execute([$orderId]);
$trackingLogs = $trackStmt->fetchAll();

$deliveryBoy = null;
if (!empty($order['delivery_boy_id'])) {
    $deliveryBoy = getDeliveryBoyById($order['delivery_boy_id']);
}

// Sequence of stages
$stages = ['Placed', 'Confirmed', 'Packed', 'Shipped', 'Out for Delivery', 'Delivered'];
$currentStatus = $order['order_status'];
$currentIndex = array_search($currentStatus, $stages);
if ($currentIndex === false) $currentIndex = 0;

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <!-- Top Navigation -->
    <div class="flex items-center justify-between mb-6">
        <a href="<?= SITE_URL ?>/my-orders.php" class="text-xs font-bold text-fk-blue hover:underline flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Orders</span>
        </a>
        <div class="text-xs text-gray-500 font-mono">
            Order ID: <strong><?= htmlspecialchars($order['order_number']) ?></strong>
        </div>
    </div>

    <!-- Tracking Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8 space-y-8">

        <?php if ($order['payment_status'] === 'Pending Verification' || $currentStatus === 'Payment Verification Pending'): ?>
            <!-- Verification Alert Notice -->
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-xs text-amber-900 flex items-start gap-3.5">
                <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 text-lg shadow-inner">
                    <i class="fa-solid fa-hourglass-half animate-spin"></i>
                </div>
                <div>
                    <div class="inline-flex items-center gap-1 font-black uppercase text-[10px] text-amber-800 bg-amber-200/60 px-2 py-0.5 rounded mb-1">
                        <i class="fa-solid fa-clock"></i> Awaiting Bank Verification
                    </div>
                    <div class="font-bold text-amber-950 text-sm">UPI Payment Credit Check Hone Tak Order Processing Queue Me Hai</div>
                    <p class="text-amber-800 mt-0.5 leading-relaxed">
                        Aapka submitted UTR (<code class="bg-amber-100 font-bold px-1.5 py-0.5 rounded font-mono text-amber-950"><?= htmlspecialchars($order['transaction_id'] ?? 'N/A') ?></code>) Admin ke official bank statement ke sath verify kiya ja raha hai. Bank account me ₹<?= number_format($order['final_amount'], 2) ?> credit confirm hote hi yeh order automatic <strong>Confirmed</strong> ho jayega aur packaging shuru hogi.
                    </p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-gray-100 pb-6">
            <div>
                <span class="text-xs font-bold text-emerald-600 uppercase tracking-wider flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                    <span>Live Cold-Chain Tracking</span>
                </span>
                <h1 class="text-xl sm:text-2xl font-black text-gray-900 mt-1">
                    <?= $currentStatus === 'Delivered' ? 'Fruits Delivered Successfully 🎉' : ($currentStatus === 'Payment Verification Pending' ? 'Awaiting Bank Payment Verification ⏳' : 'Your Harvest is On the Way 🚚') ?>
                </h1>
                <p class="text-xs text-gray-500 mt-1">
                    Slot: <strong><?= htmlspecialchars($order['delivery_slot']) ?></strong> &bull; Total Items Amount: <?= formatPrice($order['final_amount']) ?>
                </p>
            </div>

            <!-- Delivery OTP Box -->
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-center sm:text-right">
                <div class="text-[10px] uppercase font-bold text-emerald-800">Secure Delivery OTP</div>
                <div class="text-xl font-mono font-black text-emerald-700 tracking-widest">
                    <?= substr(crc32($order['order_number']), 0, 4) ?>
                </div>
                <div class="text-[9px] text-gray-500">Share with agent at delivery</div>
            </div>
        </div>

        <!-- Flipkart Horizontal Stepper for Desktop -->
        <div class="hidden sm:block py-4">
            <div class="relative flex items-center justify-between">
                <!-- Background Line -->
                <div class="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-1 bg-gray-200 z-0"></div>
                <!-- Active Progress Line -->
                <div class="absolute left-0 top-1/2 -translate-y-1/2 h-1 bg-emerald-500 z-0 transition-all duration-500" style="width: <?= ($currentIndex / (count($stages) - 1)) * 100 ?>%;"></div>

                <?php foreach ($stages as $idx => $stg): ?>
                    <?php 
                    $isPassed = $idx <= $currentIndex;
                    $isCurrent = $idx === $currentIndex;
                    ?>
                    <div class="relative z-10 flex flex-col items-center">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-xs transition-all <?= 
                            $isPassed ? 'bg-emerald-500 text-white shadow-md' : 'bg-gray-200 text-gray-500' ?> <?= $isCurrent ? 'ring-4 ring-emerald-200' : '' ?>">
                            <?php if ($idx < $currentIndex || $currentStatus === 'Delivered'): ?>
                                <i class="fa-solid fa-check text-xs"></i>
                            <?php else: ?>
                                <span><?= $idx + 1 ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="text-[11px] font-bold mt-2 whitespace-nowrap <?= $isPassed ? 'text-gray-900' : 'text-gray-400' ?>">
                            <?= $stg ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Delivery Executive Card -->
        <div class="bg-blue-50/50 rounded-xl p-4 border border-blue-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full bg-fk-blue text-white flex items-center justify-center text-lg font-bold shadow">
                    <i class="fa-solid fa-person-biking"></i>
                </div>
                <div>
                    <?php if (!empty($order['delivery_boy_name'])): ?>
                        <div class="text-xs font-black text-gray-900"><?= htmlspecialchars($order['delivery_boy_name']) ?> <span class="text-emerald-700 font-semibold">(Express Cold Delivery Partner)</span></div>
                        <div class="text-[11px] text-gray-500 mt-0.5">
                            <?= htmlspecialchars($deliveryBoy['vehicle_type'] ?? 'Electric Refrigerated Van') ?> &bull; 
                            Reg: <strong><?= htmlspecialchars($deliveryBoy['vehicle_number'] ?? 'HR26-EF-4920') ?></strong> &bull; 
                            ⭐ <?= number_format($deliveryBoy['rating'] ?? 4.95, 2) ?> Rating
                        </div>
                    <?php else: ?>
                        <div class="text-xs font-bold text-gray-900">Cold Chain Express Logistics</div>
                        <div class="text-[11px] text-gray-500">Nearest delivery partner will be dispatched upon packaging.</div>
                    <?php endif; ?>
                    <div class="text-[11px] text-emerald-700 font-semibold mt-0.5">
                        <i class="fa-solid fa-shield-virus"></i> Sanitized &bull; Temperature Monitored (4°C)
                    </div>
                </div>
            </div>

            <?php if (!empty($order['delivery_boy_phone'])): ?>
                <a href="tel:<?= htmlspecialchars($order['delivery_boy_phone']) ?>" class="bg-white border border-gray-300 hover:border-fk-blue text-fk-blue font-bold text-xs px-4 py-2 rounded-lg shadow-sm transition flex items-center gap-2">
                    <i class="fa-solid fa-phone"></i>
                    <span>Call Delivery Partner (<?= htmlspecialchars($order['delivery_boy_phone']) ?>)</span>
                </a>
            <?php else: ?>
                <span class="text-xs font-bold text-gray-400 bg-white border border-gray-200 px-3 py-1.5 rounded-lg">
                    Partner Assigning...
                </span>
            <?php endif; ?>
        </div>

        <!-- Detailed Timeline Logs -->
        <div>
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4 flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-fk-blue"></i>
                <span>Activity &amp; Dispatch Timeline</span>
            </h3>

            <div class="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-gray-200">
                <?php if (count($trackingLogs) > 0): ?>
                    <?php foreach ($trackingLogs as $log): ?>
                        <div class="relative">
                            <span class="absolute -left-6 top-1 w-3 h-3 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></span>
                            <div class="text-xs font-bold text-gray-900"><?= htmlspecialchars($log['title']) ?></div>
                            <p class="text-xs text-gray-600 mt-0.5"><?= htmlspecialchars($log['description']) ?></p>
                            <span class="text-[10px] text-gray-400 block mt-1"><?= date('d M Y, h:i A', strtotime($log['created_at'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="relative">
                        <span class="absolute -left-6 top-1 w-3 h-3 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></span>
                        <div class="text-xs font-bold text-gray-900">Order Placed</div>
                        <p class="text-xs text-gray-600 mt-0.5">Order verified and farm logistics notified.</p>
                        <span class="text-[10px] text-gray-400 block mt-1"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Destination Address with GPS Link -->
        <div class="border-t border-gray-100 pt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-gray-600">
            <div>
                <strong>Delivering to:</strong> <?= htmlspecialchars($order['address_snapshot']) ?>
            </div>
            <?php if (!empty($order['latitude']) && !empty($order['longitude'])): ?>
                <a href="https://www.google.com/maps?q=<?= $order['latitude'] ?>,<?= $order['longitude'] ?>" target="_blank" class="inline-flex items-center gap-1.5 text-xs text-fk-blue font-bold hover:underline shrink-0">
                    <i class="fa-solid fa-map-location-dot text-rose-500"></i>
                    <span>View Destination on Google Maps</span>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                </a>
            <?php endif; ?>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
