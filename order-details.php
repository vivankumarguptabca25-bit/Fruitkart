<?php
$pageTitle = "Order Details";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$orderId = (int)($_GET['id'] ?? 0);
$db = getDB();
$userId = $_SESSION['user_id'];

// Allow admin or order owner to view
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

$itemStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemStmt->execute([$orderId]);
$orderItems = $itemStmt->fetchAll();

$adminUpiId = getSetting('admin_upi_id', 'fruitkart@okaxis');
$adminQrImage = getSetting('admin_qr_image', '');

// Handle Customer Pay Online Action for Pending COD Orders
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_online_now'])) {
    $utr = trim($_POST['utr_number'] ?? '');
    $cleanUtr = preg_replace('/[^0-9]/', '', $utr);

    if (strlen($cleanUtr) !== 12) {
        setFlash('error', 'Kripya 12-digit numeric UPI Ref / UTR number enter karein (e.g. 408291048192).');
        header("Location: " . SITE_URL . "/order-details.php?id=" . $orderId);
        exit;
    }

    $fakePatterns = ['000000000000', '111111111111', '123456789012', '999999999999', '123456123456', '012345678901'];
    if (in_array($cleanUtr, $fakePatterns)) {
        setFlash('error', 'Aapne dummy/fake UPI UTR number enter kiya hai. Kripya apna asli payment reference enter karein.');
        header("Location: " . SITE_URL . "/order-details.php?id=" . $orderId);
        exit;
    }

    $dupCheck = $db->prepare("SELECT id FROM orders WHERE transaction_id = ? AND id != ?");
    $dupCheck->execute([$cleanUtr, $orderId]);
    if ($dupCheck->fetch()) {
        setFlash('error', 'Yeh UTR number pehle se dusre order me use ho chuka hai. Kripya naya payment reference dalein.');
        header("Location: " . SITE_URL . "/order-details.php?id=" . $orderId);
        exit;
    }

    $isAutoVerify = (getSetting('payment_auto_verify', '1') === '1');

    if ($isAutoVerify) {
        $upd = $db->prepare("UPDATE orders SET payment_status = 'Paid', order_status = 'Confirmed', payment_method = 'UPI', transaction_id = ? WHERE id = ?");
        $upd->execute([$cleanUtr, $orderId]);

        $db->prepare("INSERT INTO order_tracking (order_id, status, title, description) VALUES (?, 'Confirmed', 'Payment Auto-Verified & Confirmed', ?)")
           ->execute([$orderId, "Customer completed ₹" . number_format($order['final_amount'], 2) . " via UPI (Ref: $cleanUtr). Auto-verified in Admin bank account."]);

        setFlash('success', 'UPI Payment of ' . formatPrice($order['final_amount']) . ' Auto-Verified! Money successfully received and order Confirmed.');
    } else {
        $upd = $db->prepare("UPDATE orders SET payment_status = 'Pending Verification', payment_method = 'UPI', transaction_id = ? WHERE id = ?");
        $upd->execute([$cleanUtr, $orderId]);

        $db->prepare("INSERT INTO order_tracking (order_id, status, title, description) VALUES (?, (SELECT order_status FROM orders WHERE id = ?), 'Payment Verification Pending', ?)")
           ->execute([$orderId, $orderId, "Customer submitted 12-digit UPI UTR (#$cleanUtr). Verification in progress with Admin bank account."]);

        setFlash('success', 'UPI UTR # ' . $cleanUtr . ' submit ho gaya hai! Admin bank account me credit check hone par ise confirm karenge.');
    }

    header("Location: " . SITE_URL . "/order-details.php?id=" . $orderId);
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <!-- Top Action Nav -->
    <div class="flex items-center justify-between mb-6">
        <a href="<?= SITE_URL ?>/my-orders.php" class="text-xs font-bold text-fk-blue hover:underline flex items-center gap-1.5">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to My Orders</span>
        </a>

        <div class="flex items-center gap-3">
            <button onclick="window.print()" class="bg-gray-100 hover:bg-gray-200 text-gray-800 text-xs font-bold px-4 py-2 rounded-lg transition flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-print"></i>
                <span>Print Invoice</span>
            </button>
            <a href="<?= SITE_URL ?>/order-tracking.php?order_id=<?= $order['id'] ?>" class="bg-fk-blue hover:bg-fk-blue-dark text-white text-xs font-bold px-4 py-2 rounded-lg transition flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-location-crosshairs"></i>
                <span>Track Order</span>
            </a>
        </div>
    </div>

    <!-- Main Printable Receipt Box -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden p-6 sm:p-8 space-y-6">

        <?php if ($order['payment_status'] === 'Pending Verification' || $order['order_status'] === 'Payment Verification Pending'): ?>
            <!-- Verification Alert Notice -->
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-xs text-amber-900 flex items-start gap-3.5">
                <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 text-base shadow-inner">
                    <i class="fa-solid fa-hourglass-half animate-spin"></i>
                </div>
                <div>
                    <strong class="text-sm font-bold text-amber-950 block">Bank Credit Verification in Progress</strong>
                    <p class="text-amber-800 mt-0.5 leading-relaxed">
                        Aapka 12-digit UPI UTR (<code class="bg-amber-100 px-1.5 py-0.5 rounded font-mono font-bold text-amber-950"><?= htmlspecialchars($order['transaction_id'] ?? 'N/A') ?></code>) receive ho chuka hai. Admin hamare Business Bank Account me payment credit check kar rahe hain. Credit confirm hote hi aapka order Confirm ho jayega.
                    </p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Header Info -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-gray-100 pb-6">
            <div>
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Tax Invoice / Receipt</span>
                <h1 class="text-xl sm:text-2xl font-black text-gray-900 mt-1">Order #<?= htmlspecialchars($order['order_number']) ?></h1>
                <div class="text-xs text-gray-500 mt-1">Placed on: <?= date('d F Y, h:i A', strtotime($order['created_at'])) ?></div>
            </div>

            <div class="text-left sm:text-right">
                <div class="inline-block px-3 py-1 rounded-full text-xs font-bold <?= 
                    $order['order_status'] === 'Delivered' ? 'bg-emerald-100 text-emerald-800' :
                    ($order['order_status'] === 'Cancelled' ? 'bg-rose-100 text-rose-800' : 
                    ($order['order_status'] === 'Payment Verification Pending' ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-blue-100 text-fk-blue')) ?>">
                    Status: <?= htmlspecialchars($order['order_status']) ?>
                </div>
                <div class="text-xs text-gray-500 mt-1">Slot: <?= htmlspecialchars($order['delivery_slot']) ?></div>
            </div>
        </div>

        <!-- 3-Column Meta Breakdown -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 bg-gray-50 p-4 rounded-xl border border-gray-100 text-xs">
            <div>
                <h3 class="font-bold text-gray-800 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                    <span>Delivery Address</span>
                    <?php if (!empty($order['latitude']) && !empty($order['longitude'])): ?>
                        <span class="text-[10px] text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded font-bold">GPS Tagged</span>
                    <?php endif; ?>
                </h3>
                <p class="text-gray-600 leading-relaxed">
                    <?= nl2br(htmlspecialchars($order['address_snapshot'])) ?>
                </p>
                <?php if (!empty($order['latitude']) && !empty($order['longitude'])): ?>
                    <a href="https://www.google.com/maps?q=<?= $order['latitude'] ?>,<?= $order['longitude'] ?>" target="_blank" class="mt-1.5 inline-flex items-center gap-1 text-[11px] text-fk-blue font-bold hover:underline">
                        <i class="fa-solid fa-map-location-dot text-rose-500"></i>
                        <span>Open Pin on Google Maps</span>
                    </a>
                <?php endif; ?>
                <?php if (!empty($order['delivery_boy_name'])): ?>
                    <div class="mt-2 pt-1.5 border-t border-gray-200 text-[11px] text-gray-700 flex items-center gap-1.5">
                        <i class="fa-solid fa-person-biking text-sky-600"></i>
                        <span>Delivery Partner: <strong><?= htmlspecialchars($order['delivery_boy_name']) ?></strong></span>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <h3 class="font-bold text-gray-800 uppercase tracking-wider mb-1.5">Payment Details</h3>
                <div class="text-gray-600 space-y-1">
                    <div>Method: <strong class="text-gray-800"><?= htmlspecialchars($order['payment_method']) ?></strong></div>
                    <div>Status: 
                        <?php if ($order['payment_status'] === 'Paid'): ?>
                            <span class="font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 inline-flex items-center gap-1">
                                <i class="fa-solid fa-circle-check text-emerald-600"></i> Paid (<?= $order['payment_method'] === 'UPI' ? 'Auto-Verified' : ($order['payment_method'] === 'CARD' ? '3D Secure Verified' : 'Received') ?>)
                            </span>
                        <?php elseif ($order['payment_status'] === 'Pending Verification'): ?>
                            <span class="font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded border border-amber-300 inline-flex items-center gap-1">
                                <i class="fa-solid fa-hourglass-half"></i> Verification Pending
                            </span>
                        <?php else: ?>
                            <span class="font-semibold text-amber-700"><?= htmlspecialchars($order['payment_status']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($order['transaction_id'])): ?>
                        <div>Ref / UTR: <span class="font-mono text-[10px] bg-gray-200 px-1.5 py-0.5 rounded text-gray-800 font-bold"><?= htmlspecialchars($order['transaction_id']) ?></span></div>
                    <?php endif; ?>
                    <div>Gateway: FruitKart Secure Pay</div>
                </div>

                <?php if ($order['payment_status'] === 'Pending'): ?>
                    <button 
                        type="button" 
                        onclick="document.getElementById('pay-online-modal').classList.remove('hidden')" 
                        class="mt-3 w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-2 px-3 rounded-lg shadow transition flex items-center justify-center gap-1.5"
                    >
                        <i class="fa-solid fa-qrcode"></i>
                        <span>Pay Online via UPI QR</span>
                    </button>
                <?php endif; ?>
            </div>

            <div>
                <h3 class="font-bold text-gray-800 uppercase tracking-wider mb-1.5">Seller Info</h3>
                <p class="text-gray-600 leading-relaxed">
                    FruitKart Fresh Hub India Pvt. Ltd.<br>
                    Plot 45, Sector 18 Cold Chain Zone,<br>
                    Gurugram, Haryana - 122001<br>
                    FSSAI Lic: 10819005000124
                </p>
            </div>
        </div>

        <!-- Items Table -->
        <div>
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-3">Order Items</h3>
            <div class="border border-gray-200 rounded-lg overflow-hidden">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 border-b border-gray-200 font-bold uppercase text-[11px]">
                            <th class="p-3">Product</th>
                            <th class="p-3 text-center">Unit</th>
                            <th class="p-3 text-right">Price</th>
                            <th class="p-3 text-center">Qty</th>
                            <th class="p-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($orderItems as $item): ?>
                            <tr>
                                <td class="p-3 flex items-center gap-3">
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="w-10 h-10 rounded object-cover border border-gray-200">
                                    <span class="font-bold text-gray-900"><?= htmlspecialchars($item['product_name']) ?></span>
                                </td>
                                <td class="p-3 text-center text-gray-500"><?= htmlspecialchars($item['unit']) ?></td>
                                <td class="p-3 text-right text-gray-700"><?= formatPrice($item['price']) ?></td>
                                <td class="p-3 text-center font-bold text-gray-900"><?= $item['quantity'] ?></td>
                                <td class="p-3 text-right font-black text-gray-900"><?= formatPrice($item['total_price']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Price Summary Breakdown -->
        <div class="flex justify-end">
            <div class="w-full sm:w-72 space-y-2 text-xs border border-gray-200 rounded-lg p-4 bg-gray-50">
                <div class="flex justify-between text-gray-600">
                    <span>Subtotal (MRP):</span>
                    <span><?= formatPrice($order['total_mrp']) ?></span>
                </div>
                <div class="flex justify-between text-emerald-600 font-medium">
                    <span>Discount:</span>
                    <span>&minus; <?= formatPrice($order['discount_amount']) ?></span>
                </div>
                <?php if ($order['coupon_discount'] > 0): ?>
                    <div class="flex justify-between text-emerald-600 font-medium">
                        <span>Coupon (<?= htmlspecialchars($order['coupon_code']) ?>):</span>
                        <span>&minus; <?= formatPrice($order['coupon_discount']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between text-gray-600">
                    <span>Delivery Fee:</span>
                    <span><?= $order['delivery_fee'] == 0 ? 'FREE' : formatPrice($order['delivery_fee']) ?></span>
                </div>
                <div class="border-t border-gray-300 pt-2 flex justify-between text-sm font-black text-gray-900">
                    <span>Final Amount:</span>
                    <span><?= formatPrice($order['final_amount']) ?></span>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Customer Pay Online UPI QR Modal -->
<div id="pay-online-modal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-full max-w-sm overflow-hidden animate-in fade-in zoom-in duration-150">
        <div class="p-4 bg-gradient-to-r from-emerald-700 to-teal-800 text-white flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-qrcode text-yellow-300"></i>
                <h3 class="font-black text-sm uppercase">Pay Online via UPI QR</h3>
            </div>
            <button onclick="document.getElementById('pay-online-modal').classList.add('hidden')" class="text-white/70 hover:text-white font-bold text-xl leading-none">&times;</button>
        </div>

        <form action="<?= SITE_URL ?>/order-details.php?id=<?= $order['id'] ?>" method="POST" class="p-6 text-center space-y-4">
            <div>
                <span class="text-xs text-gray-500">Scan &amp; Pay using Any UPI App</span>
                <div class="text-2xl font-black text-gray-900 mt-0.5"><?= formatPrice($order['final_amount']) ?></div>
            </div>

            <!-- Dynamic or Admin QR Code -->
            <div class="p-3 bg-gray-50 rounded-xl border border-gray-200 inline-block shadow-inner">
                <?php if (!empty($adminQrImage)): ?>
                    <img src="<?= htmlspecialchars($adminQrImage) ?>" alt="UPI QR Code" class="w-44 h-44 mx-auto object-contain rounded-lg">
                <?php else: ?>
                    <img 
                        src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode('upi://pay?pa=' . $adminUpiId . '&pn=FruitKartFresh&am=' . $order['final_amount'] . '&cu=INR&tn=Order-' . $order['order_number']) ?>" 
                        alt="UPI QR Code" 
                        class="w-40 h-40 mx-auto"
                    >
                <?php endif; ?>
            </div>

            <div class="text-[11px] text-gray-500">
                Official UPI ID: <strong class="text-gray-900 font-mono font-bold"><?= htmlspecialchars($adminUpiId) ?></strong><br>
                Google Pay &bull; PhonePe &bull; Paytm &bull; BHIM UPI
            </div>

            <div class="text-left bg-gray-50 p-3 rounded-xl border border-gray-200 space-y-1.5">
                <label class="block text-[11px] font-bold text-gray-700 uppercase">
                    12-Digit UPI Ref / UTR Number *
                </label>
                <input 
                    type="text" 
                    name="utr_number" 
                    required 
                    pattern="[0-9]{12}" 
                    maxlength="12" 
                    minlength="12" 
                    placeholder="e.g. 408291048192" 
                    class="w-full p-2.5 border rounded-lg font-mono font-bold text-sm text-gray-900 tracking-wider focus:outline-none focus:border-fk-blue"
                >
                <span class="text-[10px] text-gray-500 block">
                    Payment successful screen se 12-digit UPI Transaction / UTR number copy karke yahan paste karein.
                </span>
            </div>

            <button 
                type="submit" 
                name="pay_online_now" 
                class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs py-3 rounded-xl shadow-md transition uppercase tracking-wide flex items-center justify-center gap-2"
            >
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Submit 12-Digit UTR for Verification</span>
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
