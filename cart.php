<?php
$pageTitle = "Shopping Cart";
require_once __DIR__ . '/includes/header.php';

$couponDiscount = $_SESSION['applied_coupon']['discount'] ?? 0;
$couponCode = $_SESSION['applied_coupon']['code'] ?? '';

$items = getCartItems();
$totals = getCartTotals($couponDiscount);

// Handle coupon removal
if (isset($_GET['remove_coupon'])) {
    unset($_SESSION['applied_coupon']);
    header("Location: " . SITE_URL . "/cart.php");
    exit;
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <?php if (count($items) > 0): ?>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- Left Items Column (8 cols) -->
            <div class="lg:col-span-8 space-y-4">
                
                <!-- Delivery Pin bar -->
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-location-dot text-fk-blue text-lg"></i>
                        <div>
                            <div class="text-xs text-gray-500">Deliver to: <strong class="text-gray-900"><?= $user ? htmlspecialchars($user['name']) : 'Guest Customer' ?></strong>, 122001</div>
                            <div class="text-[11px] text-gray-400">DLF Phase 2, Gurugram, Haryana</div>
                        </div>
                    </div>
                    <a href="<?= SITE_URL ?>/profile.php" class="text-xs font-bold text-fk-blue hover:underline">Change</a>
                </div>

                <!-- Cart Items List Container with Form -->
                <form action="<?= SITE_URL ?>/checkout.php" method="POST" id="cart-selection-form" onsubmit="return validateCartCheckout(event)">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden divide-y divide-gray-100">
                        
                        <!-- Top Selection Header -->
                        <div class="p-4 bg-gray-50/80 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <input 
                                    type="checkbox" 
                                    id="select-all-checkbox" 
                                    checked 
                                    onchange="toggleSelectAllCart(this)" 
                                    class="w-4 h-4 text-fk-blue rounded border-gray-300 focus:ring-blue-500 cursor-pointer"
                                >
                                <label for="select-all-checkbox" class="text-xs font-black text-gray-900 uppercase tracking-wide cursor-pointer select-none">
                                    Select All (<span id="selected-items-count"><?= count($items) ?></span> / <?= count($items) ?> Items)
                                </label>
                            </div>
                            <span class="text-[11px] text-gray-500 font-medium">
                                <i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i> Jo item buy karna hai use tick karein ya alag se buy karein.
                            </span>
                        </div>

                        <?php foreach ($items as $item): ?>
                            <div 
                                class="p-4 sm:p-6 flex flex-col sm:flex-row gap-4 sm:gap-5 items-start cart-item-row transition hover:bg-gray-50/30"
                                data-cart-id="<?= $item['cart_id'] ?>"
                                data-mrp="<?= $item['mrp_price'] * $item['quantity'] ?>"
                                data-selling="<?= $item['selling_price'] * $item['quantity'] ?>"
                                data-qty="<?= $item['quantity'] ?>"
                            >
                                <!-- Checkbox to Select / Deselect this specific item -->
                                <div class="pt-2 sm:pt-4 shrink-0">
                                    <input 
                                        type="checkbox" 
                                        name="selected_cart_ids[]" 
                                        value="<?= $item['cart_id'] ?>" 
                                        checked 
                                        class="item-checkbox w-4 h-4 text-fk-blue rounded border-gray-300 focus:ring-blue-500 cursor-pointer"
                                        onchange="recalculateCartSelection()"
                                        title="Tick to include in order"
                                    >
                                </div>

                                <!-- Fruit Image & Qty Selector -->
                                <div class="flex flex-col items-center gap-3 shrink-0">
                                    <a href="<?= SITE_URL ?>/product-details.php?id=<?= $item['id'] ?>" class="w-24 h-24 rounded-lg overflow-hidden border border-gray-200 bg-gray-50 p-1 block">
                                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover rounded">
                                    </a>

                                    <!-- Quantity Controls (+ / -) -->
                                    <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden bg-white shadow-sm">
                                        <button 
                                            type="button"
                                            onclick="updateCartQty(<?= $item['id'] ?>, <?= $item['quantity'] - 1 ?>)" 
                                            class="w-7 h-7 flex items-center justify-center bg-gray-50 hover:bg-gray-200 text-gray-700 font-bold transition text-xs"
                                            title="Decrease quantity"
                                        >
                                            &minus;
                                        </button>
                                        <span class="w-8 text-center text-xs font-bold text-gray-900"><?= $item['quantity'] ?></span>
                                        <button 
                                            type="button"
                                            onclick="updateCartQty(<?= $item['id'] ?>, <?= $item['quantity'] + 1 ?>)" 
                                            class="w-7 h-7 flex items-center justify-center bg-gray-50 hover:bg-gray-200 text-gray-700 font-bold transition text-xs"
                                            title="Increase quantity"
                                        >
                                            +
                                        </button>
                                    </div>
                                </div>

                                <!-- Fruit Details -->
                                <div class="flex-1 flex flex-col justify-between self-stretch">
                                    <div>
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <a href="<?= SITE_URL ?>/product-details.php?id=<?= $item['id'] ?>" class="text-sm sm:text-base font-bold text-gray-900 hover:text-fk-blue line-clamp-1">
                                                    <?= htmlspecialchars($item['name']) ?>
                                                </a>
                                                <div class="text-xs text-gray-500 mt-0.5">Unit: <?= htmlspecialchars($item['unit']) ?> &bull; Seller: <span class="font-medium text-gray-700">FruitKart Fresh Hub</span></div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-xs text-gray-400">Delivery by <strong class="text-gray-700">Today, 2 hrs</strong></div>
                                                <div class="text-[11px] text-emerald-600 font-semibold">Free ₹40</div>
                                            </div>
                                        </div>

                                        <!-- Price -->
                                        <div class="flex items-baseline gap-2 mt-2">
                                            <span class="text-base font-black text-gray-900"><?= formatPrice($item['selling_price'] * $item['quantity']) ?></span>
                                            <?php if ($item['mrp_price'] > $item['selling_price']): ?>
                                                <span class="text-xs text-gray-400 line-through"><?= formatPrice($item['mrp_price'] * $item['quantity']) ?></span>
                                                <span class="text-xs font-bold text-emerald-700"><?= $item['discount_percent'] ?>% Off</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Action Buttons & Direct Buy This Item Only -->
                                    <div class="flex flex-wrap items-center justify-between gap-3 mt-4 pt-3 border-t border-gray-100 text-xs font-bold">
                                        <div class="flex items-center gap-4">
                                            <button type="button" onclick="toggleWishlist(<?= $item['id'] ?>, null)" class="text-gray-600 hover:text-rose-600 transition uppercase tracking-wider flex items-center gap-1 cursor-pointer">
                                                <i class="fa-regular fa-heart"></i>
                                                <span>Save For Later</span>
                                            </button>
                                            <button type="button" onclick="removeFromCart(<?= $item['id'] ?>)" class="text-gray-600 hover:text-rose-600 transition uppercase tracking-wider flex items-center gap-1 cursor-pointer">
                                                <i class="fa-regular fa-trash-can"></i>
                                                <span>Remove</span>
                                            </button>
                                        </div>

                                        <!-- Direct Buy Only This Specific Item -->
                                        <a 
                                            href="<?= SITE_URL ?>/checkout.php?cart_id=<?= $item['cart_id'] ?>" 
                                            class="bg-amber-400 hover:bg-amber-500 text-slate-950 font-black px-3.5 py-1.5 rounded-lg shadow-sm transition text-[11px] flex items-center gap-1.5 uppercase tracking-wide cursor-pointer"
                                            title="Sirf ye item buy karein (baki cart me rahenge)"
                                        >
                                            <i class="fa-solid fa-bolt text-xs"></i>
                                            <span>Buy This Item Only</span>
                                        </a>
                                    </div>
                                </div>

                            </div>
                        <?php endforeach; ?>

                        <!-- Bottom Sticky Bar with Place Order (Sits cleanly above mobile app nav bar) -->
                        <div class="p-3.5 sm:p-4 bg-white sticky bottom-14 md:bottom-0 border-t border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 z-20 shadow-lg md:shadow-none">
                            <div class="text-xs text-gray-600">
                                Selected Total: <strong class="text-base text-gray-900" id="bottom-selected-total"><?= formatPrice($totals['final_amount']) ?></strong>
                                <span class="block text-[10px] text-gray-400" id="bottom-selected-count">(<?= count($items) ?> items selected for payment)</span>
                            </div>
                            <button 
                                type="submit" 
                                id="place-order-submit-btn" 
                                class="bg-fk-orange hover:bg-orange-600 text-white font-extrabold px-8 py-3 rounded-lg shadow-md hover:shadow-lg transition text-sm uppercase tracking-wide flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span>PLACE ORDER (<span id="place-order-btn-count"><?= count($items) ?></span> SELECTED)</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>
                        </div>

                    </div>
                </form>

            </div>

            <!-- Right Price Details Box (4 cols) -->
            <div class="lg:col-span-4 space-y-4">
                
                <!-- Coupon Apply Box -->
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                    <h3 class="text-xs font-bold text-gray-800 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <i class="fa-solid fa-ticket text-fk-blue"></i>
                        <span>Apply Coupon &amp; Offers</span>
                    </h3>

                    <?php if (!empty($couponCode)): ?>
                        <div class="flex items-center justify-between p-2.5 bg-emerald-50 border border-emerald-200 rounded-lg text-xs">
                            <div>
                                <span class="font-bold text-emerald-800 tracking-wide"><?= htmlspecialchars($couponCode) ?></span>
                                <span class="text-emerald-700 block text-[11px]">Applied! Saved <?= formatPrice($couponDiscount) ?></span>
                            </div>
                            <a href="<?= SITE_URL ?>/cart.php?remove_coupon=1" class="text-rose-600 font-bold hover:underline">Remove</a>
                        </div>
                    <?php else: ?>
                        <form onsubmit="applyCouponCode(event)" class="flex gap-2">
                            <input 
                                type="text" 
                                id="coupon-code-input" 
                                placeholder="Enter FRESH50, FRUIT20" 
                                class="w-full text-xs p-2.5 uppercase border border-gray-300 rounded focus:outline-none focus:border-fk-blue font-bold tracking-wider"
                            >
                            <button type="submit" class="bg-fk-blue hover:bg-fk-blue-dark text-white text-xs font-bold px-4 py-2.5 rounded transition shadow-sm">
                                APPLY
                            </button>
                        </form>
                        <div class="mt-2 text-[11px] text-gray-500">
                            Available: <span class="cursor-pointer font-bold text-fk-blue hover:underline" onclick="fillCoupon('FRESH50')">FRESH50</span> (Flat ₹50 Off) &bull; <span class="cursor-pointer font-bold text-fk-blue hover:underline" onclick="fillCoupon('FRUIT20')">FRUIT20</span> (20% Off)
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Flipkart Price Details Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden sticky top-24">
                    <div class="p-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                        <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-wider">Price Details</h3>
                        <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200" id="summary-badge-count">
                            <?= $totals['item_count'] ?> Selected
                        </span>
                    </div>

                    <div class="p-4 space-y-3 text-xs">
                        <div class="flex justify-between text-gray-700">
                            <span>Price (<span id="summary-item-count"><?= $totals['item_count'] ?></span> items)</span>
                            <span id="summary-mrp-total"><?= formatPrice($totals['total_mrp']) ?></span>
                        </div>

                        <div class="flex justify-between text-emerald-600 font-medium">
                            <span>Special Product Discount</span>
                            <span>&minus; <span id="summary-discount-total"><?= formatPrice($totals['discount']) ?></span></span>
                        </div>

                        <?php if ($couponDiscount > 0): ?>
                            <div class="flex justify-between text-emerald-600 font-medium">
                                <span>Coupon Savings</span>
                                <span>&minus; <?= formatPrice($couponDiscount) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="flex justify-between text-gray-700 items-center">
                            <span>Delivery Charges</span>
                            <span id="summary-delivery-fee" class="<?= $totals['delivery_fee'] == 0 ? 'text-emerald-600 font-bold uppercase' : 'text-gray-900 font-bold' ?>">
                                <?= $totals['delivery_fee'] == 0 ? '<span class="line-through text-gray-400 font-normal mr-1">₹30</span> FREE' : formatPrice($totals['delivery_fee']) ?>
                            </span>
                        </div>

                        <div class="border-t border-dashed border-gray-200 pt-3 flex justify-between text-sm font-black text-gray-900">
                            <span>Total Payable</span>
                            <span class="text-base text-gray-900" id="summary-final-amount"><?= formatPrice($totals['final_amount']) ?></span>
                        </div>

                        <!-- Savings Alert -->
                        <div class="bg-emerald-50 text-emerald-800 p-2.5 rounded-lg border border-emerald-200 text-center font-bold text-xs" id="summary-savings-box">
                            🎉 You will save <span id="summary-savings-total"><?= formatPrice($totals['discount'] + $couponDiscount) ?></span> on this order!
                        </div>
                    </div>

                    <!-- Safety Guarantee -->
                    <div class="p-3 bg-gray-50 border-t border-gray-100 text-[11px] text-gray-500 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-shield-halved text-emerald-600"></i>
                        <span>Safe and Secure Payments. 100% Authentic Produce.</span>
                    </div>
                </div>

            </div>

        </div>
    <?php else: ?>
        <!-- Empty Cart Screen -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center max-w-2xl mx-auto my-8">
            <div class="w-24 h-24 mx-auto rounded-full bg-blue-50 text-fk-blue flex items-center justify-center text-4xl mb-4">
                <i class="fa-solid fa-cart-shopping"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-900 mb-2">Your Fruit Basket is Empty!</h2>
            <p class="text-xs text-gray-500 mb-6 max-w-sm mx-auto">
                Explore our orchard-fresh harvest of apples, seasonal mangoes, berries, and exotic fruits today.
            </p>
            <a href="<?= SITE_URL ?>/products.php" class="bg-fk-blue hover:bg-fk-blue-dark text-white font-bold text-xs px-8 py-3 rounded-lg shadow-md transition inline-block uppercase tracking-wider">
                Shop Fresh Fruits Now
            </a>
        </div>
    <?php endif; ?>

</div>

<script>
    const couponDiscountVal = <?= (float)$couponDiscount ?>;
    const standardDeliveryFee = <?= (float)getSetting('delivery_fee', '0.00') ?>;
    const freeDeliveryThreshold = <?= (float)getSetting('free_delivery_threshold', '0.00') ?>;

    function formatINR(amount) {
        return '₹' + Number(amount).toFixed(2);
    }

    function toggleSelectAllCart(master) {
        const itemCheckboxes = document.querySelectorAll('.item-checkbox');
        itemCheckboxes.forEach(cb => { cb.checked = master.checked; });
        recalculateCartSelection();
    }

    function recalculateCartSelection() {
        const rows = document.querySelectorAll('.cart-item-row');
        let selectedCount = 0;
        let totalMrp = 0;
        let totalSelling = 0;
        let totalQty = 0;

        rows.forEach(row => {
            const cb = row.querySelector('.item-checkbox');
            if (cb && cb.checked) {
                selectedCount++;
                const mrp = parseFloat(row.dataset.mrp) || 0;
                const selling = parseFloat(row.dataset.selling) || 0;
                const qty = parseInt(row.dataset.qty) || 1;

                totalMrp += mrp;
                totalSelling += selling;
                totalQty += qty;
            }
        });

        // Update master select-all state
        const masterCb = document.getElementById('select-all-checkbox');
        if (masterCb) {
            masterCb.checked = selectedCount === rows.length && rows.length > 0;
        }

        const discount = Math.max(0, totalMrp - totalSelling);
        let deliveryFee = 0;
        if (standardDeliveryFee > 0 && totalSelling > 0) {
            if (freeDeliveryThreshold > 0 && totalSelling >= freeDeliveryThreshold) {
                deliveryFee = 0;
            } else {
                deliveryFee = standardDeliveryFee;
            }
        }
        let finalAmount = Math.max(0, totalSelling - couponDiscountVal + deliveryFee);

        // Update DOM displays
        document.getElementById('selected-items-count').innerText = selectedCount;
        document.getElementById('place-order-btn-count').innerText = selectedCount;
        document.getElementById('bottom-selected-count').innerText = `(${selectedCount} items selected for payment)`;
        document.getElementById('bottom-selected-total').innerText = formatINR(finalAmount);

        document.getElementById('summary-badge-count').innerText = `${totalQty} Selected`;
        document.getElementById('summary-item-count').innerText = totalQty;
        document.getElementById('summary-mrp-total').innerText = formatINR(totalMrp);
        document.getElementById('summary-discount-total').innerText = formatINR(discount);

        const deliveryEl = document.getElementById('summary-delivery-fee');
        if (deliveryFee === 0) {
            deliveryEl.className = 'text-emerald-600 font-bold uppercase';
            deliveryEl.innerHTML = '<span class="line-through text-gray-400 font-normal mr-1">₹30</span> FREE';
        } else {
            deliveryEl.className = 'text-gray-900 font-bold';
            deliveryEl.innerText = formatINR(deliveryFee);
        }

        document.getElementById('summary-final-amount').innerText = formatINR(finalAmount);
        document.getElementById('summary-savings-total').innerText = formatINR(discount + couponDiscountVal);

        // Enable / Disable place order button
        const submitBtn = document.getElementById('place-order-submit-btn');
        if (selectedCount === 0) {
            submitBtn.disabled = true;
            submitBtn.innerText = 'NO ITEMS SELECTED';
        } else {
            submitBtn.disabled = false;
            submitBtn.innerHTML = `<span>PLACE ORDER (${selectedCount} SELECTED)</span> <i class="fa-solid fa-arrow-right"></i>`;
        }
    }

    function validateCartCheckout(e) {
        const checkedCount = document.querySelectorAll('.item-checkbox:checked').length;
        if (checkedCount === 0) {
            e.preventDefault();
            alert('Kripya buy karne ke liye kam se kam 1 fruit item select karein.');
            return false;
        }
        return true;
    }
    function fillCoupon(code) {
        document.getElementById('coupon-code-input').value = code;
    }

    async function applyCouponCode(e) {
        e.preventDefault();
        const code = document.getElementById('coupon-code-input').value.trim();
        if (!code) return;

        try {
            const res = await fetch('<?= SITE_URL ?>/api/coupon.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code: code })
            });
            const data = await res.json();
            if (data.status === 'success') {
                showToast(data.message, 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast(data.message, 'error');
            }
        } catch (err) {
            console.error(err);
            showToast('Server error applying coupon.', 'error');
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
