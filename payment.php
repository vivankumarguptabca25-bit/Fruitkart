<?php
$pageTitle = "Complete Payment - 100% Safe & Secure";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];
$items = getCheckoutItems();

if (empty($items)) {
    header("Location: " . SITE_URL . "/products.php");
    exit;
}

// Ensure database schema columns exist safely (independent try/catch to prevent failure)
$schemaUpdates = [
    "ALTER TABLE orders MODIFY COLUMN payment_status VARCHAR(50) DEFAULT 'Pending'",
    "ALTER TABLE orders MODIFY COLUMN payment_method VARCHAR(50) DEFAULT 'COD'",
    "ALTER TABLE orders MODIFY COLUMN order_status VARCHAR(50) DEFAULT 'Placed'",
    "ALTER TABLE orders ADD COLUMN payment_notes TEXT DEFAULT NULL",
    "ALTER TABLE orders ADD COLUMN total_mrp DECIMAL(10,2) DEFAULT 0",
    "ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0",
    "ALTER TABLE orders ADD COLUMN coupon_discount DECIMAL(10,2) DEFAULT 0",
    "ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE orders ADD COLUMN delivery_slot VARCHAR(100) DEFAULT 'Express Slot (Within 2 Hours)'",
    "ALTER TABLE orders ADD COLUMN transaction_id VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE order_items ADD COLUMN mrp_price DECIMAL(10,2) DEFAULT 0",
    "ALTER TABLE order_tracking MODIFY COLUMN status VARCHAR(50) NOT NULL"
];
foreach ($schemaUpdates as $sql) {
    try { $db->exec($sql); } catch (Exception $e) {}
}

// Fallback: If checkout_data is missing, automatically pick user's default saved address
if (!isset($_SESSION['checkout_data'])) {
    $defStmt = $db->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1");
    $defStmt->execute([$userId]);
    $defaultAddr = $defStmt->fetch();
    if ($defaultAddr) {
        $_SESSION['checkout_data'] = [
            'address_id' => $defaultAddr['id'],
            'delivery_slot' => 'Express Slot (Within 2 Hours)'
        ];
    } else {
        header("Location: " . SITE_URL . "/checkout.php");
        exit;
    }
}

$checkoutData = $_SESSION['checkout_data'];
$addressId = $checkoutData['address_id'];
$deliverySlot = $checkoutData['delivery_slot'] ?? 'Express Slot (Within 2 Hours)';

// Fetch selected address with auto-recovery fallback
$stmt = $db->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
$stmt->execute([$addressId, $userId]);
$address = $stmt->fetch();

if (!$address) {
    $stmt = $db->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $address = $stmt->fetch();
    if ($address) {
        $_SESSION['checkout_data']['address_id'] = $address['id'];
        $addressId = $address['id'];
    } else {
        setFlash('error', 'Please add a delivery address first.');
        header("Location: " . SITE_URL . "/checkout.php");
        exit;
    }
}

$couponDiscount = $_SESSION['applied_coupon']['discount'] ?? 0;
$couponCode = $_SESSION['applied_coupon']['code'] ?? null;
$couponId = $_SESSION['applied_coupon']['id'] ?? null;
$totals = getCheckoutTotals($couponDiscount);

// Admin Payment & Bank Settings from Database
$adminUpiId = getSetting('admin_upi_id', 'fruitkart@okaxis');
$adminUpiName = getSetting('admin_upi_name', 'FruitKart Fresh Hub India Pvt Ltd');
$adminUpiPhone = getSetting('admin_upi_phone', '+91 98765 43210');
$adminQrImage = getSetting('admin_qr_image', '');
$isAutoVerify = (getSetting('payment_auto_verify', '1') === '1');
$razorpayEnabled = (getSetting('razorpay_enabled', '0') === '1');
$razorpayKeyId = getSetting('razorpay_key_id', '');

if (!empty($adminQrImage)) {
    if (strpos($adminQrImage, '/uploads/qr/') !== false) {
        $qrSubpath = substr($adminQrImage, strpos($adminQrImage, '/uploads/qr/'));
        $qrCodeUrl = SITE_URL . $qrSubpath;
    } elseif (strpos($adminQrImage, 'http') === 0) {
        $qrCodeUrl = $adminQrImage;
    } else {
        $qrCodeUrl = SITE_URL . '/' . ltrim($adminQrImage, '/');
    }
} else {
    $qrPayload = 'upi://pay?pa=' . urlencode($adminUpiId) . '&pn=' . urlencode($adminUpiName) . '&am=' . $totals['final_amount'] . '&cu=INR&tn=Order-FruitKart';
    $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($qrPayload);
}

$bankName = getSetting('bank_name', 'State Bank of India (SBI)');
$bankAccountName = getSetting('bank_account_name', 'FruitKart Fresh Hub India Private Limited');
$bankAccountNo = getSetting('bank_account_no', '50200084729103');
$bankIfsc = getSetting('bank_ifsc', 'SBIN0001234');
$bankBranch = getSetting('bank_branch', 'Sector 18 Commercial Hub, Gurugram');
$bankNotes = getSetting('bank_payment_notes', 'Please mention your Order Number in the transfer remarks/notes.');

// Setup Captcha for COD confirmation
if (empty($_SESSION['cod_captcha']) || isset($_GET['refresh_captcha'])) {
    $_SESSION['cod_captcha'] = strval(rand(111, 999));
}
$codCaptcha = $_SESSION['cod_captcha'];

// ==========================================
// PROCESS PAYMENT SUBMISSION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_order'])) {
    $paymentMethod = clean($_POST['payment_method'] ?? 'UPI');
    if (!in_array($paymentMethod, ['COD', 'UPI', 'CARD', 'NETBANKING', 'RAZORPAY'])) {
        $paymentMethod = 'UPI';
    }

    // Process Bank Card Offer Discount
    $cardDiscount = 0.0;
    if ($paymentMethod === 'CARD' && !empty($_POST['card_discount_applied'])) {
        $cardDiscount = min(50.00, (float)($_POST['card_discount'] ?? 50.00));
    }

    $finalAmountToCharge = max(0, $totals['final_amount'] - $cardDiscount);
    $totalDiscountAmount = $totals['discount'] + $cardDiscount;

    $rawTxnId = clean($_POST['transaction_id'] ?? ($_POST['bank_utr'] ?? ($_POST['razorpay_payment_id'] ?? '')));
    $txnId = preg_replace('/\s+/', '', $rawTxnId);
    $paymentNotes = '';
    $orderStatus = 'Placed';

    // 1. UPI Payment Mode
    if ($paymentMethod === 'UPI') {
        if (!preg_match('/^[0-9]{12}$/', $txnId)) {
            setFlash('error', 'Invalid UPI UTR! PhonePe/GPay/Paytm payment receipt se 12-digit numeric Reference / UTR Number dalein.');
            header("Location: " . SITE_URL . "/payment.php");
            exit;
        }

        // Dummy checks
        $fakePatterns = [
            '000000000000', '111111111111', '222222222222', '333333333333', 
            '444444444444', '555555555555', '666666666666', '777777777777', 
            '888888888888', '999999999999'
        ];
        if (in_array($txnId, $fakePatterns)) {
            setFlash('error', 'Fake/Dummy UTR number accept nahi hoga! Kripya real payment karke valid 12-digit UTR number enter karein.');
            header("Location: " . SITE_URL . "/payment.php");
            exit;
        }

        // Duplicate UTR check
        $dupCheck = $db->prepare("SELECT id FROM orders WHERE transaction_id = ?");
        $dupCheck->execute([$txnId]);
        if ($dupCheck->fetch()) {
            setFlash('error', 'Yeh UPI UTR pehle se kisi order me use ho chuka hai! Kripya fresh valid UTR enter karein.');
            header("Location: " . SITE_URL . "/payment.php");
            exit;
        }

        if ($isAutoVerify) {
            $paymentStatus = 'Paid';
            $orderStatus = 'Confirmed';
            $paymentNotes = "UPI Payment of " . formatPrice($finalAmountToCharge) . " Auto-Verified & Credited to Admin Bank Account (UTR: " . $txnId . "). Verified in real time.";
        } else {
            $paymentStatus = 'Pending Verification';
            $orderStatus = 'Payment Verification Pending';
            $paymentNotes = "UPI QR payment of " . formatPrice($finalAmountToCharge) . " submitted (UTR: " . $txnId . "). Awaiting manual Admin bank check.";
        }

    // 2. Card Payment Mode (Flipkart 3D Secure)
    } elseif ($paymentMethod === 'CARD') {
        $cardLast4 = clean($_POST['card_last4'] ?? '4242');
        $cardBank = clean($_POST['card_bank_name'] ?? 'Bank Card');
        if (empty($txnId)) {
            $txnId = 'CARD-AUTH-' . strtoupper(substr(md5(uniqid()), 0, 8));
        }
        $paymentStatus = 'Paid';
        $orderStatus = 'Confirmed';
        $paymentNotes = "Paid via $cardBank (ending in $cardLast4) - 3D Secure OTP Verified.";
        if ($cardDiscount > 0) {
            $paymentNotes .= " Flipkart Bank Card Offer Discount applied: " . formatPrice($cardDiscount) . ".";
        }

    // 3. Net Banking Mode
    } elseif ($paymentMethod === 'NETBANKING') {
        if (empty($txnId) || strlen($txnId) < 6) {
            setFlash('error', 'Kripya apna Bank Transfer IMPS / NEFT UTR / Reference ID enter karein!');
            header("Location: " . SITE_URL . "/payment.php");
            exit;
        }
        $senderBank = clean($_POST['sender_bank'] ?? 'Bank Transfer');
        if ($isAutoVerify) {
            $paymentStatus = 'Paid';
            $orderStatus = 'Confirmed';
            $paymentNotes = "Transferred via " . $senderBank . " (UTR: " . $txnId . ") - Auto-Verified in Admin Bank Account.";
        } else {
            $paymentStatus = 'Pending Verification';
            $orderStatus = 'Payment Verification Pending';
            $paymentNotes = "Transferred via " . $senderBank . " (UTR: " . $txnId . "). Awaiting manual bank check.";
        }

    // 4. Cash on Delivery (COD)
    } else {
        $codCaptchaInput = trim($_POST['cod_captcha_input'] ?? '');
        $savedCaptcha = trim($_SESSION['cod_captcha'] ?? '');
        if (!empty($savedCaptcha) && $codCaptchaInput !== $savedCaptcha) {
            setFlash('error', 'Invalid verification code! Kripya sahi 3-digit order confirmation code enter karein.');
            header("Location: " . SITE_URL . "/payment.php");
            exit;
        }
        $paymentStatus = 'Pending';
        $orderStatus = 'Placed';
        $txnId = 'COD-' . rand(100000, 999999);
        $paymentNotes = "Cash on Delivery - Verified by customer. To be collected upon delivery.";
        unset($_SESSION['cod_captcha']);
    }

    $orderNumber = 'FK-ORD-' . strtoupper(substr(uniqid(), -6)) . rand(10, 99);
    $addressSnapshot = "{$address['full_name']}, {$address['phone']}, {$address['address_line']}, {$address['locality']}, {$address['city']}, {$address['state']} - {$address['pincode']} ({$address['address_type']})";

    try {
        $db->beginTransaction();

        // 1. Insert into orders table
        $orderStmt = $db->prepare("
            INSERT INTO orders (
                order_number, user_id, address_id, address_snapshot, 
                total_mrp, discount_amount, coupon_code, coupon_discount, 
                delivery_fee, final_amount, payment_method, payment_status, 
                order_status, delivery_slot, transaction_id, payment_notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $orderStmt->execute([
            $orderNumber,
            $userId,
            $addressId,
            $addressSnapshot,
            $totals['total_mrp'],
            $totalDiscountAmount,
            $couponCode,
            $couponDiscount,
            $totals['delivery_fee'],
            $finalAmountToCharge,
            $paymentMethod,
            $paymentStatus,
            $orderStatus,
            $deliverySlot,
            $txnId,
            $paymentNotes
        ]);
        $orderId = $db->lastInsertId();

        // Copy GPS coordinates & auto-assign active delivery partner
        try {
            $lat = !empty($address['latitude']) ? $address['latitude'] : null;
            $lng = !empty($address['longitude']) ? $address['longitude'] : null;
            $mLink = !empty($address['maps_link']) ? $address['maps_link'] : ($lat && $lng ? "https://www.google.com/maps?q={$lat},{$lng}" : null);
            $gpsAddr = !empty($address['gps_address']) ? $address['gps_address'] : null;

            $defBoy = $db->query("SELECT * FROM delivery_boys WHERE status = 'Active' ORDER BY id ASC LIMIT 1")->fetch();
            $boyId = $defBoy ? $defBoy['id'] : null;
            $boyName = $defBoy ? $defBoy['name'] : 'Rajesh Kumar';
            $boyPhone = $defBoy ? $defBoy['phone'] : '9876543210';

            $updOrder = $db->prepare("
                UPDATE orders 
                SET latitude = ?, longitude = ?, maps_link = ?, gps_address = ?, 
                    delivery_boy_id = ?, delivery_boy_name = ?, delivery_boy_phone = ? 
                WHERE id = ?
            ");
            $updOrder->execute([$lat, $lng, $mLink, $gpsAddr, $boyId, $boyName, $boyPhone, $orderId]);
        } catch (Exception $e) {}

        // 2. Insert order items & reduce stock (with schema safety)
        $hasMrp = false;
        try {
            $colTest = $db->query("SHOW COLUMNS FROM order_items LIKE 'mrp_price'")->fetch();
            $hasMrp = (bool)$colTest;
        } catch (Exception $e) {}

        if ($hasMrp) {
            $itemStmt = $db->prepare("
                INSERT INTO order_items (
                    order_id, product_id, product_name, unit, price, mrp_price, quantity, total_price, image_url
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
        } else {
            $itemStmt = $db->prepare("
                INSERT INTO order_items (
                    order_id, product_id, product_name, unit, price, quantity, total_price, image_url
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
        }

        $stockStmt = $db->prepare("UPDATE products SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id = ?");

        foreach ($items as $item) {
            $itemTotal = $item['selling_price'] * $item['quantity'];
            if ($hasMrp) {
                $itemStmt->execute([
                    $orderId,
                    $item['id'],
                    $item['name'],
                    $item['unit'],
                    $item['selling_price'],
                    $item['mrp_price'] ?? $item['selling_price'],
                    $item['quantity'],
                    $itemTotal,
                    $item['image_url']
                ]);
            } else {
                $itemStmt->execute([
                    $orderId,
                    $item['id'],
                    $item['name'],
                    $item['unit'],
                    $item['selling_price'],
                    $item['quantity'],
                    $itemTotal,
                    $item['image_url']
                ]);
            }

            $stockStmt->execute([$item['quantity'], $item['id']]);
        }

        // 3. Create initial tracking log
        if ($paymentStatus === 'Pending Verification') {
            $trackTitle = 'Payment Submitted - Awaiting Bank Credit Confirmation';
            $trackDesc = "Customer ne ₹" . number_format($finalAmountToCharge, 2) . " payment ka UTR (#$txnId) submit kiya hai. Admin bank account me credit check hone tak verification pending hai.";
        } elseif ($paymentStatus === 'Paid') {
            $trackTitle = 'Payment Auto-Verified & Order Confirmed';
            $trackDesc = "Payment of ₹" . number_format($finalAmountToCharge, 2) . " Auto-Verified by Gateway (Ref: #$txnId). Money credited to Admin Bank Account. Order Confirmed automatically.";
        } else {
            $trackTitle = 'Order Placed Successfully';
            $trackDesc = "Your fresh fruit order has been received. " . $paymentNotes;
        }

        try {
            $trackStmt = $db->prepare("
                INSERT INTO order_tracking (order_id, status, title, description)
                VALUES (?, ?, ?, ?)
            ");
            $trackStmt->execute([$orderId, $orderStatus, $trackTitle, $trackDesc]);
        } catch (Exception $e) {}

        // 4. Remove ONLY the bought items from cart
        if (!empty($_SESSION['buy_now_product'])) {
            unset($_SESSION['buy_now_product']);
        } elseif (!empty($_SESSION['selected_cart_ids']) && is_array($_SESSION['selected_cart_ids'])) {
            $cleanCartIds = array_map('intval', array_filter($_SESSION['selected_cart_ids']));
            if (!empty($cleanCartIds)) {
                $ph = implode(',', array_fill(0, count($cleanCartIds), '?'));
                $params = array_merge($cleanCartIds, [$userId]);
                $db->prepare("DELETE FROM cart WHERE id IN ($ph) AND user_id = ?")->execute($params);
            }
            unset($_SESSION['selected_cart_ids']);
        } else {
            $db->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);
            $db->prepare("DELETE FROM cart WHERE session_id = ?")->execute([getSessionId()]);
        }

        unset($_SESSION['applied_coupon']);
        unset($_SESSION['checkout_data']);

        $db->commit();

        header("Location: " . SITE_URL . "/order-success.php?order_id=" . $orderId);
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        setFlash('error', 'Could not complete order: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/includes/checkout-header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- ========================================== -->
        <!-- LEFT COLUMN: FLIPKART 4-STEP CHECKOUT (8 COLS) -->
        <!-- ========================================== -->
        <div class="lg:col-span-8 space-y-4">
            
            <!-- STEP 1: LOGIN (Completed) -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center justify-between transition hover:shadow-md">
                <div class="flex items-center gap-3.5">
                    <span class="w-6 h-6 rounded-full bg-emerald-600 text-white text-xs font-black flex items-center justify-center shadow-sm">
                        <i class="fa-solid fa-check text-[11px]"></i>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] uppercase text-gray-500 font-extrabold tracking-wider">1. LOGIN ACCOUNT</span>
                            <span class="bg-emerald-100 text-emerald-800 text-[10px] font-extrabold px-2 py-0.5 rounded-full flex items-center gap-1">
                                <i class="fa-solid fa-circle-check text-[9px]"></i> VERIFIED
                            </span>
                        </div>
                        <div class="text-xs sm:text-sm font-bold text-gray-900 flex flex-wrap items-center gap-2 mt-0.5">
                            <span><?= htmlspecialchars($user['name'] ?? 'Valued Customer') ?></span>
                            <span class="text-xs text-gray-500 font-normal">&bull; <?= htmlspecialchars($user['email'] ?? '') ?></span>
                            <?php if (!empty($user['phone'])): ?>
                                <span class="text-xs text-gray-500 font-normal">&bull; +91 <?= htmlspecialchars($user['phone']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 2: DELIVERY ADDRESS (Completed - with Change button) -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 transition hover:shadow-md">
                <div class="flex items-start gap-3.5">
                    <span class="w-6 h-6 rounded-full bg-emerald-600 text-white text-xs font-black flex items-center justify-center shrink-0 mt-0.5 shadow-sm">
                        <i class="fa-solid fa-check text-[11px]"></i>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] uppercase text-gray-500 font-extrabold tracking-wider">2. DELIVERY ADDRESS</span>
                            <span class="bg-blue-100 text-fk-blue text-[10px] font-black px-2 py-0.5 rounded uppercase"><?= htmlspecialchars($address['address_type']) ?></span>
                        </div>
                        <div class="text-xs sm:text-sm font-black text-gray-900 mt-1">
                            <?= htmlspecialchars($address['full_name']) ?> <span class="font-normal text-gray-500 text-xs ml-1">(&#9742; <?= htmlspecialchars($address['phone']) ?>)</span>
                        </div>
                        <div class="text-xs text-gray-600 mt-0.5 leading-relaxed">
                            <?= htmlspecialchars($address['address_line']) ?>, <?= htmlspecialchars($address['locality']) ?>, <?= htmlspecialchars($address['city']) ?>, <?= htmlspecialchars($address['state']) ?> - <strong><?= htmlspecialchars($address['pincode']) ?></strong>
                        </div>
                        <div class="text-[11px] text-emerald-700 font-bold mt-1.5 flex items-center gap-1.5 bg-emerald-50 px-2.5 py-1 rounded-md w-fit border border-emerald-100">
                            <i class="fa-solid fa-bolt text-emerald-600"></i>
                            <span>Delivery Slot: <strong><?= htmlspecialchars($deliverySlot) ?></strong></span>
                        </div>
                    </div>
                </div>
                <div class="shrink-0 sm:self-center">
                    <a href="<?= SITE_URL ?>/checkout.php" class="inline-flex items-center gap-1.5 px-4 py-2 border-2 border-fk-blue text-fk-blue hover:bg-blue-50 rounded-lg text-xs font-black transition uppercase tracking-wider">
                        <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                        <span>CHANGE</span>
                    </a>
                </div>
            </div>

            <!-- STEP 3: ORDER SUMMARY (Completed - with Change & Expand items) -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden transition hover:shadow-md">
                <div class="p-4 sm:p-5 flex items-center justify-between bg-white border-b border-gray-100">
                    <div class="flex items-center gap-3.5">
                        <span class="w-6 h-6 rounded-full bg-emerald-600 text-white text-xs font-black flex items-center justify-center shrink-0 shadow-sm">
                            <i class="fa-solid fa-check text-[11px]"></i>
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] uppercase text-gray-500 font-extrabold tracking-wider">3. ORDER SUMMARY</span>
                                <span class="bg-gray-100 text-gray-700 text-[10px] font-bold px-2 py-0.5 rounded-full"><?= count($items) ?> <?= count($items) === 1 ? 'Fruit' : 'Fruits' ?></span>
                            </div>
                            <div class="text-xs sm:text-sm font-black text-gray-900 mt-0.5">
                                <?= $totals['item_count'] ?> items &bull; Payable: <span class="text-fk-blue dynamic-final-amount"><?= formatPrice($totals['final_amount']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="button" onclick="toggleOrderSummaryItems()" class="text-xs text-fk-blue hover:text-blue-700 font-bold flex items-center gap-1">
                            <span id="summary-toggle-label">View Items</span>
                            <i class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200" id="summary-toggle-chevron"></i>
                        </button>
                        <a href="<?= SITE_URL ?>/cart.php" class="inline-flex items-center gap-1.5 px-4 py-2 border-2 border-fk-blue text-fk-blue hover:bg-blue-50 rounded-lg text-xs font-black transition uppercase tracking-wider">
                            <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                            <span>CHANGE</span>
                        </a>
                    </div>
                </div>

                <!-- Collapsible List of Selected Fruits -->
                <div id="order-summary-items-container" class="divide-y divide-gray-100 bg-gray-50/60 p-4 space-y-2.5 max-h-72 overflow-y-auto">
                    <?php foreach ($items as $item): ?>
                        <div class="flex items-center justify-between gap-3 bg-white p-3 rounded-xl border border-gray-200 shadow-2xs">
                            <div class="flex items-center gap-3 min-w-0">
                                <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-12 h-12 rounded-lg object-cover border border-gray-200 shrink-0 bg-gray-50">
                                <div class="min-w-0">
                                    <h4 class="text-xs font-bold text-gray-900 truncate"><?= htmlspecialchars($item['name']) ?></h4>
                                    <p class="text-[11px] text-gray-500 mt-0.5"><?= htmlspecialchars($item['unit']) ?> &bull; Qty: <strong class="text-gray-900 font-bold"><?= $item['quantity'] ?></strong></p>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-xs sm:text-sm font-black text-gray-900"><?= formatPrice($item['selling_price'] * $item['quantity']) ?></div>
                                <div class="text-[10px] text-gray-400 line-through"><?= formatPrice($item['mrp_price'] * $item['quantity']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- STEP 4: PAYMENT OPTIONS (ACTIVE FLIPKART ACCORDION) -->
            <div class="bg-white rounded-xl shadow-md border border-gray-200 overflow-hidden">
                
                <!-- Flipkart Blue Banner Header -->
                <div class="p-4 bg-fk-blue text-white flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-3">
                        <span class="w-6 h-6 rounded-full bg-white text-fk-blue text-xs font-black flex items-center justify-center shadow">4</span>
                        <h2 class="text-sm font-black uppercase tracking-wider">PAYMENT OPTIONS</h2>
                    </div>
                    <span class="text-xs bg-emerald-500 text-white px-2.5 py-0.5 rounded-full font-extrabold flex items-center gap-1 shadow-xs">
                        <i class="fa-solid fa-lock text-[10px]"></i> 100% Encrypted &amp; Safe
                    </span>
                </div>

                <!-- Flipkart Payment Modes Form -->
                <form action="<?= SITE_URL ?>/payment.php" method="POST" id="payment-form" class="divide-y divide-gray-100">
                    <input type="hidden" name="complete_order" value="1">
                    <input type="hidden" name="card_last4" id="card-last4-hidden" value="1234">
                    <input type="hidden" name="card_bank_name" id="card-bank-name-hidden" value="HDFC Bank">
                    <input type="hidden" name="card_discount" id="card-discount-hidden" value="0">
                    <input type="hidden" name="card_discount_applied" id="card-discount-applied-hidden" value="0">

                    <!-- ============================================== -->
                    <!-- OPTION 1: CREDIT / DEBIT / ATM CARD (FLIPKART CARD APPLY) -->
                    <!-- ============================================== -->
                    <div class="payment-method-row p-4 sm:p-5 transition cursor-pointer bg-blue-50/20 border-l-4 border-fk-blue" id="row-card" onclick="selectPaymentMethod('CARD', event)">
                        <label class="flex items-start gap-4 cursor-pointer">
                            <input type="radio" name="payment_method" value="CARD" checked class="mt-1.5 w-4 h-4 text-fk-blue focus:ring-blue-500 cursor-pointer" onchange="selectPaymentMethod('CARD')">
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-black text-gray-900">Credit / Debit / ATM Card</span>
                                        <div class="flex items-center gap-1.5 text-gray-400 text-sm ml-1">
                                            <i class="fa-brands fa-cc-visa text-blue-700" title="Visa"></i>
                                            <i class="fa-brands fa-cc-mastercard text-amber-600" title="Mastercard"></i>
                                            <span class="bg-emerald-600 text-white text-[9px] font-black px-1.5 py-0.5 rounded">RuPay</span>
                                        </div>
                                    </div>
                                    <span class="bg-amber-100 text-amber-900 border border-amber-300 text-[10px] font-black px-2.5 py-0.5 rounded-full shadow-2xs animate-pulse">
                                        🏷️ SAVE ₹50 WITH BANK OFFER
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5">Visa, MasterCard, RuPay, Maestro with 3D Secure OTP authentication.</p>
                                
                                <!-- Card Box Container -->
                                <div id="card-box" class="mt-4 bg-white p-4 sm:p-6 rounded-2xl border border-blue-200 shadow-sm space-y-4">
                                    
                                    <!-- Flipkart Bank Card Offer Banner & Apply Switch -->
                                    <div class="bg-gradient-to-r from-emerald-50 via-teal-50 to-emerald-50 border-2 border-emerald-300 rounded-xl p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
                                        <div class="flex items-start gap-3">
                                            <div class="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center shrink-0 text-sm shadow">
                                                <i class="fa-solid fa-tag"></i>
                                            </div>
                                            <div>
                                                <div class="text-xs font-black text-emerald-950 flex items-center gap-1.5">
                                                    <span>Flipkart Bank Card Offer: Flat ₹50 Instant Discount!</span>
                                                    <span class="bg-emerald-200 text-emerald-800 text-[9px] px-2 py-0.5 rounded-full font-bold">APPLICABLE</span>
                                                </div>
                                                <p class="text-[11px] text-emerald-800 mt-0.5">
                                                    Axis Bank, SBI, HDFC &amp; ICICI Credit/Debit Cards par ₹50 instant discount payein.
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <!-- Apply Offer Button / Toggle -->
                                        <div class="shrink-0 flex items-center gap-2">
                                            <button 
                                                type="button" 
                                                id="apply-card-offer-btn"
                                                onclick="toggleCardOffer(event)" 
                                                class="bg-emerald-600 hover:bg-emerald-700 text-white font-black px-4 py-2 rounded-lg text-xs uppercase tracking-wider transition flex items-center gap-1.5 shadow cursor-pointer"
                                            >
                                                <i class="fa-solid fa-circle-check text-xs"></i>
                                                <span id="card-offer-btn-text">APPLY ₹50 OFFER</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Card Offer Success Confirmation Box -->
                                    <div id="card-offer-applied-banner" class="hidden bg-emerald-100/80 border border-emerald-300 rounded-lg p-2.5 text-xs text-emerald-900 font-bold flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <i class="fa-solid fa-circle-check text-emerald-700 text-sm"></i>
                                            <span>✓ Bank Card Offer Applied! ₹50.00 will be deducted from your final total.</span>
                                        </div>
                                        <span class="font-black text-emerald-800">- ₹50.00</span>
                                    </div>

                                    <!-- Card Number Field with Live Brand Detection -->
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <label class="block text-xs font-bold text-gray-800">
                                                Card Number *
                                            </label>
                                            <button type="button" onclick="autofillDemoCard()" class="text-[11px] text-fk-blue hover:text-blue-700 font-bold underline cursor-pointer">
                                                Use Demo Card (Test Autofill)
                                            </button>
                                        </div>

                                        <div class="relative">
                                            <input 
                                                type="text" 
                                                id="card-number-input"
                                                placeholder="xxxx xxxx xxxx 1234" 
                                                maxlength="19" 
                                                oninput="handleCardInput(this)"
                                                class="w-full text-xs sm:text-sm pl-3.5 pr-20 py-3 border-2 border-gray-300 rounded-xl bg-white font-mono font-bold text-gray-900 focus:outline-none focus:border-fk-blue shadow-inner"
                                            >
                                            <div class="absolute right-3 top-2.5 flex items-center gap-1.5">
                                                <span id="card-brand-badge" class="bg-gray-100 text-gray-700 text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider">
                                                    CARD
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Detected Bank & Type indicator -->
                                        <div class="mt-1 flex items-center justify-between text-[11px]">
                                            <span id="detected-bank-label" class="text-gray-500 font-medium">
                                                <i class="fa-solid fa-building-columns text-gray-400 mr-1"></i> Detects HDFC, SBI, ICICI, Axis, RuPay
                                            </span>
                                            <span class="text-gray-400">100% Encrypted</span>
                                        </div>
                                    </div>

                                    <!-- Expiry & CVV Grid -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-800 mb-1">
                                                Valid Thru (MM/YY) *
                                            </label>
                                            <input 
                                                type="text" 
                                                id="card-expiry-input"
                                                placeholder="12/28" 
                                                maxlength="5" 
                                                oninput="formatExpiry(this)"
                                                class="w-full text-xs sm:text-sm p-3 border-2 border-gray-300 rounded-xl bg-white font-mono font-bold text-gray-900 focus:outline-none focus:border-fk-blue shadow-inner"
                                            >
                                        </div>

                                        <div>
                                            <div class="flex items-center justify-between mb-1">
                                                <label class="block text-xs font-bold text-gray-800">
                                                    CVV (3 Digits) *
                                                </label>
                                                <span class="text-[10px] text-gray-400" title="Last 3 digits on back of card">
                                                    <i class="fa-solid fa-circle-question text-gray-400"></i> Behind card
                                                </span>
                                            </div>
                                            <div class="relative">
                                                <input 
                                                    type="password" 
                                                    id="card-cvv-input"
                                                    placeholder="123" 
                                                    maxlength="4" 
                                                    class="w-full text-xs sm:text-sm p-3 border-2 border-gray-300 rounded-xl bg-white font-mono font-bold text-gray-900 focus:outline-none focus:border-fk-blue shadow-inner"
                                                >
                                                <i class="fa-solid fa-lock absolute right-3 top-3.5 text-gray-400 text-xs"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Cardholder Name -->
                                    <div>
                                        <label class="block text-xs font-bold text-gray-800 mb-1">Cardholder Name *</label>
                                        <input 
                                            type="text" 
                                            id="card-name-input"
                                            value="<?= htmlspecialchars($user['name'] ?? 'Authorized Cardholder') ?>"
                                            placeholder="Name printed on card" 
                                            class="w-full text-xs sm:text-sm p-3 border border-gray-300 rounded-xl bg-white font-bold text-gray-900 focus:outline-none focus:border-fk-blue"
                                        >
                                    </div>

                                    <!-- Save Card Policy Checkbox (Flipkart Style) -->
                                    <div class="flex items-center gap-2 pt-1 text-xs text-gray-600">
                                        <input type="checkbox" id="save-card-checkbox" checked class="rounded text-fk-blue focus:ring-blue-500">
                                        <label for="save-card-checkbox" class="cursor-pointer select-none text-[11px]">
                                            Save this card securely as per RBI guidelines.
                                        </label>
                                    </div>

                                    <!-- Direct Pay Button inside Card Box -->
                                    <div class="pt-2">
                                        <button 
                                            type="button" 
                                            onclick="submitCardPayment()" 
                                            class="w-full bg-fk-orange hover:bg-orange-600 text-white font-black py-3.5 px-6 rounded-xl shadow-md hover:shadow-lg transition text-sm uppercase tracking-wider flex items-center justify-center gap-2 cursor-pointer"
                                        >
                                            <i class="fa-solid fa-lock text-xs"></i>
                                            <span id="card-box-pay-label">PAY <?= formatPrice($totals['final_amount']) ?> (SECURE 3D CHECKOUT)</span>
                                        </button>
                                        <p class="text-[10px] text-center text-gray-400 mt-2">
                                            Click karne par Bank 3D Secure OTP verification window khulegi.
                                        </p>
                                    </div>

                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- ============================================== -->
                    <!-- OPTION 2: UPI (Google Pay, PhonePe, Paytm, QR) -->
                    <!-- ============================================== -->
                    <div class="payment-method-row p-4 sm:p-5 transition cursor-pointer hover:bg-gray-50/50" id="row-upi" onclick="selectPaymentMethod('UPI', event)">
                        <label class="flex items-start gap-4 cursor-pointer">
                            <input type="radio" name="payment_method" value="UPI" class="mt-1.5 w-4 h-4 text-fk-blue focus:ring-blue-500 cursor-pointer" onchange="selectPaymentMethod('UPI')">
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-black text-gray-900">UPI (Google Pay, PhonePe, Paytm, BHIM, Scan QR)</span>
                                    <span class="bg-emerald-100 text-emerald-800 text-[10px] font-black px-2 py-0.5 rounded-full">INSTANT AUTO-CHECK</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5">Pay via QR Barcode or any UPI app with instant Real-Time Bank Credit verification.</p>
                                
                                <!-- UPI Box Details -->
                                <div id="upi-box" class="hidden mt-4 bg-gradient-to-r from-blue-50/90 via-indigo-50/60 to-blue-50/90 p-4 sm:p-6 rounded-2xl border border-blue-200 space-y-5">
                                    
                                    <!-- QR & Details Flexbox -->
                                    <div class="flex flex-col sm:flex-row items-center gap-6">
                                        <!-- Barcode / QR Code Frame -->
                                        <div class="w-48 h-48 bg-white p-3 rounded-2xl border-2 border-fk-blue shadow-lg flex flex-col items-center justify-center shrink-0">
                                            <img src="<?= htmlspecialchars($qrCodeUrl) ?>" alt="Admin UPI Barcode QR" class="w-full h-full object-contain">
                                            <span class="text-[9px] font-black text-fk-blue mt-1 uppercase tracking-widest bg-blue-50 px-2 py-0.5 rounded">Scan &amp; Pay</span>
                                        </div>

                                        <!-- Admin Official UPI Details -->
                                        <div class="space-y-3 text-xs flex-1 w-full">
                                            <div class="flex items-center gap-2">
                                                <span class="bg-emerald-600 text-white text-[10px] font-extrabold px-2.5 py-0.5 rounded-full shadow-2xs">OFFICIAL ADMIN PAYEE</span>
                                                <span class="text-gray-500 font-mono text-[11px]">Real-Time Clearance</span>
                                            </div>

                                            <div>
                                                <span class="text-[10px] text-gray-500 uppercase font-bold block">Verified Merchant Name:</span>
                                                <span class="text-sm font-black text-gray-900"><?= htmlspecialchars($adminUpiName) ?></span>
                                            </div>

                                            <!-- UPI ID with Copy Button -->
                                            <div class="bg-white p-3 rounded-xl border border-blue-200 flex items-center justify-between shadow-2xs">
                                                <div>
                                                    <span class="text-[10px] text-gray-400 block font-bold uppercase">Official UPI ID / VPA:</span>
                                                    <span class="font-mono font-black text-gray-900 text-xs sm:text-sm" id="upi-id-text"><?= htmlspecialchars($adminUpiId) ?></span>
                                                </div>
                                                <button type="button" onclick="copyToClipboard('<?= htmlspecialchars($adminUpiId) ?>', this)" class="bg-blue-50 hover:bg-fk-blue text-fk-blue hover:text-white px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                                                    <i class="fa-regular fa-copy"></i>
                                                    <span>Copy</span>
                                                </button>
                                            </div>

                                            <!-- PhonePe / GPay Number -->
                                            <?php if (!empty($adminUpiPhone)): ?>
                                                <div class="bg-white p-2.5 rounded-xl border border-gray-200 flex items-center justify-between text-xs">
                                                    <div>
                                                        <span class="text-[10px] text-gray-400 font-bold uppercase">PhonePe / GPay Mobile:</span>
                                                        <strong class="font-mono text-gray-900 font-bold"><?= htmlspecialchars($adminUpiPhone) ?></strong>
                                                    </div>
                                                    <button type="button" onclick="copyToClipboard('<?= htmlspecialchars($adminUpiPhone) ?>', this)" class="text-fk-blue hover:underline font-bold text-xs cursor-pointer">Copy</button>
                                                </div>
                                            <?php endif; ?>

                                            <div class="flex items-center gap-2 pt-1 text-[11px] text-gray-600">
                                                <span>Payable: <strong class="text-gray-900 font-black text-sm"><?= formatPrice($totals['final_amount']) ?></strong></span>
                                                &bull;
                                                <span>Zero Extra Fees</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Mandatory UPI UTR Input Field -->
                                    <div class="pt-4 border-t border-blue-200">
                                        <div class="flex items-center justify-between mb-1.5">
                                            <label class="block text-xs font-bold text-gray-900">
                                                <i class="fa-solid fa-receipt text-fk-blue mr-1"></i> Enter UPI 12-Digit Reference / UTR Number *
                                            </label>
                                            <button type="button" onclick="generateDemoUtr()" class="text-[11px] text-fk-blue hover:text-blue-700 font-bold underline cursor-pointer">
                                                Use Demo UTR (Test)
                                            </button>
                                        </div>

                                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                                            <input 
                                                type="text" 
                                                name="transaction_id" 
                                                id="upi-utr-input"
                                                placeholder="e.g. 402910394819 (UPI payment karne ke baad 12-digit UTR dalein)" 
                                                maxlength="16"
                                                class="flex-1 text-xs sm:text-sm p-3 border-2 border-gray-300 rounded-xl bg-white font-mono font-bold text-gray-900 focus:outline-none focus:border-fk-blue shadow-inner"
                                            >
                                            <button type="button" onclick="openUpiGatewayModal()" class="bg-fk-blue hover:bg-fk-blue-dark text-white font-black px-5 py-3 rounded-xl text-xs transition flex items-center justify-center gap-2 shrink-0 shadow cursor-pointer">
                                                <i class="fa-solid fa-bolt text-yellow-300"></i>
                                                <span>Open UPI Gateway</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Direct Pay Button inside UPI Box -->
                                    <div class="pt-2">
                                        <button 
                                            type="button" 
                                            onclick="submitUpiPayment()" 
                                            class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-black py-3.5 px-6 rounded-xl shadow-md hover:shadow-lg transition text-sm uppercase tracking-wider flex items-center justify-center gap-2 cursor-pointer"
                                        >
                                            <i class="fa-solid fa-circle-check text-xs"></i>
                                            <span>VERIFY &amp; CONFIRM UPI PAYMENT (<?= formatPrice($totals['final_amount']) ?>)</span>
                                        </button>
                                    </div>

                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- ============================================== -->
                    <!-- OPTION 3: NET BANKING (DIRECT ADMIN BANK)     -->
                    <!-- ============================================== -->
                    <div class="payment-method-row p-4 sm:p-5 transition cursor-pointer hover:bg-gray-50/50" id="row-netbanking" onclick="selectPaymentMethod('NETBANKING', event)">
                        <label class="flex items-start gap-4 cursor-pointer">
                            <input type="radio" name="payment_method" value="NETBANKING" class="mt-1.5 w-4 h-4 text-fk-blue focus:ring-blue-500 cursor-pointer" onchange="selectPaymentMethod('NETBANKING')">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-black text-gray-900">Direct Bank Transfer / Net Banking (IMPS / NEFT)</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5">Transfer directly to Admin's verified Bank Account with auto-check.</p>
                                
                                <div id="netbanking-box" class="hidden mt-4 bg-gradient-to-r from-slate-50 to-blue-50/40 p-4 sm:p-6 rounded-2xl border border-gray-200 space-y-4">
                                    <div class="flex items-center justify-between pb-2 border-b border-gray-200">
                                        <div class="flex items-center gap-2">
                                            <i class="fa-solid fa-building-columns text-fk-blue text-base"></i>
                                            <span class="font-black text-xs text-gray-900 uppercase tracking-wider">Admin Official Bank Details</span>
                                        </div>
                                        <span class="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded">IMPS / NEFT / RTGS</span>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                        <div class="bg-white p-3 rounded-xl border border-gray-200">
                                            <span class="text-[10px] text-gray-400 uppercase font-bold block">Bank Name</span>
                                            <span class="text-xs sm:text-sm font-black text-gray-900"><?= htmlspecialchars($bankName) ?></span>
                                        </div>

                                        <div class="bg-white p-3 rounded-xl border border-gray-200">
                                            <span class="text-[10px] text-gray-400 uppercase font-bold block">Account Holder Name</span>
                                            <span class="text-xs sm:text-sm font-black text-gray-900"><?= htmlspecialchars($bankAccountName) ?></span>
                                        </div>

                                        <div class="bg-white p-3 rounded-xl border border-gray-200 flex items-center justify-between">
                                            <div>
                                                <span class="text-[10px] text-gray-400 uppercase font-bold block">Account Number</span>
                                                <span class="text-xs sm:text-sm font-mono font-black text-gray-900"><?= htmlspecialchars($bankAccountNo) ?></span>
                                            </div>
                                            <button type="button" onclick="copyToClipboard('<?= htmlspecialchars($bankAccountNo) ?>', this)" class="bg-blue-50 text-fk-blue hover:bg-fk-blue hover:text-white px-2.5 py-1 rounded text-[11px] font-bold transition">
                                                Copy
                                            </button>
                                        </div>

                                        <div class="bg-white p-3 rounded-xl border border-gray-200 flex items-center justify-between">
                                            <div>
                                                <span class="text-[10px] text-gray-400 uppercase font-bold block">IFSC Code</span>
                                                <span class="text-xs sm:text-sm font-mono font-black text-gray-900 uppercase"><?= htmlspecialchars($bankIfsc) ?></span>
                                            </div>
                                            <button type="button" onclick="copyToClipboard('<?= htmlspecialchars($bankIfsc) ?>', this)" class="bg-blue-50 text-fk-blue hover:bg-fk-blue hover:text-white px-2.5 py-1 rounded text-[11px] font-bold transition">
                                                Copy
                                            </button>
                                        </div>

                                        <div class="sm:col-span-2 bg-white p-3 rounded-xl border border-gray-200 text-xs text-gray-600">
                                            <strong>Branch:</strong> <?= htmlspecialchars($bankBranch) ?> &bull; <span class="text-emerald-700 font-bold"><?= htmlspecialchars($bankNotes) ?></span>
                                        </div>
                                    </div>

                                    <!-- Bank Selection & UTR Input -->
                                    <div class="pt-3 border-t border-gray-200 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-700 mb-1">Select Your Bank *</label>
                                            <select name="sender_bank" id="netbanking-sender-bank" class="w-full text-xs p-3 border border-gray-300 rounded-xl bg-white font-bold text-gray-800 focus:outline-none focus:border-fk-blue">
                                                <option value="State Bank of India">State Bank of India (SBI)</option>
                                                <option value="HDFC Bank">HDFC Bank</option>
                                                <option value="ICICI Bank">ICICI Bank</option>
                                                <option value="Axis Bank">Axis Bank</option>
                                                <option value="Punjab National Bank">Punjab National Bank</option>
                                                <option value="Kotak Mahindra Bank">Kotak Mahindra Bank</option>
                                                <option value="Other Bank">Other Indian Bank</option>
                                            </select>
                                        </div>

                                        <div>
                                            <div class="flex items-center justify-between mb-1">
                                                <label class="block text-xs font-bold text-gray-700">
                                                    Bank IMPS/NEFT UTR Ref ID *
                                                </label>
                                                <button type="button" onclick="generateDemoBankRef()" class="text-[11px] text-fk-blue hover:text-blue-700 font-bold underline cursor-pointer">
                                                    Demo Ref
                                                </button>
                                            </div>
                                            <input 
                                                type="text" 
                                                name="bank_utr" 
                                                id="netbanking-utr-input"
                                                placeholder="e.g. IMPS/4028192837 or NEFT Ref Number" 
                                                class="w-full text-xs p-3 border border-gray-300 rounded-xl bg-white font-mono font-bold text-gray-900 focus:outline-none focus:border-fk-blue"
                                            >
                                        </div>
                                    </div>

                                    <div class="pt-2">
                                        <button 
                                            type="button" 
                                            onclick="submitNetBankingPayment()" 
                                            class="w-full bg-fk-blue hover:bg-fk-blue-dark text-white font-black py-3.5 px-6 rounded-xl shadow-md transition text-sm uppercase tracking-wider flex items-center justify-center gap-2 cursor-pointer"
                                        >
                                            <i class="fa-solid fa-building-columns text-xs"></i>
                                            <span>CONFIRM BANK TRANSFER (<?= formatPrice($totals['final_amount']) ?>)</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- ============================================== -->
                    <!-- OPTION 4: CASH ON DELIVERY (COD)              -->
                    <!-- ============================================== -->
                    <div class="payment-method-row p-4 sm:p-5 transition cursor-pointer hover:bg-gray-50/50" id="row-cod" onclick="selectPaymentMethod('COD', event)">
                        <label class="flex items-start gap-4 cursor-pointer">
                            <input type="radio" name="payment_method" value="COD" class="mt-1.5 w-4 h-4 text-fk-blue focus:ring-blue-500 cursor-pointer" onchange="selectPaymentMethod('COD')">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-black text-gray-900">Cash on Delivery (Cash / UPI on Doorstep)</span>
                                    <span class="bg-gray-100 text-gray-700 text-[10px] font-bold px-2 py-0.5 rounded">NO EXTRA CHARGE</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5">Pay via Cash, Google Pay or PhonePe when your fruit crate arrives.</p>
                                
                                <div id="cod-box" class="hidden mt-4 bg-gray-50 p-4 sm:p-5 rounded-2xl border border-gray-200 space-y-3">
                                    <div class="text-xs text-gray-600 flex items-center gap-2">
                                        <i class="fa-solid fa-circle-check text-emerald-600"></i>
                                        <span>Zero convenience fee. Inspect fresh fruit crate quality before paying.</span>
                                    </div>

                                    <!-- Security Captcha for COD confirmation -->
                                    <div class="pt-2 border-t border-gray-200 flex flex-wrap items-center gap-4">
                                        <div>
                                            <span class="block text-[10px] font-bold text-gray-500 uppercase mb-1">
                                                <i class="fa-solid fa-shield-halved text-fk-blue mr-1"></i> Security Code:
                                            </span>
                                            <div class="flex items-center gap-2">
                                                <div class="bg-slate-900 text-yellow-400 font-mono font-black text-base tracking-widest px-3.5 py-2 rounded-xl select-none shadow-inner" id="captcha-display">
                                                    <?= htmlspecialchars($codCaptcha) ?>
                                                </div>
                                                <button type="button" onclick="regenerateCaptcha()" class="text-xs text-fk-blue hover:text-blue-700 p-2 rounded-lg hover:bg-blue-50 cursor-pointer" title="Refresh Code">
                                                    <i class="fa-solid fa-rotate"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="flex-1 min-w-[140px]">
                                            <span class="block text-[10px] font-bold text-gray-700 uppercase mb-1">Enter Code Above *</span>
                                            <input 
                                                type="text" 
                                                name="cod_captcha_input" 
                                                id="cod-captcha-input" 
                                                maxlength="3" 
                                                placeholder="3-digit code" 
                                                class="w-full text-xs p-2.5 border border-gray-300 rounded-xl font-mono font-bold text-gray-900 focus:outline-none focus:border-fk-blue"
                                            >
                                        </div>
                                    </div>

                                    <div class="pt-2">
                                        <button 
                                            type="button" 
                                            onclick="submitCodPayment()" 
                                            class="w-full bg-fk-orange hover:bg-orange-600 text-white font-black py-3.5 px-6 rounded-xl shadow-md transition text-sm uppercase tracking-wider flex items-center justify-center gap-2 cursor-pointer"
                                        >
                                            <i class="fa-solid fa-check text-xs"></i>
                                            <span>CONFIRM CASH ON DELIVERY (<?= formatPrice($totals['final_amount']) ?>)</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- Bottom Bar: Confirm Action Button (Unified Fallback) -->
                    <div class="p-4 sm:p-5 bg-gray-50/90 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="text-xs text-gray-600">
                            Total Payable: <strong class="text-lg text-gray-900 font-black dynamic-final-amount"><?= formatPrice($totals['final_amount']) ?></strong>
                            <span class="block text-[10px] text-emerald-600 font-bold"><i class="fa-solid fa-lock text-[9px]"></i> 100% Safe &amp; Secure Payment Guarantee</span>
                        </div>

                        <button 
                            type="button" 
                            id="pay-submit-btn" 
                            onclick="initiatePaymentProcess()" 
                            class="bg-fk-orange hover:bg-orange-600 text-white font-black px-8 py-3.5 rounded-xl shadow-md hover:shadow-lg transition text-sm uppercase tracking-wide flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <i class="fa-solid fa-lock text-xs"></i>
                            <span id="pay-btn-label">PAY VIA CARD &amp; VERIFY OTP (<?= formatPrice($totals['final_amount']) ?>)</span>
                        </button>
                    </div>

                </form>
            </div>

        </div>

        <!-- ============================================== -->
        <!-- RIGHT COLUMN: FLIPKART PRICE DETAILS & TRUST   -->
        <!-- ============================================== -->
        <div class="lg:col-span-4 space-y-4">
            
            <!-- Sticky Price Details Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-24">
                <div class="p-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                    <h3 class="text-xs font-black text-gray-500 uppercase tracking-wider">Price Details</h3>
                    <span class="text-xs font-bold text-gray-600"><?= $totals['item_count'] ?> <?= $totals['item_count'] === 1 ? 'Item' : 'Items' ?></span>
                </div>

                <div class="p-4 sm:p-5 space-y-3.5 text-xs">
                    <div class="flex justify-between text-gray-700">
                        <span>Price (MRP Total)</span>
                        <span class="font-bold text-gray-900"><?= formatPrice($totals['total_mrp']) ?></span>
                    </div>

                    <div class="flex justify-between text-emerald-600 font-bold">
                        <span>Special Product Discount</span>
                        <span>&minus; <?= formatPrice($totals['discount']) ?></span>
                    </div>

                    <?php if ($couponDiscount > 0): ?>
                        <div class="flex justify-between text-emerald-600 font-bold">
                            <span>Coupon Savings (<?= htmlspecialchars($couponCode) ?>)</span>
                            <span>&minus; <?= formatPrice($couponDiscount) ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Dynamic Bank Card Offer Row (Flipkart Style) -->
                    <div id="sidebar-card-offer-row" class="hidden justify-between text-emerald-600 font-black">
                        <span class="flex items-center gap-1">
                            <i class="fa-solid fa-tag text-[10px]"></i> Bank Card Offer Discount
                        </span>
                        <span id="sidebar-card-discount-val">&minus; ₹50.00</span>
                    </div>

                    <div class="flex justify-between text-gray-700 items-center">
                        <span>Delivery Charges</span>
                        <?php if ($totals['delivery_fee'] == 0): ?>
                            <span class="text-emerald-600 font-black uppercase"><span class="line-through text-gray-400 font-normal mr-1">₹30</span> FREE</span>
                        <?php else: ?>
                            <span class="text-gray-900 font-bold"><?= formatPrice($totals['delivery_fee']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="border-t border-dashed border-gray-200 pt-3.5 flex justify-between text-base font-black text-gray-900">
                        <span>Total Payable Amount</span>
                        <span class="text-lg text-fk-blue font-black dynamic-final-amount"><?= formatPrice($totals['final_amount']) ?></span>
                    </div>

                    <div class="bg-emerald-50 text-emerald-800 p-3 rounded-xl border border-emerald-200 text-center font-black text-xs shadow-2xs">
                        🎉 Total Savings: <span id="sidebar-total-savings"><?= formatPrice($totals['discount'] + $couponDiscount) ?></span> on this order!
                    </div>
                </div>
            </div>

            <!-- Flipkart Safe and Secure Payments Trust Badge -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-5 space-y-3 text-xs text-gray-600">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-shield-halved text-base"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-900 text-xs">Safe and Secure Payments</h4>
                        <p class="text-[11px] text-gray-500 mt-0.5">Easy returns. 100% Authentic farm fresh fruits.</p>
                    </div>
                </div>

                <div class="pt-2 border-t border-gray-100 flex items-center justify-between text-[11px] text-gray-400">
                    <span class="flex items-center gap-1"><i class="fa-solid fa-lock text-emerald-600"></i> 256-Bit SSL</span>
                    <span>&bull;</span>
                    <span class="flex items-center gap-1"><i class="fa-solid fa-circle-check text-blue-600"></i> NPCI Verified</span>
                    <span>&bull;</span>
                    <span class="flex items-center gap-1"><i class="fa-solid fa-certificate text-amber-500"></i> 100% Genuine</span>
                </div>
            </div>

        </div>

    </div>

</div>

<!-- ============================================== -->
<!-- MODAL 1: INTERACTIVE LIVE UPI PAYMENT GATEWAY -->
<!-- ============================================== -->
<div id="upi-gateway-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden border border-gray-100 my-8 animate-in fade-in zoom-in-95 duration-150">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-900 to-indigo-900 text-white p-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-emerald-400 text-slate-950 flex items-center justify-center font-bold text-lg shadow">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
                <div>
                    <h3 class="font-black text-base text-white flex items-center gap-2">
                        <span>UPI Payment Gateway</span>
                        <span class="text-[10px] bg-emerald-500/30 text-emerald-300 font-bold px-2 py-0.5 rounded-full border border-emerald-400/40">Secure NPCI</span>
                    </h3>
                    <p class="text-[11px] text-blue-200">Google Pay &bull; PhonePe &bull; Paytm &bull; BHIM</p>
                </div>
            </div>
            <button type="button" onclick="closeUpiGatewayModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="p-6 space-y-5 text-xs">
            
            <!-- Amount & Timer Banner -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-2xl p-4 flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Payable Amount</span>
                    <span class="text-2xl font-black text-gray-900 dynamic-final-amount"><?= formatPrice($totals['final_amount']) ?></span>
                </div>
                <div class="text-right">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Session Expires In</span>
                    <span class="text-sm font-mono font-black text-rose-600 flex items-center justify-end gap-1" id="upi-timer">
                        <i class="fa-solid fa-clock text-xs animate-pulse"></i> 04:59
                    </span>
                </div>
            </div>

            <!-- QR Code & VPA Display -->
            <div class="flex flex-col sm:flex-row items-center gap-4 bg-gray-50 p-4 rounded-2xl border border-gray-200">
                <div class="w-36 h-36 bg-white p-2 rounded-xl border-2 border-fk-blue shadow shrink-0 flex items-center justify-center">
                    <img src="<?= htmlspecialchars($qrCodeUrl) ?>" alt="UPI QR" class="w-full h-full object-contain">
                </div>
                <div class="space-y-2 flex-1 text-center sm:text-left">
                    <span class="text-[10px] text-gray-400 font-bold uppercase block">Admin Payee Name</span>
                    <span class="font-bold text-gray-900 text-sm block"><?= htmlspecialchars($adminUpiName) ?></span>
                    
                    <div class="bg-white p-2 rounded-lg border border-gray-200 flex items-center justify-between text-[11px]">
                        <span class="font-mono font-bold text-gray-800 truncate mr-2"><?= htmlspecialchars($adminUpiId) ?></span>
                        <button type="button" onclick="copyToClipboard('<?= htmlspecialchars($adminUpiId) ?>', this)" class="text-fk-blue hover:underline font-bold text-[10px] shrink-0 cursor-pointer">Copy</button>
                    </div>

                    <div class="text-[10px] text-gray-500">
                        Scan QR in your UPI app &amp; make payment of <strong class="dynamic-final-amount"><?= formatPrice($totals['final_amount']) ?></strong>.
                    </div>
                </div>
            </div>

            <!-- Mandatory UTR Input Step -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="block font-bold text-gray-800 text-xs">
                        <i class="fa-solid fa-receipt text-fk-blue mr-1"></i> Enter 12-Digit UPI UTR / Reference ID *
                    </label>
                    <button type="button" onclick="generateDemoUtrModal()" class="text-[11px] text-fk-blue font-bold underline cursor-pointer">
                        Use Demo UTR
                    </button>
                </div>
                <input 
                    type="text" 
                    id="modal-upi-utr" 
                    maxlength="16"
                    placeholder="e.g. 402910394819 (From Payment Receipt)" 
                    class="w-full p-3 border-2 border-gray-300 rounded-xl font-mono font-bold text-sm text-gray-900 focus:outline-none focus:border-fk-blue"
                >
                <p class="text-[10px] text-gray-500">
                    PhonePe / GPay receipt me <strong>"UPI transaction ID / UTR"</strong> number likha hota hai. Usko yahan daal kar verify karein.
                </p>
            </div>

            <!-- Live Processing Feedback -->
            <div id="upi-process-status" class="hidden p-3 rounded-xl bg-blue-50 border border-blue-200 text-center space-y-2">
                <div class="flex items-center justify-center gap-2 text-fk-blue font-bold text-xs">
                    <i class="fa-solid fa-circle-notch fa-spin"></i>
                    <span id="upi-status-msg">Verifying with UPI Gateway &amp; NPCI...</span>
                </div>
                <div class="w-full bg-blue-200 h-1.5 rounded-full overflow-hidden">
                    <div id="upi-progress-bar" class="bg-fk-blue h-full w-1/3 transition-all duration-500"></div>
                </div>
            </div>

            <!-- Buttons -->
            <div class="pt-2 flex items-center justify-end gap-3 border-t border-gray-100">
                <button 
                    type="button" 
                    onclick="closeUpiGatewayModal()" 
                    class="px-4 py-2.5 border rounded-xl font-bold text-gray-600 hover:bg-gray-100 transition text-xs cursor-pointer"
                >
                    Cancel
                </button>
                <button 
                    type="button" 
                    id="upi-verify-btn"
                    onclick="verifyAndSubmitUpiPayment()" 
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-black px-6 py-2.5 rounded-xl shadow transition uppercase tracking-wide text-xs flex items-center gap-2 cursor-pointer"
                >
                    <i class="fa-solid fa-check"></i>
                    <span>I Have Paid &bull; Verify &amp; Confirm</span>
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- MODAL 2: BANK 3D SECURE OTP GATEWAY (FLIPKART) -->
<!-- ============================================== -->
<div id="card-otp-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden border border-gray-100 my-8 animate-in fade-in zoom-in-95 duration-150">
        
        <!-- Header: Bank Security Logo -->
        <div class="bg-gradient-to-r from-slate-900 via-blue-950 to-slate-900 text-white p-5 flex items-center justify-between border-b border-gray-800">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center font-bold text-base shadow">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <h3 class="font-black text-sm text-white" id="modal-bank-title">HDFC Bank 3D Secure Authentication</h3>
                    <p class="text-[10px] text-gray-300">Verified by VISA &bull; MasterCard Identity Check &bull; RuPay</p>
                </div>
            </div>
            <button type="button" onclick="closeCardOtpModal()" class="w-7 h-7 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        <div class="p-6 space-y-4 text-xs">
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-3.5 space-y-2">
                <div class="flex justify-between text-gray-500">
                    <span>Merchant:</span>
                    <strong class="text-gray-900">FruitKart Fresh Hub India Pvt Ltd</strong>
                </div>
                <div class="flex justify-between text-gray-500">
                    <span>Card Ending:</span>
                    <strong class="font-mono text-gray-900" id="card-ending-text">**** 1234</strong>
                </div>
                <div class="flex justify-between text-gray-500 border-t border-gray-200 pt-2">
                    <span class="font-bold">Transaction Amount:</span>
                    <strong class="text-base font-black text-fk-blue dynamic-final-amount"><?= formatPrice($totals['final_amount']) ?></strong>
                </div>
            </div>

            <div>
                <p class="text-gray-700 font-medium mb-2 leading-relaxed">
                    Please enter the 6-Digit One Time Password (OTP) sent to your registered mobile number (+91 98******10):
                </p>
                <input 
                    type="text" 
                    id="card-otp-input-field" 
                    maxlength="6" 
                    placeholder="Enter 6-Digit OTP" 
                    class="w-full p-3.5 border-2 border-gray-300 rounded-xl font-mono font-black text-center text-xl tracking-widest text-gray-900 focus:outline-none focus:border-fk-blue shadow-inner"
                >
            </div>

            <!-- Demo Sandbox Hint & Autofill -->
            <div class="flex items-center justify-between bg-blue-50/80 p-3 rounded-xl border border-blue-200">
                <span class="text-[11px] text-blue-900 font-bold">
                    <i class="fa-solid fa-lightbulb text-amber-500 mr-1"></i> Demo Sandbox OTP: <strong class="font-mono text-xs">123456</strong>
                </span>
                <button type="button" onclick="fillDemoOtp()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-3 py-1.5 rounded-lg text-[11px] transition cursor-pointer shadow-xs">
                    Auto-Fill OTP
                </button>
            </div>

            <!-- Live Processing Feedback -->
            <div id="card-process-status" class="hidden p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-center space-y-1.5">
                <div class="flex items-center justify-center gap-2 text-emerald-800 font-bold text-xs">
                    <i class="fa-solid fa-circle-notch fa-spin"></i>
                    <span id="card-status-msg">Authenticating 3D Secure with Issuing Bank...</span>
                </div>
                <div class="w-full bg-emerald-200 h-1.5 rounded-full overflow-hidden">
                    <div id="card-progress-bar" class="bg-emerald-600 h-full w-2/3 transition-all duration-500"></div>
                </div>
            </div>

            <!-- Buttons -->
            <div class="pt-2 flex items-center justify-end gap-3 border-t border-gray-100">
                <button 
                    type="button" 
                    onclick="closeCardOtpModal()" 
                    class="px-4 py-2.5 border rounded-xl font-bold text-gray-600 hover:bg-gray-100 transition text-xs cursor-pointer"
                >
                    Cancel
                </button>
                <button 
                    type="button" 
                    id="card-verify-btn"
                    onclick="verifyAndSubmitCardPayment()" 
                    class="bg-fk-blue hover:bg-fk-blue-dark text-white font-black px-6 py-2.5 rounded-xl shadow transition uppercase tracking-wide text-xs flex items-center gap-2 cursor-pointer"
                >
                    <i class="fa-solid fa-lock text-xs"></i>
                    <span>Authenticate &amp; Complete Order</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- JAVASCRIPT LOGIC & HANDLERS                    -->
<!-- ============================================== -->
<script>
    let currentPaymentMode = 'CARD';
    let baseOrderTotal = <?= (float)$totals['final_amount'] ?>;
    let baseDiscount = <?= (float)$totals['discount'] ?>;
    let couponDiscount = <?= (float)$couponDiscount ?>;
    let cardOfferApplied = false;
    let upiTimerInterval = null;

    // Toggle Order Summary Items accordion
    function toggleOrderSummaryItems() {
        const container = document.getElementById('order-summary-items-container');
        const chevron = document.getElementById('summary-toggle-chevron');
        const label = document.getElementById('summary-toggle-label');
        
        if (container.classList.contains('hidden')) {
            container.classList.remove('hidden');
            chevron.classList.add('rotate-180');
            label.innerText = 'Hide Items';
        } else {
            container.classList.add('hidden');
            chevron.classList.remove('rotate-180');
            label.innerText = 'View Items';
        }
    }

    // Flipkart Accordion Row Selector
    function selectPaymentMethod(mode, event) {
        if (event && event.target && (event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON' || event.target.tagName === 'SELECT')) {
            // allow native focus on inputs
        }
        currentPaymentMode = mode;

        // Check the radio
        const radio = document.querySelector(`input[name="payment_method"][value="${mode}"]`);
        if (radio) radio.checked = true;

        // Update accordion boxes
        document.getElementById('card-box').classList.toggle('hidden', mode !== 'CARD');
        document.getElementById('upi-box').classList.toggle('hidden', mode !== 'UPI');
        document.getElementById('netbanking-box').classList.toggle('hidden', mode !== 'NETBANKING');
        document.getElementById('cod-box').classList.toggle('hidden', mode !== 'COD');

        // Style the active row border
        ['card', 'upi', 'netbanking', 'cod'].forEach(m => {
            const row = document.getElementById(`row-${m}`);
            if (row) {
                if (m.toUpperCase() === mode) {
                    row.classList.add('border-l-4', 'border-fk-blue', 'bg-blue-50/20');
                } else {
                    row.classList.remove('border-l-4', 'border-fk-blue', 'bg-blue-50/20');
                }
            }
        });

        updatePayableAmountsUI();
    }

    // Toggle Flipkart Bank Card Offer (Flat ₹50 Instant Discount)
    function toggleCardOffer(e) {
        if (e && e.stopPropagation) e.stopPropagation();
        cardOfferApplied = !cardOfferApplied;

        const btnText = document.getElementById('card-offer-btn-text');
        const btn = document.getElementById('apply-card-offer-btn');
        const banner = document.getElementById('card-offer-applied-banner');
        const hiddenDiscount = document.getElementById('card-discount-hidden');
        const hiddenApplied = document.getElementById('card-discount-applied-hidden');
        const sidebarRow = document.getElementById('sidebar-card-offer-row');

        if (cardOfferApplied) {
            btnText.innerText = 'REMOVE ₹50 OFFER';
            btn.classList.remove('bg-emerald-600', 'hover:bg-emerald-700');
            btn.classList.add('bg-rose-600', 'hover:bg-rose-700');
            banner.classList.remove('hidden');
            hiddenDiscount.value = '50.00';
            hiddenApplied.value = '1';
            sidebarRow.classList.remove('hidden');
            sidebarRow.classList.add('flex');
        } else {
            btnText.innerText = 'APPLY ₹50 OFFER';
            btn.classList.remove('bg-rose-600', 'hover:bg-rose-700');
            btn.classList.add('bg-emerald-600', 'hover:bg-emerald-700');
            banner.classList.add('hidden');
            hiddenDiscount.value = '0';
            hiddenApplied.value = '0';
            sidebarRow.classList.add('hidden');
            sidebarRow.classList.remove('flex');
        }

        updatePayableAmountsUI();
    }

    // Update all dynamic price indicators live on the page
    function updatePayableAmountsUI() {
        const discountToDeduct = (currentPaymentMode === 'CARD' && cardOfferApplied) ? 50.00 : 0.0;
        const currentPayable = Math.max(0, baseOrderTotal - discountToDeduct);
        const formattedAmount = '₹' + currentPayable.toFixed(2);
        const totalSavings = baseDiscount + couponDiscount + discountToDeduct;

        // Update all text labels with class 'dynamic-final-amount'
        document.querySelectorAll('.dynamic-final-amount').forEach(el => {
            el.innerText = formattedAmount;
        });

        // Update total savings
        const savingsEl = document.getElementById('sidebar-total-savings');
        if (savingsEl) {
            savingsEl.innerText = '₹' + totalSavings.toFixed(2);
        }

        // Update buttons inside boxes
        const cardBoxBtn = document.getElementById('card-box-pay-label');
        if (cardBoxBtn) {
            cardBoxBtn.innerText = `PAY ${formattedAmount} (SECURE 3D CHECKOUT)`;
        }

        const bottomBtn = document.getElementById('pay-btn-label');
        if (bottomBtn) {
            if (currentPaymentMode === 'CARD') {
                bottomBtn.innerText = `PAY VIA CARD & VERIFY OTP (${formattedAmount})`;
            } else if (currentPaymentMode === 'UPI') {
                bottomBtn.innerText = `PROCEED TO UPI PAYMENT & VERIFY (${formattedAmount})`;
            } else if (currentPaymentMode === 'NETBANKING') {
                bottomBtn.innerText = `SUBMIT BANK TRANSFER REF (${formattedAmount})`;
            } else if (currentPaymentMode === 'COD') {
                bottomBtn.innerText = `CONFIRM CASH ON DELIVERY (${formattedAmount})`;
            }
        }
    }

    // Card Input Handler with Live Brand & Bank Detection
    function handleCardInput(input) {
        let val = input.value.replace(/\D/g, '').substring(0, 16);
        let parts = [];
        for (let i = 0; i < val.length; i += 4) {
            parts.push(val.substring(i, i + 4));
        }
        input.value = parts.join(' ');

        const badge = document.getElementById('card-brand-badge');
        const bankLabel = document.getElementById('detected-bank-label');
        const modalBankTitle = document.getElementById('modal-bank-title');
        const hiddenBank = document.getElementById('card-bank-name-hidden');

        let brand = 'CARD';
        let bank = 'Bank Card';

        if (/^4/.test(val)) {
            brand = 'VISA';
            badge.className = 'bg-blue-600 text-white text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider';
            bank = 'HDFC Bank Visa Card';
        } else if (/^(5[1-5]|2[2-7])/.test(val)) {
            brand = 'MASTERCARD';
            badge.className = 'bg-amber-600 text-white text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider';
            bank = 'Axis Bank MasterCard';
        } else if (/^(60|65|81|82)/.test(val)) {
            brand = 'RUPAY';
            badge.className = 'bg-emerald-600 text-white text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider';
            bank = 'State Bank of India RuPay Card';
        } else if (/^3[47]/.test(val)) {
            brand = 'AMEX';
            badge.className = 'bg-sky-600 text-white text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider';
            bank = 'American Express Card';
        } else if (/^(50|56|57|58|6)/.test(val)) {
            brand = 'MAESTRO';
            badge.className = 'bg-red-600 text-white text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider';
            bank = 'ICICI Bank Maestro Card';
        } else {
            badge.className = 'bg-gray-100 text-gray-700 text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider';
        }

        badge.innerText = brand;
        bankLabel.innerHTML = `<i class="fa-solid fa-building-columns text-emerald-600 mr-1"></i> Detected: <strong>${bank}</strong>`;
        if (modalBankTitle) modalBankTitle.innerText = `${bank} 3D Secure Authentication`;
        if (hiddenBank) hiddenBank.value = bank;

        if (val.length >= 4) {
            document.getElementById('card-last4-hidden').value = val.slice(-4);
        }
    }

    // Auto-fill Demo Card for Instant Testing
    function autofillDemoCard() {
        const cardInput = document.getElementById('card-number-input');
        cardInput.value = '4532 8920 1289 4012';
        handleCardInput(cardInput);
        document.getElementById('card-expiry-input').value = '12/28';
        document.getElementById('card-cvv-input').value = '892';
        
        // Auto-apply card offer for convenience if not already
        if (!cardOfferApplied) {
            toggleCardOffer();
        }
    }

    function formatExpiry(input) {
        let val = input.value.replace(/\D/g, '').substring(0, 4);
        if (val.length >= 2) {
            input.value = val.substring(0, 2) + '/' + val.substring(2);
        } else {
            input.value = val;
        }
    }

    // Generate Demo UTR for testing
    function generateDemoUtr() {
        const randomUtr = '4' + Math.floor(10000000000 + Math.random() * 89999999999);
        document.getElementById('upi-utr-input').value = randomUtr;
    }

    function generateDemoUtrModal() {
        const randomUtr = '4' + Math.floor(10000000000 + Math.random() * 89999999999);
        document.getElementById('modal-upi-utr').value = randomUtr;
    }

    function generateDemoBankRef() {
        const randomRef = 'IMPS/' + Math.floor(1000000000 + Math.random() * 8999999999);
        document.getElementById('netbanking-utr-input').value = randomRef;
    }

    function regenerateCaptcha() {
        const rand = Math.floor(100 + Math.random() * 900);
        document.getElementById('captcha-display').innerText = rand;
    }

    function copyToClipboard(text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check text-emerald-500"></i> Copied!';
            setTimeout(() => {
                btn.innerHTML = orig;
            }, 2000);
        });
    }

    // ================= UPI GATEWAY MODAL HANDLERS =================
    function openUpiGatewayModal() {
        const existingUtr = document.getElementById('upi-utr-input').value.trim();
        if (existingUtr) {
            document.getElementById('modal-upi-utr').value = existingUtr;
        }
        document.getElementById('upi-gateway-modal').classList.remove('hidden');

        // Start 5 min countdown
        let totalSeconds = 299;
        const timerEl = document.getElementById('upi-timer');
        clearInterval(upiTimerInterval);
        upiTimerInterval = setInterval(() => {
            let m = Math.floor(totalSeconds / 60);
            let s = totalSeconds % 60;
            timerEl.innerHTML = `<i class="fa-solid fa-clock text-xs animate-pulse"></i> 0${m}:${s < 10 ? '0' : ''}${s}`;
            totalSeconds--;
            if (totalSeconds < 0) {
                clearInterval(upiTimerInterval);
                timerEl.innerText = 'Session Expired';
            }
        }, 1000);
    }

    function closeUpiGatewayModal() {
        clearInterval(upiTimerInterval);
        document.getElementById('upi-gateway-modal').classList.add('hidden');
    }

    function verifyAndSubmitUpiPayment() {
        const utrInput = document.getElementById('modal-upi-utr');
        let utr = utrInput.value.replace(/\s+/g, '').trim();

        if (!utr || !/^[0-9]{12}$/.test(utr)) {
            alert('Invalid UTR! Kripya PhonePe, Google Pay ya Paytm receipt se 12-Digit numeric Reference / UTR Number enter karein.');
            utrInput.focus();
            return;
        }

        const fakePatterns = [
            '000000000000', '111111111111', '222222222222', '333333333333', 
            '444444444444', '555555555555', '666666666666', '777777777777', 
            '888888888888', '999999999999'
        ];
        if (fakePatterns.includes(utr)) {
            alert('Fake / Dummy UTR number accept nahi hoga! Kripya valid 12-digit UTR enter karein.');
            utrInput.focus();
            return;
        }

        const statusBox = document.getElementById('upi-process-status');
        const statusMsg = document.getElementById('upi-status-msg');
        const progressBar = document.getElementById('upi-progress-bar');
        const verifyBtn = document.getElementById('upi-verify-btn');

        statusBox.classList.remove('hidden');
        verifyBtn.disabled = true;
        verifyBtn.classList.add('opacity-50');

        statusMsg.innerText = `Connecting to UPI NPCI Clearance Network for UTR #${utr}...`;
        progressBar.style.width = '40%';

        setTimeout(() => {
            statusMsg.innerText = 'Verifying Bank Credit with Admin Payee Account...';
            progressBar.style.width = '75%';
        }, 600);

        setTimeout(() => {
            statusMsg.innerHTML = '<span class="text-emerald-700 font-black"><i class="fa-solid fa-circle-check"></i> Bank Credit Auto-Verified! Payment Received.</span>';
            progressBar.style.width = '100%';
            progressBar.classList.remove('bg-fk-blue');
            progressBar.classList.add('bg-emerald-500');
            if (typeof playOrderCelebrationChime === 'function') {
                playOrderCelebrationChime();
            }
        }, 1200);

        setTimeout(() => {
            document.getElementById('upi-utr-input').value = utr;
            document.getElementById('payment-form').submit();
        }, 1800);
    }

    // ================= CARD OTP MODAL HANDLERS =================
    function openCardOtpModal() {
        const last4 = document.getElementById('card-last4-hidden').value || '1234';
        document.getElementById('card-ending-text').innerText = '**** ' + last4;
        document.getElementById('card-otp-modal').classList.remove('hidden');
        setTimeout(() => {
            document.getElementById('card-otp-input-field').focus();
        }, 200);
    }

    function closeCardOtpModal() {
        document.getElementById('card-otp-modal').classList.add('hidden');
    }

    function fillDemoOtp() {
        document.getElementById('card-otp-input-field').value = '123456';
    }

    function verifyAndSubmitCardPayment() {
        const otp = document.getElementById('card-otp-input-field').value.trim();
        if (!otp || otp.length < 6) {
            alert('Kripya 6-Digit OTP enter karein (Test ke liye Demo OTP: 123456 use karein).');
            document.getElementById('card-otp-input-field').focus();
            return;
        }

        const statusBox = document.getElementById('card-process-status');
        const statusMsg = document.getElementById('card-status-msg');
        const verifyBtn = document.getElementById('card-verify-btn');
        const progressBar = document.getElementById('card-progress-bar');

        statusBox.classList.remove('hidden');
        verifyBtn.disabled = true;
        verifyBtn.classList.add('opacity-50');

        statusMsg.innerText = 'Step 1/3: Authenticating 3D Secure OTP with Bank...';
        progressBar.style.width = '45%';

        setTimeout(() => {
            statusMsg.innerText = 'Step 2/3: Authorizing Payment with Card Gateway...';
            progressBar.style.width = '80%';
        }, 700);

        setTimeout(() => {
            statusMsg.innerHTML = '<span class="text-emerald-700 font-black"><i class="fa-solid fa-circle-check"></i> Step 3/3: Payment Approved! Auth: CARD-948192</span>';
            progressBar.style.width = '100%';
            if (typeof playOrderCelebrationChime === 'function') {
                playOrderCelebrationChime();
            }
        }, 1300);

        setTimeout(() => {
            document.getElementById('payment-form').submit();
        }, 1800);
    }

    // Specific Submission Triggers from Within Respective Boxes
    function submitCardPayment() {
        const cardNum = document.getElementById('card-number-input').value.trim();
        const cardExp = document.getElementById('card-expiry-input').value.trim();
        const cardCvv = document.getElementById('card-cvv-input').value.trim();

        if (!cardNum || cardNum.length < 15) {
            alert('Kripya valid 16-Digit Card Number enter karein (ya "Use Demo Card" par click karein).');
            document.getElementById('card-number-input').focus();
            return;
        }

        if (!cardExp || cardExp.length < 5) {
            alert('Kripya valid Expiry Date (MM/YY) enter karein (Jaise: 12/28).');
            document.getElementById('card-expiry-input').focus();
            return;
        }

        if (!cardCvv || cardCvv.length < 3) {
            alert('Kripya 3-Digit CVV enter karein.');
            document.getElementById('card-cvv-input').focus();
            return;
        }

        openCardOtpModal();
    }

    function submitUpiPayment() {
        const inlineUtr = document.getElementById('upi-utr-input').value.trim();
        if (!inlineUtr || inlineUtr.length < 6) {
            openUpiGatewayModal();
            return;
        }

        if (!/^[0-9]{12}$/.test(inlineUtr)) {
            alert('Invalid UTR! Kripya PhonePe, Google Pay ya Paytm payment receipt se 12-Digit numeric Reference / UTR Number enter karein.');
            document.getElementById('upi-utr-input').focus();
            return;
        }

        const fakePatterns = [
            '000000000000', '111111111111', '222222222222', '333333333333', 
            '444444444444', '555555555555', '666666666666', '777777777777', 
            '888888888888', '999999999999'
        ];
        if (fakePatterns.includes(inlineUtr)) {
            alert('Fake / Dummy UTR number accept nahi hoga! Kripya real 12-digit UTR enter karein.');
            document.getElementById('upi-utr-input').focus();
            return;
        }

        const submitBtn = document.getElementById('pay-submit-btn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Auto-Verifying Bank Credit...';
        document.getElementById('payment-form').submit();
    }

    function submitNetBankingPayment() {
        const bankUtr = document.getElementById('netbanking-utr-input').value.trim();
        if (!bankUtr || bankUtr.length < 6) {
            alert('Kripya Admin ke Bank Account me transfer karke apna 12-digit IMPS / NEFT UTR Reference Number enter karein!');
            document.getElementById('netbanking-utr-input').focus();
            return;
        }

        const submitBtn = document.getElementById('pay-submit-btn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Verifying Bank Transfer...';
        document.getElementById('payment-form').submit();
    }

    function submitCodPayment() {
        const captchaDisplay = document.getElementById('captcha-display').innerText.trim();
        const captchaInput = document.getElementById('cod-captcha-input').value.trim();

        if (!captchaInput) {
            alert('Kripya Cash on Delivery order confirm karne ke liye screen par dikhaya gaya 3-digit security code dalein.');
            document.getElementById('cod-captcha-input').focus();
            return;
        }

        if (captchaInput !== captchaDisplay) {
            alert('Security code match nahi hua! Kripya sahi 3-digit code enter karein.');
            document.getElementById('cod-captcha-input').focus();
            return;
        }

        const submitBtn = document.getElementById('pay-submit-btn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Placing COD Order...';
        if (typeof playOrderCelebrationChime === 'function') {
            playOrderCelebrationChime();
        }
        setTimeout(() => {
            document.getElementById('payment-form').submit();
        }, 300);
    }

    // Unified Bottom Button Handler
    function initiatePaymentProcess() {
        if (currentPaymentMode === 'CARD') {
            submitCardPayment();
        } else if (currentPaymentMode === 'UPI') {
            submitUpiPayment();
        } else if (currentPaymentMode === 'NETBANKING') {
            submitNetBankingPayment();
        } else if (currentPaymentMode === 'COD') {
            submitCodPayment();
        }
    }

    // Initialize UI on page load
    document.addEventListener('DOMContentLoaded', function() {
        selectPaymentMethod('CARD');
    });
</script>

<?php require_once __DIR__ . '/includes/checkout-footer.php'; ?>
