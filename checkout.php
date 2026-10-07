<?php
$pageTitle = "Checkout - Select Address & Delivery";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

// Check for Direct Buy Now (single product, e.g. from product page or buy this item button)
if (isset($_GET['buy_now']) && !empty($_GET['product_id'])) {
    $buyNowPid = (int)$_GET['product_id'];
    $buyNowQty = max(1, (int)($_GET['qty'] ?? 1));
    $_SESSION['buy_now_product'] = [
        'product_id' => $buyNowPid,
        'quantity' => $buyNowQty
    ];
    unset($_SESSION['selected_cart_ids']);
} elseif (isset($_POST['selected_cart_ids']) && is_array($_POST['selected_cart_ids'])) {
    $_SESSION['selected_cart_ids'] = array_map('intval', array_filter($_POST['selected_cart_ids']));
    unset($_SESSION['buy_now_product']);
} elseif (isset($_GET['cart_id'])) {
    // Single cart item checkout
    $_SESSION['selected_cart_ids'] = [(int)$_GET['cart_id']];
    unset($_SESSION['buy_now_product']);
} elseif (isset($_GET['all_cart'])) {
    unset($_SESSION['selected_cart_ids']);
    unset($_SESSION['buy_now_product']);
}

$items = getCheckoutItems();

if (empty($items)) {
    setFlash('warning', 'Koi fruit select nahi hai. Kripya cart se item choose karein.');
    header("Location: " . SITE_URL . "/cart.php");
    exit;
}

$couponDiscount = $_SESSION['applied_coupon']['discount'] ?? 0;
$couponCode = $_SESSION['applied_coupon']['code'] ?? '';
$totals = getCheckoutTotals($couponDiscount);

// 1. Handle Swiggy Map Pin "Save & Proceed to Payment" direct action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_and_proceed'])) {
    $fullName = clean($_POST['full_name'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $pincode = clean($_POST['pincode'] ?? '122001');
    $locality = clean($_POST['locality'] ?? 'Sector 18');
    $addressLine = clean($_POST['address_line'] ?? '');
    $city = clean($_POST['city'] ?? 'Gurugram');
    $state = clean($_POST['state'] ?? 'Haryana');
    $addressType = in_array($_POST['address_type'] ?? '', ['Home', 'Work', 'Other']) ? $_POST['address_type'] : 'Home';
    $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : 28.459500;
    $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : 77.026600;
    $mapsLink = clean($_POST['maps_link'] ?? '');
    if (empty($mapsLink) && $latitude && $longitude) {
        $mapsLink = "https://www.google.com/maps?q={$latitude},{$longitude}";
    }
    $gpsAddress = clean($_POST['gps_address'] ?? 'Sector 18, Gurugram, Haryana');
    $selectedSlot = clean($_POST['delivery_slot'] ?? 'Express Slot (Within 2 Hours)');

    // Smart Village / Rural Fallback if user clicked Confirm without typing doorstep text
    if (empty($fullName)) {
        $fullName = !empty($user['name']) ? $user['name'] : 'Valued Customer';
    }
    if (empty($phone)) {
        $phone = !empty($user['phone']) ? $user['phone'] : '9876543210';
    }
    if (empty($locality)) {
        $locality = !empty($city) ? $city : 'Local Area';
    }
    if (empty($addressLine)) {
        $addressLine = "Near Main Chowk / House Point, " . $locality;
    }

    if (!empty($fullName) && !empty($phone) && !empty($addressLine)) {
        // Set previous addresses to non-default
        $db->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);

        try {
            $ins = $db->prepare("
                INSERT INTO addresses (user_id, full_name, phone, pincode, locality, address_line, city, state, address_type, is_default, latitude, longitude, maps_link, gps_address)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)
            ");
            $ins->execute([$userId, $fullName, $phone, $pincode, $locality, $addressLine, $city, $state, $addressType, $latitude, $longitude, $mapsLink, $gpsAddress]);
            $newAddrId = $db->lastInsertId();
        } catch (Exception $e) {
            $ins = $db->prepare("
                INSERT INTO addresses (user_id, full_name, phone, pincode, locality, address_line, city, state, address_type, is_default)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $ins->execute([$userId, $fullName, $phone, $pincode, $locality, $addressLine, $city, $state, $addressType]);
            $newAddrId = $db->lastInsertId();
        }

        // Immediately set this new address as the current order delivery address
        $_SESSION['checkout_data'] = [
            'address_id' => $newAddrId,
            'delivery_slot' => $selectedSlot,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'maps_link' => $mapsLink,
            'gps_address' => $gpsAddress
        ];
        header("Location: " . SITE_URL . "/payment.php");
        exit;
    } else {
        setFlash('error', 'Kripya apna House / Flat No, Name aur Mobile Number enter karein.');
    }
}

// 2. Handle Proceed with Existing Saved Address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceed_payment'])) {
    $selectedAddressId = (int)($_POST['selected_address_id'] ?? 0);
    $selectedSlot = clean($_POST['delivery_slot'] ?? 'Express Slot (Within 2 Hours)');
    $customLat = !empty($_POST['custom_latitude']) ? (float)$_POST['custom_latitude'] : null;
    $customLng = !empty($_POST['custom_longitude']) ? (float)$_POST['custom_longitude'] : null;
    $customMaps = clean($_POST['custom_maps_link'] ?? '');
    $customGpsAddr = clean($_POST['custom_gps_address'] ?? '');

    if ($selectedAddressId <= 0) {
        setFlash('error', 'Please select or add a delivery address.');
    } else {
        // Set this chosen address as the default active address for user
        $db->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
        $db->prepare("UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$selectedAddressId, $userId]);

        if ($customLat && $customLng) {
            try {
                $db->prepare("UPDATE addresses SET latitude = ?, longitude = ?, maps_link = ?, gps_address = ? WHERE id = ? AND user_id = ?")
                   ->execute([$customLat, $customLng, $customMaps, $customGpsAddr, $selectedAddressId, $userId]);
            } catch (Exception $e) {}
        }

        // Set as current order active checkout data
        $_SESSION['checkout_data'] = [
            'address_id' => $selectedAddressId,
            'delivery_slot' => $selectedSlot,
            'latitude' => $customLat,
            'longitude' => $customLng,
            'maps_link' => $customMaps,
            'gps_address' => $customGpsAddr
        ];
        header("Location: " . SITE_URL . "/payment.php");
        exit;
    }
}

// Fetch user addresses
$stmt = $db->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$stmt->execute([$userId]);
$addresses = $stmt->fetchAll();

$addressesJson = json_encode(array_map(function($a) {
    return [
        'id' => (int)$a['id'],
        'lat' => !empty($a['latitude']) ? (float)$a['latitude'] : 28.459500,
        'lng' => !empty($a['longitude']) ? (float)$a['longitude'] : 77.026600,
        'full_name' => $a['full_name'],
        'city' => $a['city']
    ];
}, $addresses));

require_once __DIR__ . '/includes/checkout-header.php';
?>

<!-- Leaflet CSS & JS for Swiggy Style Interactive Map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<style>
/* Swiggy Map Container & Overlays */
.swiggy-map-wrapper {
    position: relative;
    border-radius: 1.25rem;
    overflow: hidden;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
    background: #f1f5f9;
}

#swiggy-map {
    height: 420px;
    width: 100%;
    z-index: 10;
}

/* Swiggy Floating Center Pin */
.swiggy-center-pin-wrapper {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -100%);
    z-index: 500;
    pointer-events: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    transition: transform 0.18s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.swiggy-center-pin-wrapper.pin-floating {
    transform: translate(-50%, -125%);
}

.swiggy-pin-tag {
    background: #111827;
    color: #ffffff;
    font-size: 11px;
    font-weight: 800;
    padding: 5px 12px;
    border-radius: 9999px;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
    white-space: nowrap;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
    letter-spacing: 0.2px;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.swiggy-pin-marker {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #10b981 0%, #2563eb 100%);
    border-radius: 50% 50% 50% 0;
    transform: rotate(-45deg);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    box-shadow: 0 10px 22px rgba(16, 185, 129, 0.45);
    border: 3px solid #ffffff;
    font-size: 17px;
}

.swiggy-pin-marker i {
    transform: rotate(45deg);
}

.swiggy-pin-pulse {
    width: 16px;
    height: 6px;
    background: rgba(0, 0, 0, 0.25);
    border-radius: 50%;
    margin-top: 2px;
    filter: blur(1.5px);
    transition: transform 0.18s ease-out, opacity 0.18s ease-out;
}

.swiggy-center-pin-wrapper.pin-floating .swiggy-pin-pulse {
    transform: scale(0.6);
    opacity: 0.15;
}

/* Floating Search Bar (Swiggy Style) */
.swiggy-floating-search-bar {
    position: absolute;
    top: 14px;
    left: 14px;
    right: 14px;
    z-index: 600;
}

/* Floating Locate Me Button */
.swiggy-floating-locate-btn {
    position: absolute;
    bottom: 16px;
    right: 16px;
    z-index: 600;
    background: #ffffff;
    color: #1f2937;
    padding: 9px 15px;
    border-radius: 9999px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    border: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 7px;
    cursor: pointer;
    font-weight: 800;
    font-size: 12px;
    transition: all 0.2s ease;
}

.swiggy-floating-locate-btn:hover {
    background: #f9fafb;
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);
    color: #fc8019;
}

/* Floating Map Layer Switcher (Google Roadmap / Satellite) */
.swiggy-floating-layer-toggle {
    position: absolute;
    bottom: 16px;
    left: 16px;
    z-index: 600;
    display: flex;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
    border-radius: 9999px;
    padding: 3px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    border: 1px solid #e5e7eb;
}

.map-layer-btn {
    padding: 6px 13px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    display: flex;
    align-items: center;
    gap: 5px;
    color: #4b5563;
    background: transparent;
}

.map-layer-btn.active {
    background: #059669;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(5, 150, 105, 0.4);
}
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left Checkout Form Steps (8 cols) -->
        <div class="lg:col-span-8 space-y-5">
            
            <!-- Step 1: Login Status Header -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-full bg-fk-blue text-white text-xs font-black flex items-center justify-center">1</span>
                    <div>
                        <span class="text-[11px] uppercase text-gray-500 font-bold tracking-wider">LOGGED IN AS</span>
                        <div class="text-sm font-black text-gray-800 flex items-center gap-2">
                            <span><?= htmlspecialchars($user['name']) ?></span>
                            <span class="text-xs font-normal text-gray-500">(<?= htmlspecialchars($user['email']) ?>)</span>
                            <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Swiggy Style Location Picker & Address Management -->
            <div class="space-y-4">

                <!-- If user has saved addresses: show quick selector -->
                <?php if (count($addresses) > 0): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="p-4 bg-slate-900 text-white flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="w-7 h-7 rounded-full bg-amber-500 text-white text-xs font-black flex items-center justify-center">2</span>
                                <div>
                                    <h2 class="text-sm font-black uppercase tracking-wider">SELECT SAVED ADDRESS OR PIN ON MAP</h2>
                                    <p class="text-[11px] text-slate-300">Apne saved address se deliver karwayein ya FruitKart live map par naya location pin karein</p>
                                </div>
                            </div>
                            <button 
                                type="button" 
                                onclick="focusSwiggyMap()" 
                                class="bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold px-3.5 py-1.5 rounded-xl shadow transition cursor-pointer flex items-center gap-1.5"
                            >
                                <i class="fa-solid fa-map-pin"></i>
                                <span>Set Location on FruitKart Map</span>
                            </button>
                        </div>

                        <!-- Saved addresses list form -->
                        <form action="<?= SITE_URL ?>/checkout.php" method="POST" class="p-4 space-y-3">
                            <input type="hidden" name="proceed_payment" value="1">
                            <input type="hidden" name="custom_latitude" id="saved-input-lat" value="">
                            <input type="hidden" name="custom_longitude" id="saved-input-lng" value="">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <?php foreach ($addresses as $index => $addr): ?>
                                    <label class="block p-3.5 rounded-xl border <?= $index === 0 ? 'border-amber-500 bg-amber-50/40 shadow-xs' : 'border-gray-200 hover:border-gray-300' ?> cursor-pointer transition">
                                        <div class="flex items-start gap-2.5">
                                            <input 
                                                type="radio" 
                                                name="selected_address_id" 
                                                value="<?= $addr['id'] ?>" 
                                                <?= $index === 0 ? 'checked' : '' ?> 
                                                class="mt-1 text-amber-500 focus:ring-amber-500"
                                                onchange="onSavedAddressRadioChange(<?= (int)$addr['id'] ?>, <?= !empty($addr['latitude']) ? (float)$addr['latitude'] : 28.4595 ?>, <?= !empty($addr['longitude']) ? (float)$addr['longitude'] : 77.0266 ?>)"
                                            >
                                            <div class="flex-1 text-xs">
                                                <div class="flex items-center justify-between gap-1">
                                                    <span class="font-black text-gray-900"><?= htmlspecialchars($addr['full_name']) ?></span>
                                                    <span class="bg-gray-100 text-gray-700 text-[10px] font-bold px-2 py-0.5 rounded uppercase">
                                                        <?= htmlspecialchars($addr['address_type']) ?>
                                                    </span>
                                                </div>
                                                <p class="text-gray-600 mt-1 leading-relaxed">
                                                    <?= htmlspecialchars($addr['address_line']) ?>, <?= htmlspecialchars($addr['locality']) ?>, <?= htmlspecialchars($addr['city']) ?>
                                                </p>
                                                <div class="mt-2 text-[11px] text-gray-500 font-bold">
                                                    📞 <?= htmlspecialchars($addr['phone']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <!-- Delivery Slot Selection for saved address -->
                            <div class="pt-3 border-t border-gray-100">
                                <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wide mb-2">Delivery Time Slot:</label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <label class="p-2.5 border border-emerald-400 bg-emerald-50/50 rounded-xl flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="delivery_slot" value="Express Slot (Within 2 Hours)" checked class="text-amber-500">
                                        <span class="text-xs font-bold text-emerald-900">⚡ 2-Hour Express</span>
                                    </label>
                                    <label class="p-2.5 border border-gray-200 rounded-xl flex items-center gap-2 cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="delivery_slot" value="Morning 7:00 AM - 10:00 AM" class="text-amber-500">
                                        <span class="text-xs font-semibold text-gray-700">🌅 Morning (7-10 AM)</span>
                                    </label>
                                    <label class="p-2.5 border border-gray-200 rounded-xl flex items-center gap-2 cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="delivery_slot" value="Evening 5:00 PM - 8:00 PM" class="text-amber-500">
                                        <span class="text-xs font-semibold text-gray-700">🌆 Evening (5-8 PM)</span>
                                    </label>
                                </div>
                            </div>

                            <button 
                                type="submit" 
                                class="w-full bg-slate-900 hover:bg-black text-white font-extrabold py-3.5 rounded-xl shadow transition uppercase tracking-wider flex items-center justify-center gap-2 text-xs cursor-pointer"
                            >
                                <span>DELIVER TO SELECTED SAVED ADDRESS ➔</span>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- ======================================================= -->
                <!-- FRUITKART LIVE INTERACTIVE MAP LOCATION PICKER          -->
                <!-- ======================================================= -->
                <div id="swiggy-map-card" class="bg-white rounded-2xl shadow-sm border-2 border-emerald-500/40 overflow-hidden space-y-0">
                    
                    <!-- FruitKart Map Top Header -->
                    <div class="p-4 bg-gradient-to-r from-emerald-600 via-teal-600 to-blue-700 text-white flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white text-base">
                                <i class="fa-solid fa-apple-whole text-amber-300"></i>
                            </span>
                            <div>
                                <h3 class="text-sm font-black uppercase tracking-wider">FRUITKART LIVE MAP LOCATION PICKER</h3>
                                <p class="text-[11px] text-emerald-100">Map ko drag karein ya search karke apna exact delivery point set karein</p>
                            </div>
                        </div>
                        <span class="text-[10px] bg-white text-emerald-800 font-extrabold px-3 py-1 rounded-full uppercase tracking-wider shadow-sm flex items-center gap-1.5">
                            <i class="fa-solid fa-satellite-dish text-emerald-600 animate-pulse"></i> Realtime GPS
                        </span>
                    </div>

                    <!-- Map Frame with Center Pin, Search & Locate Me -->
                    <div class="swiggy-map-wrapper">
                        
                        <!-- Floating Live Search Input with Instant Autocomplete & Quick City Chips -->
                        <div class="swiggy-floating-search-bar">
                            <div class="relative flex items-center">
                                <i class="fa-solid fa-magnifying-glass absolute left-3.5 text-gray-400 text-sm"></i>
                                <input 
                                    type="text" 
                                    id="swiggy-search-input" 
                                    placeholder="Village, Town, Pincode ya Google Maps link paste karein..." 
                                    autocomplete="off"
                                    class="w-full text-xs pl-10 pr-20 py-3 bg-white/95 backdrop-blur-md rounded-2xl border border-gray-200 shadow-xl font-semibold text-gray-900 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
                                >
                                <!-- Clear button -->
                                <button type="button" id="swiggy-clear-search" onclick="clearSwiggySearch()" class="hidden absolute right-12 text-gray-400 hover:text-gray-700 text-sm">
                                    <i class="fa-solid fa-circle-xmark"></i>
                                </button>
                                <!-- Search Action Button -->
                                <button type="button" onclick="triggerSearchNow()" class="absolute right-2 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition active:scale-95 flex items-center gap-1">
                                    <span id="search-btn-icon"><i class="fa-solid fa-arrow-right"></i></span>
                                    <span>Find</span>
                                </button>
                            </div>
                            
                            <!-- Autocomplete Suggestions Dropdown -->
                            <div id="swiggy-search-suggestions" class="hidden mt-2 bg-white rounded-2xl shadow-2xl border border-gray-100 max-h-60 overflow-y-auto divide-y divide-gray-100 z-[1001]">
                            </div>

                            <!-- Fast 1-Tap Location Chips -->
                            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pt-2 px-0.5">
                                <button type="button" onclick="selectQuickCity('Nanhera (Village), Roorkee', 29.896790, 77.817616)" class="px-2.5 py-1 rounded-full bg-white/90 backdrop-blur-sm text-gray-800 text-[10px] font-bold shadow-xs border border-gray-200 hover:bg-emerald-50 hover:border-emerald-300 transition shrink-0 active:scale-95">
                                    📍 Nanhera (Roorkee)
                                </button>
                                <button type="button" onclick="selectQuickCity('Shahpur, Bihar', 25.659258, 84.429475)" class="px-2.5 py-1 rounded-full bg-white/90 backdrop-blur-sm text-gray-800 text-[10px] font-bold shadow-xs border border-gray-200 hover:bg-emerald-50 hover:border-emerald-300 transition shrink-0 active:scale-95">
                                    📍 Shahpur (Bhojpur)
                                </button>
                                <button type="button" onclick="selectQuickCity('Arrah, Bhojpur', 25.556000, 84.660300)" class="px-2.5 py-1 rounded-full bg-white/90 backdrop-blur-sm text-gray-800 text-[10px] font-bold shadow-xs border border-gray-200 hover:bg-emerald-50 hover:border-emerald-300 transition shrink-0 active:scale-95">
                                    📍 Arrah
                                </button>
                                <button type="button" onclick="selectQuickCity('Patna, Bihar', 25.594100, 85.137600)" class="px-2.5 py-1 rounded-full bg-white/90 backdrop-blur-sm text-gray-800 text-[10px] font-bold shadow-xs border border-gray-200 hover:bg-emerald-50 hover:border-emerald-300 transition shrink-0 active:scale-95">
                                    📍 Patna
                                </button>
                                <button type="button" onclick="selectQuickCity('Gurugram, Haryana', 28.459500, 77.026600)" class="px-2.5 py-1 rounded-full bg-white/90 backdrop-blur-sm text-gray-800 text-[10px] font-bold shadow-xs border border-gray-200 hover:bg-emerald-50 hover:border-emerald-300 transition shrink-0 active:scale-95">
                                    📍 Gurugram / Delhi NCR
                                </button>
                                <button type="button" onclick="selectQuickCity('Noida, UP', 28.535500, 77.391000)" class="px-2.5 py-1 rounded-full bg-white/90 backdrop-blur-sm text-gray-800 text-[10px] font-bold shadow-xs border border-gray-200 hover:bg-emerald-50 hover:border-emerald-300 transition shrink-0 active:scale-95">
                                    📍 Noida
                                </button>
                            </div>
                        </div>

                        <!-- Leaflet Map Container -->
                        <div id="swiggy-map"></div>

                        <!-- FruitKart Animated Center Pin -->
                        <div class="swiggy-center-pin-wrapper" id="swiggy-pin-wrapper">
                            <div class="swiggy-pin-tag">
                                <i class="fa-solid fa-apple-whole text-amber-400"></i>
                                <span>FruitKart Order will be delivered here</span>
                            </div>
                            <div class="swiggy-pin-marker">
                                <i class="fa-solid fa-house"></i>
                            </div>
                            <div class="swiggy-pin-pulse"></div>
                        </div>

                        <!-- Floating Map Layer Switcher (Google Map Roadmap / Satellite View) -->
                        <div class="swiggy-floating-layer-toggle">
                            <button type="button" id="btn-layer-roadmap" onclick="switchMapLayer('roadmap')" class="map-layer-btn active">
                                <i class="fa-solid fa-map"></i> <span>Google Map</span>
                            </button>
                            <button type="button" id="btn-layer-satellite" onclick="switchMapLayer('satellite')" class="map-layer-btn">
                                <i class="fa-solid fa-earth-americas"></i> <span>Satellite</span>
                            </button>
                        </div>

                        <!-- Floating Locate Me Button -->
                        <button 
                            type="button" 
                            id="swiggy-locate-me-btn" 
                            onclick="locateUserCurrentPosition()" 
                            class="swiggy-floating-locate-btn"
                            title="Current GPS Location Detect Karein"
                        >
                            <i class="fa-solid fa-crosshairs text-base text-amber-600" id="locate-me-icon"></i>
                            <span id="locate-me-label">Locate Me</span>
                        </button>

                    </div>

                    <!-- Swiggy Doorstep Details Bottom Sheet / Confirmation Card -->
                    <div class="p-5 sm:p-6 bg-white space-y-4">
                        
                        <!-- Area Headline & Address Feedback -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3.5 border-b border-gray-100">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 text-white flex items-center justify-center text-lg shadow-sm shrink-0">
                                    <i class="fa-solid fa-location-dot"></i>
                                </div>
                                <div>
                                    <div class="text-[10px] font-black uppercase tracking-wider text-amber-600">SELECTED DELIVERY LOCATION</div>
                                    <h3 id="swiggy-area-title" class="text-base font-black text-gray-900 leading-tight">Sector 18, Gurugram</h3>
                                    <p id="swiggy-full-address-text" class="text-xs text-gray-500 mt-0.5 leading-relaxed">
                                        Gurugram, Haryana, India
                                    </p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1.5 bg-emerald-100 text-emerald-800 text-[10px] font-extrabold px-3 py-1 rounded-full shrink-0 self-start sm:self-center border border-emerald-200">
                                <i class="fa-solid fa-circle-check text-emerald-600"></i> Pin Verified
                            </span>
                        </div>

                        <!-- Doorstep Details Form -->
                        <form action="<?= SITE_URL ?>/checkout.php" method="POST" id="swiggy-address-form" class="space-y-3.5">
                            <input type="hidden" name="save_and_proceed" value="1">
                            
                            <!-- Hidden GPS Inputs -->
                            <input type="hidden" name="latitude" id="swiggy-input-lat" value="28.459500">
                            <input type="hidden" name="longitude" id="swiggy-input-lng" value="77.026600">
                            <input type="hidden" name="maps_link" id="swiggy-input-maps" value="https://www.google.com/maps?q=28.459500,77.026600">
                            <input type="hidden" name="gps_address" id="swiggy-input-gps" value="Sector 18, Gurugram, Haryana">
                            <input type="hidden" name="city" id="swiggy-input-city" value="Gurugram">
                            <input type="hidden" name="state" id="swiggy-input-state" value="Haryana">
                            <input type="hidden" name="pincode" id="swiggy-input-pincode" value="122001">

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wide mb-1">
                                        House / Flat / Floor / Building <span class="text-rose-500">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        name="address_line" 
                                        id="swiggy-flat-input"
                                        required 
                                        placeholder="e.g. Flat 402, 4th Floor, Tower B" 
                                        class="w-full text-xs px-3.5 py-3 border border-gray-300 rounded-xl font-medium focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                                    >
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wide mb-1">
                                        Apartment / Road / Area <span class="text-rose-500">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        name="locality" 
                                        id="swiggy-locality-input"
                                        required 
                                        placeholder="e.g. DLF Cyber City, Sector 18" 
                                        value="Sector 18"
                                        class="w-full text-xs px-3.5 py-3 border border-gray-300 rounded-xl font-medium focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                                    >
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wide mb-1">
                                        Receiver Full Name <span class="text-rose-500">*</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        name="full_name" 
                                        value="<?= htmlspecialchars($user['name']) ?>" 
                                        required 
                                        class="w-full text-xs px-3.5 py-3 border border-gray-300 rounded-xl font-medium focus:outline-none focus:border-amber-500"
                                    >
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wide mb-1">
                                        10-Digit Mobile Number (Delivery Contact) <span class="text-rose-500">*</span>
                                    </label>
                                    <input 
                                        type="tel" 
                                        name="phone" 
                                        value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                                        required 
                                        maxlength="10" 
                                        placeholder="e.g. 9876543210" 
                                        class="w-full text-xs px-3.5 py-3 border border-gray-300 rounded-xl font-medium focus:outline-none focus:border-amber-500"
                                    >
                                </div>
                            </div>

                            <!-- Save Address As Chips (Swiggy Style) -->
                            <div>
                                <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wide mb-1.5">Save Address As:</label>
                                <div class="flex items-center gap-2 text-xs">
                                    <label class="cursor-pointer">
                                        <input type="radio" name="address_type" value="Home" checked class="hidden peer">
                                        <div class="px-4 py-2 rounded-xl border border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 peer-checked:text-amber-900 font-bold flex items-center gap-1.5 transition">
                                            <i class="fa-solid fa-house"></i> Home
                                        </div>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="address_type" value="Work" class="hidden peer">
                                        <div class="px-4 py-2 rounded-xl border border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 peer-checked:text-amber-900 font-bold flex items-center gap-1.5 transition">
                                            <i class="fa-solid fa-briefcase"></i> Work
                                        </div>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="address_type" value="Other" class="hidden peer">
                                        <div class="px-4 py-2 rounded-xl border border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 peer-checked:text-amber-900 font-bold flex items-center gap-1.5 transition">
                                            <i class="fa-solid fa-location-dot"></i> Other
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Delivery Slot Preference -->
                            <div class="pt-2">
                                <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wide mb-1.5">Delivery Time Slot:</label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <label class="p-2.5 border border-emerald-400 bg-emerald-50/50 rounded-xl flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="delivery_slot" value="Express Slot (Within 2 Hours)" checked class="text-amber-500">
                                        <span class="text-xs font-bold text-emerald-900">⚡ 2-Hour Express</span>
                                    </label>
                                    <label class="p-2.5 border border-gray-200 rounded-xl flex items-center gap-2 cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="delivery_slot" value="Morning 7:00 AM - 10:00 AM" class="text-amber-500">
                                        <span class="text-xs font-semibold text-gray-700">🌅 Morning (7-10 AM)</span>
                                    </label>
                                    <label class="p-2.5 border border-gray-200 rounded-xl flex items-center gap-2 cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="delivery_slot" value="Evening 5:00 PM - 8:00 PM" class="text-amber-500">
                                        <span class="text-xs font-semibold text-gray-700">🌆 Evening (5-8 PM)</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Swiggy Big CTA Button -->
                            <button 
                                type="submit" 
                                class="w-full bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white font-black py-4 rounded-xl shadow-lg transition transform active:scale-[0.99] uppercase tracking-wider flex items-center justify-center gap-2 text-sm cursor-pointer mt-4"
                            >
                                <span>CONFIRM LOCATION &amp; PROCEED TO PAYMENT</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>
                        </form>

                    </div>

                </div>

            </div>

        </div>

        <!-- Right Order Summary & Price Box (4 cols) -->
        <div class="lg:col-span-4 space-y-4">
            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden sticky top-24">
                <div class="p-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                    <h3 class="text-xs font-extrabold text-gray-500 uppercase tracking-wider">Price Details</h3>
                    <span class="text-xs text-gray-500"><?= $totals['item_count'] ?> Fruit Items</span>
                </div>

                <div class="p-4 space-y-3 text-xs">
                    <div class="flex justify-between text-gray-700">
                        <span>Price (MRP Total)</span>
                        <span><?= formatPrice($totals['total_mrp']) ?></span>
                    </div>

                    <div class="flex justify-between text-emerald-600 font-medium">
                        <span>Special Discount</span>
                        <span>&minus; <?= formatPrice($totals['discount']) ?></span>
                    </div>

                    <?php if ($couponDiscount > 0): ?>
                        <div class="flex justify-between text-emerald-600 font-medium">
                            <span>Coupon (<?= htmlspecialchars($couponCode) ?>)</span>
                            <span>&minus; <?= formatPrice($couponDiscount) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="flex justify-between text-gray-700 items-center">
                        <span>Delivery Charges</span>
                        <?php if ($totals['delivery_fee'] == 0): ?>
                            <span class="text-emerald-600 font-bold uppercase"><span class="line-through text-gray-400 font-normal mr-1">₹30</span> FREE</span>
                        <?php else: ?>
                            <span class="text-gray-900 font-bold"><?= formatPrice($totals['delivery_fee']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="border-t border-dashed border-gray-200 pt-3 flex justify-between text-sm font-black text-gray-900">
                        <span>Total Payable</span>
                        <span class="text-base text-gray-900"><?= formatPrice($totals['final_amount']) ?></span>
                    </div>

                    <div class="bg-emerald-50 text-emerald-800 p-2.5 rounded-xl border border-emerald-200 text-center font-bold text-xs">
                        🎉 Total Savings: <?= formatPrice($totals['discount'] + $couponDiscount) ?>
                    </div>
                </div>

                <!-- Items Preview -->
                <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                    <div class="text-xs font-bold text-gray-700 mb-2">Order Items (<?= count($items) ?>):</div>
                    <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                        <?php foreach ($items as $item): ?>
                            <div class="flex items-center justify-between text-xs">
                                <span class="truncate max-w-[180px] text-gray-700"><?= htmlspecialchars($item['name']) ?> &times; <?= $item['quantity'] ?></span>
                                <span class="font-bold text-gray-900"><?= formatPrice($item['selling_price'] * $item['quantity']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

<!-- Swiggy Interactive Map JavaScript Engine -->
<script>
const savedAddressesData = <?= $addressesJson ?>;
let swiggyMap = null;
let searchDebounceTimer = null;
let googleRoadmapLayer = null;
let googleSatelliteLayer = null;
let activeTileLayer = null;

// Initial coordinates: first saved address or Gurugram / Delhi NCR default
let defaultLat = savedAddressesData.length > 0 && savedAddressesData[0].lat ? savedAddressesData[0].lat : 28.459500;
let defaultLng = savedAddressesData.length > 0 && savedAddressesData[0].lng ? savedAddressesData[0].lng : 77.026600;

function initSwiggyMap(lat, lng) {
    const mapElement = document.getElementById('swiggy-map');
    if (!mapElement) return;

    const startLat = parseFloat(lat) || defaultLat;
    const startLng = parseFloat(lng) || defaultLng;

    if (swiggyMap) {
        swiggyMap.setView([startLat, startLng], 16);
        swiggyMap.invalidateSize();
        return;
    }

    try {
        swiggyMap = L.map('swiggy-map', {
            center: [startLat, startLng],
            zoom: 16,
            zoomControl: false,
            scrollWheelZoom: true
        });

        // 1. Google Maps Roadmap Layer (Full Indian Village, City, Road labels)
        googleRoadmapLayer = L.tileLayer('https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: '&copy; Google Maps'
        });

        // 2. Google Maps Satellite Hybrid Layer (Real satellite imagery + roads/villages)
        googleSatelliteLayer = L.tileLayer('https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: '&copy; Google Satellite'
        });

        // Add Google Roadmap by default
        googleRoadmapLayer.addTo(swiggyMap);
        activeTileLayer = googleRoadmapLayer;

        // Subtle zoom control at bottom left
        L.control.zoom({ position: 'bottomleft' }).addTo(swiggyMap);

        const pinWrapper = document.getElementById('swiggy-pin-wrapper');

        // 1. Swiggy Center Pin dynamics: lift pin on drag start
        swiggyMap.on('movestart', function () {
            if (pinWrapper) pinWrapper.classList.add('pin-floating');
        });

        // 2. Drop pin on drag end & trigger reverse geocoding
        swiggyMap.on('moveend', function () {
            if (pinWrapper) pinWrapper.classList.remove('pin-floating');
            const center = swiggyMap.getCenter();
            onMapCenterChanged(center.lat, center.lng);
        });

        // 3. Click anywhere on map to pan smoothly to that point
        swiggyMap.on('click', function (e) {
            swiggyMap.panTo(e.latlng);
        });

        // Perform initial geocode for the starting center
        onMapCenterChanged(startLat, startLng);

    } catch (err) {
        console.error('Swiggy Map init error:', err);
    }
}

// Switch between Google Roadmap and Satellite view
function switchMapLayer(mode) {
    if (!swiggyMap) return;
    const btnRoadmap = document.getElementById('btn-layer-roadmap');
    const btnSatellite = document.getElementById('btn-layer-satellite');

    if (mode === 'satellite') {
        if (swiggyMap.hasLayer(googleRoadmapLayer)) swiggyMap.removeLayer(googleRoadmapLayer);
        googleSatelliteLayer.addTo(swiggyMap);
        activeTileLayer = googleSatelliteLayer;
        if (btnSatellite) btnSatellite.classList.add('active');
        if (btnRoadmap) btnRoadmap.classList.remove('active');
    } else {
        if (swiggyMap.hasLayer(googleSatelliteLayer)) swiggyMap.removeLayer(googleSatelliteLayer);
        googleRoadmapLayer.addTo(swiggyMap);
        activeTileLayer = googleRoadmapLayer;
        if (btnRoadmap) btnRoadmap.classList.add('active');
        if (btnSatellite) btnSatellite.classList.remove('active');
    }
}

// Helper: Parse coordinates or Google Maps link directly
function parseCoordinatesOrGoogleLink(text) {
    if (!text) return null;
    const cleanText = text.trim();
    // 1. Google Maps @lat,lng format e.g. /@29.8995388,77.8171254,13.93z
    let match = cleanText.match(/@(-?\d+\.\d+),(-?\d+\.\d+)/);
    if (match) return { lat: parseFloat(match[1]), lng: parseFloat(match[2]) };
    // 2. Google Maps q=lat,lng or ll=lat,lng format
    match = cleanText.match(/[?&](?:q|ll)=(-?\d+\.\d+),(-?\d+\.\d+)/);
    if (match) return { lat: parseFloat(match[1]), lng: parseFloat(match[2]) };
    // 3. Direct coordinates: 29.8995388, 77.8171254 or 29.8995388 77.8171254
    match = cleanText.match(/^(-?\d+\.\d+)[\s,]+(-?\d+\.\d+)$/);
    if (match) return { lat: parseFloat(match[1]), lng: parseFloat(match[2]) };
    return null;
}

// Reverse Geocoding with First-Class Indian Village & Town Detection
async function onMapCenterChanged(lat, lng) {
    const latFormatted = Number(lat).toFixed(6);
    const lngFormatted = Number(lng).toFixed(6);

    // Update hidden inputs
    const inputLat = document.getElementById('swiggy-input-lat');
    const inputLng = document.getElementById('swiggy-input-lng');
    const inputMaps = document.getElementById('swiggy-input-maps');
    const savedLat = document.getElementById('saved-input-lat');
    const savedLng = document.getElementById('saved-input-lng');

    if (inputLat) inputLat.value = latFormatted;
    if (inputLng) inputLng.value = lngFormatted;
    if (savedLat) savedLat.value = latFormatted;
    if (savedLng) savedLng.value = lngFormatted;

    const mapsUrl = `https://www.google.com/maps?q=${latFormatted},${lngFormatted}`;
    if (inputMaps) inputMaps.value = mapsUrl;

    const areaTitle = document.getElementById('swiggy-area-title');
    const fullAddr = document.getElementById('swiggy-full-address-text');
    const localityInput = document.getElementById('swiggy-locality-input');
    const cityInput = document.getElementById('swiggy-input-city');
    const stateInput = document.getElementById('swiggy-input-state');
    const pincodeInput = document.getElementById('swiggy-input-pincode');
    const gpsInput = document.getElementById('swiggy-input-gps');
    const flatInput = document.getElementById('swiggy-flat-input');

    if (areaTitle) {
        areaTitle.innerHTML = '<span class="text-amber-600 animate-pulse"><i class="fa-solid fa-spinner fa-spin text-xs"></i> Detecting village/location...</span>';
    }

    try {
        const response = await fetch(`<?= SITE_URL ?>/api/search-location.php?reverse=1&lat=${latFormatted}&lon=${lngFormatted}`);
        const data = await response.json();

        if (data && data.address) {
            const addr = data.address;

            // First-class Village, Hamlet & Town detection for rural & urban areas
            const village = addr.village || addr.hamlet || addr.isolated_dwelling || '';
            const localArea = addr.suburb || addr.neighbourhood || addr.residential || addr.road || addr.quarter || '';
            const townOrCity = addr.city || addr.town || addr.municipality || '';
            const county = addr.county || '';
            const district = addr.state_district || addr.county || '';
            const state = addr.state || 'India';
            const pincode = addr.postcode || '';

            let mainHeading = '';
            let subHeading = '';

            if (village) {
                // Village clearly identified
                mainHeading = `🏡 ${village} (Village / Gram)`;
                subHeading = [localArea, county || district, townOrCity, state].filter(Boolean).join(', ');
            } else if (localArea) {
                mainHeading = localArea;
                subHeading = [townOrCity || district, state].filter(Boolean).join(', ');
            } else if (townOrCity) {
                mainHeading = townOrCity;
                subHeading = [district, state].filter(Boolean).join(', ');
            } else {
                mainHeading = district || 'Selected Point';
                subHeading = state;
            }

            if (areaTitle) areaTitle.innerHTML = `<span class="font-black text-emerald-800">${escapeHtml(mainHeading)}</span><span class="text-gray-500 font-medium text-xs"> &bull; ${escapeHtml(subHeading)}</span>`;
            if (fullAddr) fullAddr.innerText = data.display_name || `${mainHeading}, ${subHeading}`;
            
            if (localityInput) localityInput.value = [village || mainHeading.replace(/^[^a-zA-Z0-9]+/, ''), localArea, county].filter(Boolean).join(', ');
            if (cityInput) cityInput.value = townOrCity || district || county || 'Local Area';
            if (stateInput) stateInput.value = state;
            if (pincodeInput && pincode) pincodeInput.value = pincode;
            if (gpsInput) gpsInput.value = data.display_name || `${mainHeading}, ${subHeading}`;

            // Auto-populate doorstep address so confirming location is ready in 1 click!
            if (flatInput && (!flatInput.value || flatInput.value.trim() === '' || flatInput.value.startsWith('Makan / House') || flatInput.value.startsWith('House / Doorstep') || flatInput.value.startsWith('Near '))) {
                if (village) {
                    flatInput.value = `Makan / House, Near Village Center, ${village}`;
                } else if (localArea) {
                    flatInput.value = `House / Doorstep Point, ${localArea}`;
                } else {
                    flatInput.value = `House / Doorstep Delivery Point`;
                }
            }
        } else {
            if (areaTitle) areaTitle.innerText = `Pin: ${latFormatted}, ${lngFormatted}`;
            if (fullAddr) fullAddr.innerText = `GPS: Lat ${latFormatted}, Lng ${lngFormatted}`;
        }
    } catch (e) {
        if (areaTitle) areaTitle.innerText = `Pin: ${latFormatted}, ${lngFormatted}`;
        if (fullAddr) fullAddr.innerText = `GPS Location Coordinates Attached`;
    }
}

// Swiggy Search Autocomplete via Internal Proxy (Zero CORS / 100% Guaranteed Search)
const searchInput = document.getElementById('swiggy-search-input');
const suggestionsBox = document.getElementById('swiggy-search-suggestions');
const clearSearchBtn = document.getElementById('swiggy-clear-search');

async function performLocationSearch(query) {
    if (!query || query.length < 2) {
        if (suggestionsBox) suggestionsBox.classList.add('hidden');
        return;
    }

    // Direct Google Maps Link or Raw Lat/Lng Check
    const parsedCoords = parseCoordinatesOrGoogleLink(query);
    if (parsedCoords) {
        if (swiggyMap) {
            swiggyMap.flyTo([parsedCoords.lat, parsedCoords.lng], 17, { animate: true, duration: 1.2 });
        }
        if (suggestionsBox) suggestionsBox.classList.add('hidden');
        onMapCenterChanged(parsedCoords.lat, parsedCoords.lng);
        return;
    }

    if (suggestionsBox) {
        suggestionsBox.innerHTML = `
            <div class="p-3 text-xs text-emerald-700 flex items-center justify-center gap-2">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <span>Searching "${escapeHtml(query)}"...</span>
            </div>
        `;
        suggestionsBox.classList.remove('hidden');
    }

    try {
        const res = await fetch(`<?= SITE_URL ?>/api/search-location.php?q=${encodeURIComponent(query)}`);
        const results = await res.json();

        if (Array.isArray(results) && results.length > 0) {
            renderSearchSuggestions(results);
        } else {
            if (suggestionsBox) {
                suggestionsBox.innerHTML = `
                    <div class="p-3 text-xs text-gray-500 text-center space-y-1">
                        <div>Koi matching village ya city nahi mila for "${escapeHtml(query)}".</div>
                        <div class="text-[10px] text-emerald-600 font-bold">Aap Google Maps link paste kar sakte hain ya map par pin drag karein!</div>
                    </div>
                `;
                suggestionsBox.classList.remove('hidden');
            }
        }
    } catch (err) {
        console.error('Search error:', err);
        if (suggestionsBox) {
            suggestionsBox.innerHTML = `
                <div class="p-3 text-xs text-gray-500 text-center">
                    Location dhundne me error aaya. Map par pin drag karein.
                </div>
            `;
        }
    }
}

function triggerSearchNow() {
    if (searchInput) {
        const query = searchInput.value.trim();
        performLocationSearch(query);
    }
}

if (searchInput) {
    searchInput.addEventListener('input', function () {
        const query = this.value.trim();
        if (clearSearchBtn) {
            clearSearchBtn.classList.toggle('hidden', query.length === 0);
        }

        // Instant intercept if user pasted Google Maps link or coordinates
        const parsedCoords = parseCoordinatesOrGoogleLink(query);
        if (parsedCoords) {
            clearTimeout(searchDebounceTimer);
            if (swiggyMap) swiggyMap.flyTo([parsedCoords.lat, parsedCoords.lng], 17, { animate: true, duration: 1.2 });
            if (suggestionsBox) suggestionsBox.classList.add('hidden');
            onMapCenterChanged(parsedCoords.lat, parsedCoords.lng);
            return;
        }

        clearTimeout(searchDebounceTimer);
        if (query.length < 2) {
            if (suggestionsBox) suggestionsBox.classList.add('hidden');
            return;
        }

        searchDebounceTimer = setTimeout(() => {
            performLocationSearch(query);
        }, 350);
    });

    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            triggerSearchNow();
        }
    });
}

function renderSearchSuggestions(items) {
    if (!suggestionsBox) return;

    let html = '';
    items.forEach(item => {
        const title = item.name || 'Location';
        const subtitle = item.display_name || '';

        html += `
            <div 
                onclick="selectLocationSuggestion(${item.lat}, ${item.lon}, '${escapeHtml(title).replace(/'/g, "\\'")}')" 
                class="p-3 hover:bg-emerald-50 cursor-pointer flex items-start gap-2.5 transition text-left"
            >
                <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs shrink-0 mt-0.5">
                    <i class="fa-solid fa-location-dot"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="text-xs font-bold text-gray-900 truncate">${escapeHtml(title)}</div>
                    <div class="text-[11px] text-gray-500 truncate">${escapeHtml(subtitle)}</div>
                </div>
            </div>
        `;
    });

    suggestionsBox.innerHTML = html;
    suggestionsBox.classList.remove('hidden');
}

function selectLocationSuggestion(lat, lng, name) {
    if (swiggyMap) {
        swiggyMap.flyTo([lat, lng], 17, { animate: true, duration: 1.2 });
    }
    if (suggestionsBox) suggestionsBox.classList.add('hidden');
    if (searchInput) searchInput.value = name;
    if (clearSearchBtn) clearSearchBtn.classList.remove('hidden');
    onMapCenterChanged(lat, lng);
}

function selectQuickCity(name, lat, lng) {
    if (swiggyMap) {
        swiggyMap.flyTo([lat, lng], 16, { animate: true, duration: 1.2 });
    }
    if (searchInput) searchInput.value = name;
    if (clearSearchBtn) clearSearchBtn.classList.remove('hidden');
    if (suggestionsBox) suggestionsBox.classList.add('hidden');
    onMapCenterChanged(lat, lng);
}

function clearSwiggySearch() {
    if (searchInput) {
        searchInput.value = '';
        searchInput.focus();
    }
    if (clearSearchBtn) clearSearchBtn.classList.add('hidden');
    if (suggestionsBox) suggestionsBox.classList.add('hidden');
}

function escapeHtml(str) {
    return (str || '').replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}

// Locate Me (Current GPS)
function locateUserCurrentPosition() {
    const btn = document.getElementById('swiggy-locate-me-btn');
    const label = document.getElementById('locate-me-label');
    const icon = document.getElementById('locate-me-icon');

    if (!navigator.geolocation) {
        alert('Aapke browser me Geolocation support nahi hai.');
        return;
    }

    if (label) label.innerText = 'Locating...';
    if (icon) icon.className = 'fa-solid fa-spinner fa-spin text-base text-amber-600';

    navigator.geolocation.getCurrentPosition(
        function (pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;

            if (swiggyMap) {
                swiggyMap.flyTo([lat, lng], 17, { animate: true, duration: 1.5 });
            } else {
                initSwiggyMap(lat, lng);
            }

            if (label) label.innerText = 'Locate Me';
            if (icon) icon.className = 'fa-solid fa-crosshairs text-base text-emerald-600';
        },
        function (err) {
            if (label) label.innerText = 'Locate Me';
            if (icon) icon.className = 'fa-solid fa-crosshairs text-base text-amber-600';

            let msg = 'Location detect nahi ho saki. Aap map par pin drag karke location chun sakte hain.';
            if (err.code === err.PERMISSION_DENIED) {
                msg = 'Location permission allow nahi hai. Kripya map ko drag karke apna location set karein!';
            }
            alert(msg);
        },
        { enableHighAccuracy: true, timeout: 9000, maximumAge: 0 }
    );
}

function focusSwiggyMap() {
    const card = document.getElementById('swiggy-map-card');
    if (card) {
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => {
            if (swiggyMap) swiggyMap.invalidateSize();
        }, 200);
    }
}

function onSavedAddressRadioChange(id, lat, lng) {
    if (swiggyMap && lat && lng) {
        swiggyMap.flyTo([lat, lng], 16, { animate: true, duration: 1.0 });
    }
}

// Close suggestions on outside click
document.addEventListener('click', function (e) {
    if (suggestionsBox && !suggestionsBox.contains(e.target) && e.target !== searchInput) {
        suggestionsBox.classList.add('hidden');
    }
});

// Initialize Swiggy Map on Load
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        initSwiggyMap(defaultLat, defaultLng);
    }, 250);
});
</script>

<?php require_once __DIR__ . '/includes/checkout-footer.php'; ?>
