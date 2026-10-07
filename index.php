<?php
$pageTitle = "Fresh Farm Fruits Delivered in 2 Hours";
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Deals of the day
$deals = $db->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.is_deal = 1 AND p.status = 'active' 
    LIMIT 6
")->fetchAll();

// Featured Fruits
$featured = $db->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.is_featured = 1 AND p.status = 'active' 
    LIMIT 8
")->fetchAll();

// Exotic & Imported Fruits
$exotic = $db->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.category_id = 2 AND p.status = 'active' 
    LIMIT 4
")->fetchAll();

$personalizedName = !empty($user['name']) ? $user['name'] : 'Karan Kumar gupta';
?>

<div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-3 space-y-4 sm:space-y-6">

    <!-- ======================================================== -->
    <!-- 1. HERO PROMO BANNER CAROUSEL (Matching Flipkart Screenshot) -->
    <!-- ======================================================== -->
    <div>
        <div class="relative rounded-2xl md:rounded-3xl overflow-hidden shadow-md bg-gradient-to-r from-blue-700 via-sky-600 to-indigo-800 text-white min-h-[170px] md:min-h-[280px] flex items-center p-4 sm:p-8">
            <!-- Decorative Background Graphic -->
            <div class="absolute inset-0 opacity-15 bg-[radial-gradient(#ffffff_1.5px,transparent_1.5px)] [background-size:16px_16px]"></div>
            
            <div class="relative z-10 flex flex-col md:flex-row items-center justify-between w-full gap-4">
                <div class="space-y-1.5 sm:space-y-2.5 max-w-xl">
                    <div class="flex items-center gap-2">
                        <span class="bg-black/30 backdrop-blur-xs text-yellow-300 text-[10px] sm:text-xs font-black px-2.5 py-0.5 rounded-full uppercase tracking-wider flex items-center gap-1 border border-white/20">
                            <i class="fa-solid fa-bolt text-yellow-300"></i> The Big Fresh Days
                        </span>
                        <span class="bg-white/20 text-white text-[9px] sm:text-[10px] font-bold px-2 py-0.5 rounded-full">
                            Starts Today
                        </span>
                    </div>

                    <h1 class="text-xl sm:text-3xl lg:text-4xl font-black tracking-tight leading-tight">
                        Alphonso &amp; Royal Gala Apples <br class="hidden sm:inline">
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-yellow-300 via-amber-200 to-white">
                            From ₹49/kg*
                        </span>
                    </h1>

                    <p class="text-sky-100 text-[11px] sm:text-sm font-medium">
                        Endless Sweetness &bull; Tested Brix Sweetness &bull; Cold Chain Delivered in 30 Mins
                    </p>

                    <div class="pt-1 flex items-center gap-2.5">
                        <a href="<?= SITE_URL ?>/products.php" class="bg-fk-yellow hover:bg-yellow-400 text-gray-950 font-black px-4 py-2 sm:px-6 sm:py-2.5 rounded-xl shadow-md text-xs sm:text-sm transition active:scale-95 flex items-center gap-1.5">
                            <span>Shop Now</span>
                            <i class="fa-solid fa-arrow-right text-[11px]"></i>
                        </a>
                        <span class="text-[10px] sm:text-xs text-white/80 font-bold border border-white/25 px-2.5 py-1.5 rounded-xl bg-white/10 backdrop-blur-xs">
                            100% Guaranteed Fresh
                        </span>
                    </div>
                </div>

                <!-- Banner Right Visual Art -->
                <div class="hidden sm:flex items-center justify-center shrink-0">
                    <div class="w-36 h-36 md:w-56 md:h-56 rounded-2xl overflow-hidden shadow-2xl border-2 border-white/30 transform rotate-2 hover:rotate-0 transition duration-300 bg-white/10 backdrop-blur-xs p-1">
                        <img src="https://images.unsplash.com/photo-1610832958506-aa56368176cf?w=500&q=80" alt="Fresh Produce" class="w-full h-full object-cover rounded-xl">
                    </div>
                </div>
            </div>

            <span class="absolute bottom-2 right-3 text-[9px] bg-black/40 text-gray-300 px-1.5 py-0.5 rounded uppercase font-bold tracking-wider">AD</span>
        </div>

        <!-- Dot Pagination Indicator (Matching Flipkart Screenshot) -->
        <div class="flex items-center justify-center gap-1.5 mt-2.5">
            <span class="w-1.5 h-1.5 bg-gray-300 rounded-full"></span>
            <span class="w-1.5 h-1.5 bg-gray-300 rounded-full"></span>
            <span class="w-6 h-1.5 bg-gray-900 rounded-full"></span>
            <span class="w-1.5 h-1.5 bg-gray-300 rounded-full"></span>
            <span class="w-1.5 h-1.5 bg-gray-300 rounded-full"></span>
            <span class="w-1.5 h-1.5 bg-gray-300 rounded-full"></span>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- 2. PERSONALIZED CARD (Exact Match to Screenshot Lavender Box) -->
    <!-- "Karan Kumar gupta, top picks for you! ..."              -->
    <!-- ======================================================== -->
    <div class="bg-[#ede9fe] rounded-3xl p-3.5 sm:p-5 shadow-xs border border-purple-200/60">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm sm:text-base font-extrabold text-gray-900 truncate tracking-tight">
                <?= htmlspecialchars($personalizedName) ?>, top picks for you! ...
            </h3>
            <a href="<?= SITE_URL ?>/products.php" class="text-xs font-bold text-fk-blue hover:underline shrink-0">
                View all &rarr;
            </a>
        </div>

        <!-- Horizontal Scrollable Cards with Badges (↓60%, New Launch, ↓52%) -->
        <div class="flex items-stretch gap-3 overflow-x-auto no-scrollbar pb-1">
            <?php 
            $badges = ['↓60%', 'New Launch', '↓52%', 'Bestseller', 'Hot Deal'];
            $bIdx = 0;
            foreach ($featured as $p): 
                $badgeText = $badges[$bIdx % count($badges)];
                $bIdx++;
                $isDiscount = strpos($badgeText, '↓') !== false;
            ?>
                <div class="bg-white rounded-2xl p-2.5 w-36 sm:w-44 shrink-0 shadow-xs border border-purple-100 flex flex-col justify-between group hover:shadow-md transition">
                    <div>
                        <!-- Top Left Badge -->
                        <span class="<?= $isDiscount ? 'bg-blue-600 text-white' : ($badgeText === 'New Launch' ? 'bg-sky-600 text-white' : 'bg-emerald-600 text-white') ?> text-[9px] font-black px-2 py-0.5 rounded-md inline-block uppercase tracking-wider mb-1.5 shadow-2xs">
                            <?= $badgeText ?>
                        </span>

                        <!-- Product Image -->
                        <a href="<?= SITE_URL ?>/product-details.php?id=<?= $p['id'] ?>" class="block h-28 sm:h-32 p-1 relative flex items-center justify-center overflow-hidden">
                            <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="w-full h-full object-contain group-hover:scale-105 transition duration-300">
                        </a>

                        <!-- Product Title -->
                        <a href="<?= SITE_URL ?>/product-details.php?id=<?= $p['id'] ?>" class="text-[11px] sm:text-xs font-semibold text-gray-800 line-clamp-1 mt-1 group-hover:text-fk-blue transition">
                            <?= htmlspecialchars($p['name']) ?>
                        </a>
                    </div>

                    <!-- Price / Subtext -->
                    <div class="mt-2 pt-1.5 border-t border-gray-100 flex items-center justify-between">
                        <div>
                            <span class="text-xs sm:text-sm font-black text-gray-950"><?= formatPrice($p['selling_price']) ?></span>
                            <div class="text-[9px] text-emerald-600 font-bold"><?= $p['discount_percent'] > 0 ? $p['discount_percent'] . '% OFF' : 'Deals for you' ?></div>
                        </div>
                        <button onclick="addToCart(<?= $p['id'] ?>, 1, this)" class="w-6 h-6 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white flex items-center justify-center text-xs font-bold transition active:scale-90" title="Quick Add">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 2. Features Badges Row -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <div class="flex items-center gap-3 p-2">
            <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-truck-fast text-lg"></i>
            </div>
            <div>
                <div class="text-xs font-bold text-gray-900">2-Hour Express</div>
                <div class="text-[11px] text-gray-500">Temperature Controlled</div>
            </div>
        </div>
        <div class="flex items-center gap-3 p-2">
            <div class="w-10 h-10 rounded-full bg-blue-100 text-fk-blue flex items-center justify-center shrink-0">
                <i class="fa-solid fa-shield-halved text-lg"></i>
            </div>
            <div>
                <div class="text-xs font-bold text-gray-900">Zero Bruise Guarantee</div>
                <div class="text-[11px] text-gray-500">100% Replacement</div>
            </div>
        </div>
        <div class="flex items-center gap-3 p-2">
            <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-tag text-lg"></i>
            </div>
            <div>
                <div class="text-xs font-bold text-gray-900">Best Price In Town</div>
                <div class="text-[11px] text-gray-500">Direct From Orchards</div>
            </div>
        </div>
        <div class="flex items-center gap-3 p-2">
            <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-heart-pulse text-lg"></i>
            </div>
            <div>
                <div class="text-xs font-bold text-gray-900">Naturally Ripened</div>
                <div class="text-[11px] text-gray-500">Carbide & Chemical Free</div>
            </div>
        </div>
    </div>

    <!-- 3. Category Carousel / Grid -->
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg md:text-xl font-bold text-gray-900">Shop By Fruit Categories</h2>
                <p class="text-xs text-gray-500">Carefully curated fresh picks for your daily vitamins</p>
            </div>
            <a href="<?= SITE_URL ?>/products.php" class="text-xs font-bold text-fk-blue hover:underline flex items-center gap-1">
                <span>View All</span>
                <i class="fa-solid fa-chevron-right text-[10px]"></i>
            </a>
        </div>

        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2.5 sm:gap-4">
            <?php foreach ($navCategories as $cat): ?>
                <a href="<?= SITE_URL ?>/products.php?category=<?= urlencode($cat['slug']) ?>" class="group flex flex-col items-center p-2 sm:p-3 rounded-2xl border border-gray-100 hover:border-blue-200 hover:shadow-md transition text-center bg-gray-50/60 hover:bg-white">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full overflow-hidden mb-2 border-2 border-white shadow-sm group-hover:scale-105 transition duration-300 bg-white p-1">
                        <img src="<?= htmlspecialchars($cat['image_url']) ?>" alt="<?= htmlspecialchars($cat['name']) ?>" class="w-full h-full object-cover rounded-full">
                    </div>
                    <span class="text-[11px] sm:text-xs font-bold text-gray-800 group-hover:text-fk-blue transition line-clamp-1"><?= htmlspecialchars($cat['name']) ?></span>
                    <span class="text-[9px] sm:text-[10px] text-emerald-600 font-semibold mt-0.5">Explore &rarr;</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 4. Flipkart-Style "Deals of the Day" with Countdown Timer -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <!-- Deal Header -->
        <div class="bg-gradient-to-r from-rose-500 to-orange-500 px-4 sm:px-6 py-3.5 text-white flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <i class="fa-solid fa-bolt-lightning text-yellow-300 text-xl sm:text-2xl animate-pulse"></i>
                <div>
                    <h3 class="text-base sm:text-lg font-black uppercase tracking-wide">Deals Of The Day</h3>
                    <p class="text-[11px] sm:text-xs text-rose-100">Top handpicked fruits with highest discounts</p>
                </div>
            </div>

            <!-- Countdown Timer Simulation -->
            <div class="flex items-center gap-1.5 text-xs font-semibold bg-black/20 px-2.5 py-1 rounded-lg border border-white/20 backdrop-blur-sm">
                <span class="text-[11px]">Ends in:</span>
                <span class="bg-white text-gray-900 px-1.5 py-0.5 rounded font-mono font-bold text-xs" id="deal-hours">04</span> :
                <span class="bg-white text-gray-900 px-1.5 py-0.5 rounded font-mono font-bold text-xs" id="deal-minutes">28</span> :
                <span class="bg-white text-gray-900 px-1.5 py-0.5 rounded font-mono font-bold text-xs" id="deal-seconds">45</span>
            </div>
        </div>

        <!-- Flipkart Deal Product Grid (2-cols on mobile!) -->
        <div class="p-3 sm:p-5 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2.5 sm:gap-4">
            <?php foreach ($deals as $p): ?>
                <div class="product-card bg-white rounded-xl sm:rounded-2xl border border-gray-100 hover:border-blue-200 shadow-2xs hover:shadow-md transition-all flex flex-col justify-between relative group overflow-hidden">
                    
                    <!-- Wishlist button -->
                    <button 
                        onclick="toggleWishlist(<?= $p['id'] ?>, this)" 
                        title="Add to Wishlist"
                        class="absolute top-2 right-2 z-10 w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white/95 hover:bg-white shadow-xs border border-gray-100 flex items-center justify-center transition active:scale-90"
                    >
                        <i class="fa-heart <?= isWishlisted($p['id']) ? 'fa-solid text-rose-500' : 'fa-regular text-gray-400' ?> text-xs sm:text-sm"></i>
                    </button>

                    <!-- Discount Ribbon (Flipkart Style) -->
                    <?php if ($p['discount_percent'] > 0): ?>
                        <span class="absolute top-2 left-2 z-10 bg-rose-600 text-white text-[9px] sm:text-[10px] font-black px-1.5 py-0.5 rounded shadow-xs uppercase tracking-wider">
                            <?= $p['discount_percent'] ?>% OFF
                        </span>
                    <?php endif; ?>

                    <!-- Product Image Container -->
                    <a href="<?= SITE_URL ?>/product-details.php?id=<?= $p['id'] ?>" class="block overflow-hidden h-36 sm:h-44 bg-white p-2.5 relative flex items-center justify-center">
                        <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="w-full h-full object-contain group-hover:scale-105 transition duration-300">
                    </a>

                    <!-- Flipkart Assured Small Tag -->
                    <div class="px-2.5 sm:px-3 pt-1 flex items-center gap-1 text-[9px] sm:text-[10px] font-bold text-fk-blue">
                        <span class="text-fk-yellow font-black italic">Plus</span>
                        <span class="text-gray-400 font-normal">&bull; 100% Fresh</span>
                    </div>

                    <!-- Product Meta -->
                    <div class="p-2.5 sm:p-3 flex-1 flex flex-col justify-between">
                        <div>
                            <a href="<?= SITE_URL ?>/product-details.php?id=<?= $p['id'] ?>" class="text-xs sm:text-sm font-semibold text-gray-800 hover:text-fk-blue line-clamp-2 leading-snug mb-1">
                                <?= htmlspecialchars($p['name']) ?>
                            </a>
                            <div class="text-[10px] sm:text-[11px] text-gray-400 font-medium mb-1.5"><?= htmlspecialchars($p['unit']) ?></div>
                            
                            <!-- Flipkart Green Rating Pill -->
                            <div class="flex items-center gap-1 mb-2">
                                <span class="bg-emerald-700 text-white text-[9px] sm:text-[10px] font-black px-1.5 py-0.5 rounded flex items-center gap-0.5 w-fit">
                                    <span><?= $p['rating'] ?></span>
                                    <i class="fa-solid fa-star text-[7px] sm:text-[8px]"></i>
                                </span>
                                <span class="text-[9px] sm:text-[10px] text-gray-400 font-medium">(<?= $p['rating_count'] ?>)</span>
                            </div>
                        </div>

                        <div>
                            <!-- Price Row -->
                            <div class="flex items-baseline flex-wrap gap-1 mb-2">
                                <span class="text-sm sm:text-base font-black text-gray-950"><?= formatPrice($p['selling_price']) ?></span>
                                <?php if ($p['mrp_price'] > $p['selling_price']): ?>
                                    <span class="text-[10px] sm:text-xs text-gray-400 line-through"><?= formatPrice($p['mrp_price']) ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Flipkart Grocery Style "+ ADD" Button -->
                            <button 
                                onclick="addToCart(<?= $p['id'] ?>, 1, this)"
                                class="w-full bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-700 border border-emerald-600 font-black text-xs py-1.5 sm:py-2 rounded-xl transition flex items-center justify-center gap-1 cursor-pointer active:scale-95 shadow-2xs"
                            >
                                <i class="fa-solid fa-plus text-[10px]"></i>
                                <span>ADD</span>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 5. Fresh Farm Arrivals (Flipkart 2-Col Grid on Mobile) -->
    <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4 sm:mb-6 border-b border-gray-100 pb-3">
            <div>
                <h3 class="text-base sm:text-xl font-bold text-gray-900">Fresh Farm Arrivals &amp; Favorites</h3>
                <p class="text-xs text-gray-500">Certified organic and orchard fresh fruits</p>
            </div>
            <a href="<?= SITE_URL ?>/products.php" class="bg-fk-blue hover:bg-fk-blue-dark text-white text-xs font-bold px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg shadow transition">
                VIEW ALL
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2.5 sm:gap-4">
            <?php foreach ($featured as $p): ?>
                <div class="product-card bg-white rounded-xl sm:rounded-2xl border border-gray-100 hover:border-blue-200 shadow-2xs hover:shadow-md transition-all flex flex-col justify-between relative group overflow-hidden">
                    
                    <!-- Wishlist button -->
                    <button 
                        onclick="toggleWishlist(<?= $p['id'] ?>, this)" 
                        title="Add to Wishlist"
                        class="absolute top-2 right-2 z-10 w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white/95 hover:bg-white shadow-xs border border-gray-100 flex items-center justify-center transition active:scale-90"
                    >
                        <i class="fa-heart <?= isWishlisted($p['id']) ? 'fa-solid text-rose-500' : 'fa-regular text-gray-400' ?> text-xs sm:text-sm"></i>
                    </button>

                    <!-- Discount Tag -->
                    <?php if ($p['discount_percent'] > 0): ?>
                        <span class="absolute top-2 left-2 z-10 bg-emerald-600 text-white text-[9px] sm:text-[10px] font-black px-1.5 py-0.5 rounded shadow-xs uppercase tracking-wider">
                            <?= $p['discount_percent'] ?>% OFF
                        </span>
                    <?php endif; ?>

                    <!-- Product Image Container -->
                    <a href="<?= SITE_URL ?>/product-details.php?id=<?= $p['id'] ?>" class="block overflow-hidden h-36 sm:h-44 md:h-48 bg-white p-2.5 relative flex items-center justify-center">
                        <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="w-full h-full object-contain group-hover:scale-105 transition duration-300">
                    </a>

                    <!-- Assured Badge -->
                    <div class="px-2.5 sm:px-3 pt-1 flex items-center gap-1 text-[9px] sm:text-[10px] font-bold text-fk-blue">
                        <span class="text-fk-yellow font-black italic">Plus</span>
                        <span class="text-gray-400 font-normal">&bull; 100% Farm Fresh</span>
                    </div>

                    <!-- Product Details -->
                    <div class="p-2.5 sm:p-3 flex-1 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between text-[10px] sm:text-xs text-gray-400 mb-0.5">
                                <span><?= htmlspecialchars($p['category_name']) ?></span>
                                <span class="font-medium text-emerald-600"><?= htmlspecialchars($p['unit']) ?></span>
                            </div>

                            <a href="<?= SITE_URL ?>/product-details.php?id=<?= $p['id'] ?>" class="text-xs sm:text-sm font-semibold text-gray-800 hover:text-fk-blue line-clamp-2 leading-snug mb-1">
                                <?= htmlspecialchars($p['name']) ?>
                            </a>

                            <!-- Flipkart Rating badge -->
                            <div class="flex items-center gap-1.5 mb-2">
                                <span class="bg-emerald-700 text-white text-[9px] sm:text-[10px] font-black px-1.5 py-0.5 rounded flex items-center gap-0.5">
                                    <span><?= $p['rating'] ?></span>
                                    <i class="fa-solid fa-star text-[7px] sm:text-[8px]"></i>
                                </span>
                                <span class="text-[9px] sm:text-[10px] text-gray-400">(<?= $p['rating_count'] ?>)</span>
                            </div>
                        </div>

                        <div>
                            <!-- Price & MRP -->
                            <div class="flex items-baseline flex-wrap gap-1 sm:gap-1.5 mb-2">
                                <span class="text-sm sm:text-base font-black text-gray-950"><?= formatPrice($p['selling_price']) ?></span>
                                <?php if ($p['mrp_price'] > $p['selling_price']): ?>
                                    <span class="text-[10px] sm:text-xs text-gray-400 line-through"><?= formatPrice($p['mrp_price']) ?></span>
                                    <span class="text-[9px] sm:text-[10px] text-emerald-600 font-extrabold"><?= $p['discount_percent'] ?>% off</span>
                                <?php endif; ?>
                            </div>

                            <!-- Buttons (Flipkart + ADD and Buy Now) -->
                            <div class="grid grid-cols-2 gap-1.5">
                                <button 
                                    onclick="addToCart(<?= $p['id'] ?>, 1, this)"
                                    class="bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-700 border border-emerald-600 font-black text-xs py-1.5 sm:py-2 rounded-xl transition flex items-center justify-center gap-1 cursor-pointer active:scale-95"
                                >
                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                    <span>ADD</span>
                                </button>
                                <a 
                                    href="<?= SITE_URL ?>/product-details.php?id=<?= $p['id'] ?>"
                                    class="bg-fk-yellow hover:bg-yellow-400 text-gray-900 font-black text-xs py-1.5 sm:py-2 rounded-xl transition flex items-center justify-center text-center shadow-2xs active:scale-95"
                                >
                                    <span>Buy</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 6. Exotic Fruits Promo Banner Strip -->
    <div class="rounded-xl overflow-hidden shadow bg-gradient-to-r from-purple-900 via-indigo-950 to-blue-950 text-white p-6 md:p-8 flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="space-y-2 max-w-xl">
            <span class="bg-purple-500/20 text-purple-300 border border-purple-400/30 px-3 py-1 rounded-full text-xs font-semibold uppercase">
                ✈️ Globally Sourced Fresh
            </span>
            <h3 class="text-2xl font-black">Indulge in Exotic Superfoods</h3>
            <p class="text-sm text-gray-300">
                Fresh Blueberries from Chile, Hass Avocados, New Zealand Zespri Kiwis and Pink Dragon Fruits delivered crisp and cold.
            </p>
        </div>
        <a href="<?= SITE_URL ?>/products.php?category=exotic-imported" class="shrink-0 bg-white text-purple-950 hover:bg-purple-100 font-extrabold px-6 py-3 rounded-lg shadow-lg transition">
            Explore Exotic Fruits &rarr;
        </a>
    </div>

</div>

<!-- Countdown Timer JavaScript for Deal of the Day -->
<script>
    let hours = 4, minutes = 28, seconds = 45;
    setInterval(() => {
        if (seconds > 0) {
            seconds--;
        } else {
            seconds = 59;
            if (minutes > 0) {
                minutes--;
            } else {
                minutes = 59;
                if (hours > 0) hours--;
            }
        }
        document.getElementById('deal-hours').innerText = String(hours).padStart(2, '0');
        document.getElementById('deal-minutes').innerText = String(minutes).padStart(2, '0');
        document.getElementById('deal-seconds').innerText = String(seconds).padStart(2, '0');
    }, 1000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
