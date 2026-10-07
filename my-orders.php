<?php
$pageTitle = "My Orders";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

// Fetch all orders for this user
$stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-gray-900">My Orders</h1>
            <p class="text-xs text-gray-500">Track and manage your recent farm-fresh fruit orders</p>
        </div>
        <a href="<?= SITE_URL ?>/products.php" class="text-xs font-bold text-fk-blue hover:underline">
            Shop More Fruits &rarr;
        </a>
    </div>

    <?php if (count($orders) > 0): ?>
        <div class="space-y-4">
            <?php foreach ($orders as $order): ?>
                <?php
                // Fetch items for this order
                $itemStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
                $itemStmt->execute([$order['id']]);
                $orderItems = $itemStmt->fetchAll();
                ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition">
                    
                    <!-- Order Card Header -->
                    <div class="p-4 bg-gray-50/80 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4 text-xs">
                        <div class="flex items-center gap-6">
                            <div>
                                <span class="text-gray-500 block text-[10px] uppercase font-bold">ORDER ID</span>
                                <span class="font-mono font-bold text-gray-900"><?= htmlspecialchars($order['order_number']) ?></span>
                            </div>
                            <div>
                                <span class="text-gray-500 block text-[10px] uppercase font-bold">ORDER PLACED</span>
                                <span class="font-semibold text-gray-800"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></span>
                            </div>
                            <div>
                                <span class="text-gray-500 block text-[10px] uppercase font-bold">TOTAL AMOUNT</span>
                                <div class="flex items-center gap-1.5">
                                    <span class="font-black text-gray-900"><?= formatPrice($order['final_amount']) ?></span>
                                    <?php if ($order['payment_status'] === 'Paid'): ?>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            Paid (<?= htmlspecialchars($order['payment_method']) ?>)
                                        </span>
                                    <?php elseif ($order['payment_status'] === 'Pending Verification'): ?>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-900 border border-amber-300 inline-flex items-center gap-1">
                                            <i class="fa-solid fa-hourglass-half text-[9px]"></i> Verification Pending (<?= htmlspecialchars($order['payment_method']) ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-800">
                                            <?= htmlspecialchars($order['payment_status']) ?> (<?= htmlspecialchars($order['payment_method']) ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <?php if ($order['payment_status'] === 'Pending'): ?>
                                <a href="<?= SITE_URL ?>/order-details.php?id=<?= $order['id'] ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-1.5 rounded shadow-sm transition flex items-center gap-1">
                                    <i class="fa-solid fa-qrcode text-[11px]"></i>
                                    <span>Pay Online</span>
                                </a>
                            <?php endif; ?>
                            <a href="<?= SITE_URL ?>/order-tracking.php?order_id=<?= $order['id'] ?>" class="bg-fk-blue hover:bg-fk-blue-dark text-white font-bold px-3.5 py-1.5 rounded shadow-sm transition flex items-center gap-1.5">
                                <i class="fa-solid fa-location-dot text-[11px]"></i>
                                <span>Track</span>
                            </a>
                            <a href="<?= SITE_URL ?>/order-details.php?id=<?= $order['id'] ?>" class="border border-gray-300 hover:bg-gray-100 text-gray-700 font-bold px-3 py-1.5 rounded transition">
                                Details
                            </a>
                        </div>
                    </div>

                    <!-- Items & Status Grid -->
                    <div class="p-4 sm:p-6 divide-y divide-gray-100">
                        <?php foreach ($orderItems as $item): ?>
                            <div class="pt-4 first:pt-0 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                
                                <div class="flex items-center gap-4">
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="w-16 h-16 rounded-lg object-cover border border-gray-200">
                                    <div>
                                        <a href="<?= SITE_URL ?>/product-details.php?id=<?= $item['product_id'] ?>" class="text-sm font-bold text-gray-900 hover:text-fk-blue line-clamp-1">
                                            <?= htmlspecialchars($item['product_name']) ?>
                                        </a>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            Qty: <?= $item['quantity'] ?> &bull; Unit: <?= htmlspecialchars($item['unit']) ?> &bull; <?= formatPrice($item['price']) ?> each
                                        </div>
                                        <div class="text-xs font-bold text-gray-900 mt-1">
                                            Item Total: <?= formatPrice($item['total_price']) ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Status Badge -->
                                <div class="text-left sm:text-right">
                                    <div class="flex items-center sm:justify-end gap-2 mb-1">
                                        <span class="w-2.5 h-2.5 rounded-full <?= 
                                            $order['order_status'] === 'Delivered' ? 'bg-emerald-500' :
                                            ($order['order_status'] === 'Cancelled' ? 'bg-rose-500' : 'bg-amber-500 animate-pulse') ?>">
                                        </span>
                                        <span class="text-xs font-bold text-gray-900"><?= htmlspecialchars($order['order_status']) ?></span>
                                    </div>
                                    <div class="text-[11px] text-gray-500">
                                        <?php if ($order['order_status'] === 'Delivered'): ?>
                                            Delivered Safely to Doorstep
                                        <?php else: ?>
                                            Expected Today, <?= htmlspecialchars($order['delivery_slot']) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2 flex sm:justify-end">
                                        <a href="<?= SITE_URL ?>/product-details.php?id=<?= $item['product_id'] ?>#write-review-box" class="inline-flex items-center gap-1.5 text-xs text-amber-700 hover:text-amber-800 font-bold bg-amber-50 hover:bg-amber-100 px-2.5 py-1 rounded border border-amber-200 transition">
                                            <i class="fa-solid fa-star text-[10px] text-amber-500"></i>
                                            <span>Rate &amp; Review</span>
                                        </a>
                                    </div>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center max-w-xl mx-auto my-8">
            <div class="w-20 h-20 mx-auto rounded-full bg-blue-50 text-fk-blue flex items-center justify-center text-3xl mb-4">
                <i class="fa-solid fa-box-open"></i>
            </div>
            <h2 class="text-lg font-bold text-gray-900 mb-1">No Orders Yet!</h2>
            <p class="text-xs text-gray-500 mb-6">Looks like you haven't ordered any delicious fruits yet.</p>
            <a href="<?= SITE_URL ?>/products.php" class="bg-fk-blue hover:bg-fk-blue-dark text-white font-bold text-xs px-6 py-2.5 rounded-lg shadow transition inline-block uppercase">
                Start Shopping Now
            </a>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
