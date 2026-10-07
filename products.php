<?php
$pageTitle = "Fresh Fruits Catalog & Search";
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Get filters from query parameters
$search = trim($_GET['q'] ?? '');
$categorySlug = trim($_GET['category'] ?? '');
$minPrice = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : 2000;
$minRating = isset($_GET['rating']) && is_numeric($_GET['rating']) ? (float)$_GET['rating'] : 0;
$minDiscount = isset($_GET['discount']) && is_numeric($_GET['discount']) ? (int)$_GET['discount'] : 0;
$dealOnly = isset($_GET['deal']) ? 1 : 0;
$sort = trim($_GET['sort'] ?? 'featured');

// Base query construction
$query = "
    SELECT p.*, c.name as category_name, c.slug as category_slug 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active'
";
$params = [];

if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.short_desc LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($categorySlug)) {
    $query .= " AND c.slug = ?";
    $params[] = $categorySlug;
}

if ($minPrice > 0) {
    $query .= " AND p.selling_price >= ?";
    $params[] = $minPrice;
}

if ($maxPrice < 2000) {
    $query .= " AND p.selling_price <= ?";
    $params[] = $maxPrice;
}

if ($minRating > 0) {
    $query .= " AND p.rating >= ?";
    $params[] = $minRating;
}

if ($minDiscount > 0) {
    $query .= " AND p.discount_percent >= ?";
    $params[] = $minDiscount;
}

if ($dealOnly) {
    $query .= " AND p.is_deal = 1";
}

// Sorting
switch ($sort) {
    case 'price_asc':
        $query .= " ORDER BY p.selling_price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY p.selling_price DESC";
        break;
    case 'rating':
        $query .= " ORDER BY p.rating DESC";
        break;
    case 'newest':
        $query .= " ORDER BY p.id DESC";
        break;
    default:
        $query .= " ORDER BY p.is_featured DESC, p.id DESC";
        break;
}

$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// All categories with product counts for sidebar
$categoriesWithCount = $db->query("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    WHERE c.is_active = 1
    GROUP BY c.id 
    ORDER BY c.name ASC
")->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <!-- Breadcrumb -->
    <nav class="flex text-xs text-gray-500 mb-4 items-center gap-1.5">
        <a href="<?= SITE_URL ?>/index.php" class="hover:text-fk-blue">Home</a>
        <i class="fa-solid fa-chevron-right text-[9px] text-gray-400"></i>
        <a href="<?= SITE_URL ?>/products.php" class="hover:text-fk-blue">Fruits</a>
        <?php if (!empty($categorySlug)): ?>
            <i class="fa-solid fa-chevron-right text-[9px] text-gray-400"></i>
            <span class="text-gray-900 font-semibold"><?= htmlspecialchars(ucwords(str_replace('-', ' ', $categorySlug))) ?></span>
        <?php endif; ?>
        <?php if (!empty($search)): ?>
            <i class="fa-solid fa-chevron-right text-[9px] text-gray-400"></i>
            <span class="text-gray-900 font-semibold">"<?= htmlspecialchars($search) ?>"</span>
        <?php endif; ?>
    </nav>

    <!-- Horizontal Category Chips (Flipkart App Quick Bar) -->
    <div class="mb-5 overflow-x-auto pb-1 -mx-4 px-4 sm:mx-0 sm:px-0">
        <div class="flex items-center gap-2 min-w-max">
            <a href="<?= SITE_URL ?>/products.php<?= !empty($search) ? '?q='.urlencode($search) : '' ?>" 
               class="px-3.5 py-1.5 rounded-full text-xs font-bold transition flex items-center gap-1.5 <?= empty($categorySlug) ? 'bg-fk-blue text-white shadow-sm ring-2 ring-blue-300' : 'bg-white text-gray-700 border border-gray-200 hover:border-blue-400 hover:text-fk-blue' ?>">
                <i class="fa-solid fa-border-all text-[10px]"></i>
                <span>All Fruits</span>
            </a>
            <?php foreach ($categoriesWithCount as $cat): ?>
                <a href="<?= SITE_URL ?>/products.php?category=<?= urlencode($cat['slug']) ?><?= !empty($search) ? '&q='.urlencode($search) : '' ?>" 
                   class="px-3.5 py-1.5 rounded-full text-xs font-bold transition flex items-center gap-1.5 <?= $categorySlug === $cat['slug'] ? 'bg-fk-blue text-white shadow-sm ring-2 ring-blue-300' : 'bg-white text-gray-700 border border-gray-200 hover:border-blue-400 hover:text-fk-blue' ?>">
                    <span><?= htmlspecialchars($cat['name']) ?></span>
                    <span class="text-[10px] font-mono opacity-80">(<?= $cat['product_count'] ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-6">

        <!-- Left Sidebar Filters (Desktop Only) -->
        <aside class="hidden lg:block lg:w-64 bg-white p-5 rounded-2xl shadow-sm border border-gray-100 self-start shrink-0">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-4">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-fk-blue"></i> Filters
                </h3>
                <a href="<?= SITE_URL ?>/products.php" class="text-xs text-fk-blue font-bold hover:underline">CLEAR ALL</a>
            </div>

            <form action="<?= SITE_URL ?>/products.php" method="GET" id="desktop-filter-form" class="space-y-6">
                <?php if (!empty($search)): ?>
                    <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                <?php endif; ?>

                <!-- Category Filter -->
                <div>
                    <h4 class="text-xs font-bold text-gray-700 uppercase mb-2">Categories</h4>
                    <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                        <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer hover:text-fk-blue">
                            <input type="radio" name="category" value="" <?= empty($categorySlug) ? 'checked' : '' ?> onchange="this.form.submit()" class="text-fk-blue focus:ring-blue-500">
                            <span>All Categories</span>
                        </label>
                        <?php foreach ($categoriesWithCount as $cat): ?>
                            <label class="flex items-center justify-between text-xs text-gray-700 cursor-pointer hover:text-fk-blue">
                                <span class="flex items-center gap-2">
                                    <input type="radio" name="category" value="<?= htmlspecialchars($cat['slug']) ?>" <?= $categorySlug === $cat['slug'] ? 'checked' : '' ?> onchange="this.form.submit()" class="text-fk-blue focus:ring-blue-500">
                                    <span><?= htmlspecialchars($cat['name']) ?></span>
                                </span>
                                <span class="text-gray-400 font-mono">(<?= $cat['product_count'] ?>)</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <hr class="border-gray-100">

                <!-- Price Range Filter -->
                <div>
                    <h4 class="text-xs font-bold text-gray-700 uppercase mb-2">Price Range (₹)</h4>
                    <div class="flex items-center gap-2 mb-2">
                        <input type="number" name="min_price" value="<?= $minPrice ?>" placeholder="Min" class="w-1/2 p-1.5 text-xs border rounded-lg text-center">
                        <span class="text-gray-400">to</span>
                        <input type="number" name="max_price" value="<?= $maxPrice ?>" placeholder="Max" class="w-1/2 p-1.5 text-xs border rounded-lg text-center">
                    </div>
                    <button type="submit" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 text-xs font-bold py-1.5 rounded-lg transition">
                        Apply Price
                    </button>
                </div>

                <hr class="border-gray-100">

                <!-- Rating Filter -->
                <div>
                    <h4 class="text-xs font-bold text-gray-700 uppercase mb-2">Customer Rating</h4>
                    <div class="space-y-1.5">
                        <?php foreach ([4 => '4★ & above', 3 => '3★ & above'] as $val => $label): ?>
                            <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer hover:text-fk-blue">
                                <input type="radio" name="rating" value="<?= $val ?>" <?= $minRating == $val ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span class="flex items-center gap-1 font-medium">
                                    <span><?= $label ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <hr class="border-gray-100">

                <!-- Discount Filter -->
                <div>
                    <h4 class="text-xs font-bold text-gray-700 uppercase mb-2">Discount</h4>
                    <div class="space-y-1.5">
                        <?php foreach ([10 => '10% or more', 20 => '20% or more', 30 => '30% or more'] as $dVal => $dLabel): ?>
                            <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer hover:text-fk-blue">
                                <input type="radio" name="discount" value="<?= $dVal ?>" <?= $minDiscount == $dVal ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span><?= $dLabel ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </aside>

        <!-- Right Products Grid Container -->
        <div class="flex-1">

            <!-- Top Sort Bar & Mobile Filter Trigger -->
            <div class="bg-white p-3 sm:p-4 rounded-xl sm:rounded-2xl shadow-sm border border-gray-100 mb-4 sm:mb-6 flex flex-wrap items-center justify-between gap-3">
                <div class="text-xs text-gray-600 flex items-center gap-2">
                    <span>Showing <strong class="text-gray-900"><?= count($products) ?></strong> fruits</span>
                    <?php if (!empty($search)): ?>
                        <span class="text-gray-400">for "<strong class="text-fk-blue"><?= htmlspecialchars($search) ?></strong>"</span>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Mobile Filter Button -->
                    <button 
                        type="button" 
                        onclick="openMobileFilterModal()" 
                        class="lg:hidden bg-gray-50 hover:bg-gray-100 text-gray-800 text-xs font-bold px-3 py-1.5 rounded-lg border border-gray-200 flex items-center gap-1.5 transition active:scale-95"
                    >
                        <i class="fa-solid fa-sliders text-fk-blue"></i>
                        <span>Filters</span>
                        <?php if ($minPrice > 0 || $maxPrice < 2000 || $minRating > 0 || $minDiscount > 0): ?>
                            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                        <?php endif; ?>
                    </button>

                    <!-- Sorting options -->
                    <div class="hidden sm:flex items-center gap-1 text-xs">
                        <span class="text-gray-400 mr-1 font-medium">Sort:</span>
                        <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'featured'])) ?>" class="px-2.5 py-1 rounded-md <?= $sort === 'featured' ? 'bg-fk-blue text-white font-bold' : 'text-gray-600 hover:bg-gray-100' ?>">Featured</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'price_asc'])) ?>" class="px-2.5 py-1 rounded-md <?= $sort === 'price_asc' ? 'bg-fk-blue text-white font-bold' : 'text-gray-600 hover:bg-gray-100' ?>">Price: Low</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'price_desc'])) ?>" class="px-2.5 py-1 rounded-md <?= $sort === 'price_desc' ? 'bg-fk-blue text-white font-bold' : 'text-gray-600 hover:bg-gray-100' ?>">Price: High</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'rating'])) ?>" class="px-2.5 py-1 rounded-md <?= $sort === 'rating' ? 'bg-fk-blue text-white font-bold' : 'text-gray-600 hover:bg-gray-100' ?>">Rating</a>
                    </div>

                    <!-- Mobile Sort Dropdown -->
                    <div class="sm:hidden">
                        <select onchange="window.location.href=this.value" class="text-xs bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-gray-700 font-semibold focus:outline-none focus:ring-1 focus:ring-fk-blue">
                            <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'featured'])) ?>" <?= $sort === 'featured' ? 'selected' : '' ?>>Featured</option>
                            <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'price_asc'])) ?>" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                            <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'price_desc'])) ?>" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                            <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'rating'])) ?>" <?= $sort === 'rating' ? 'selected' : '' ?>>Customer Rating</option>
                            <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'newest'])) ?>" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest Arrivals</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Products Grid (Flipkart-Style 2-Columns on Mobile!) -->
            <?php if (count($products) > 0): ?>
                <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-2.5 sm:gap-4 lg:gap-5">
                    <?php foreach ($products as $p): ?>
                        <div class="product-card bg-white rounded-xl sm:rounded-2xl border border-gray-100 hover:border-blue-200 shadow-2xs hover:shadow-md transition-all flex flex-col justify-between relative group overflow-hidden">
                            
                            <!-- Wishlist button -->
                            <button 
                                onclick="toggleWishlist(<?= $p['id'] ?>, this)" 
                                title="Add to Wishlist"
                                class="absolute top-2 right-2 z-10 w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-white/95 hover:bg-white shadow-xs border border-gray-100 flex items-center justify-center transition active:scale-90"
                            >
                                <i class="fa-heart <?= isWishlisted($p['id']) ? 'fa-solid text-rose-500' : 'fa-regular text-gray-400' ?> text-xs sm:text-sm"></i>
                            </button>

                            <!-- Discount Tag (Flipkart Style) -->
                            <?php if ($p['discount_percent'] > 0): ?>
                                <span class="absolute top-2 left-2 z-10 bg-rose-600 text-white text-[9px] sm:text-[10px] font-black px-1.5 py-0.5 rounded shadow-xs uppercase tracking-wider">
                                    <?= $p['discount_percent'] ?>% OFF
                                </span>
                            <?php endif; ?>

                            <!-- Product Image Container -->
                            <a href="<?= SITE_URL ?>/product-details.php?id=<?= $p['id'] ?>" class="block overflow-hidden h-36 sm:h-44 md:h-48 bg-white p-2.5 relative flex items-center justify-center">
                                <img src="<?= htmlspecialchars($p['image_url']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1615485290382-441e4d049cb5?w=600&q=80';" alt="<?= htmlspecialchars($p['name']) ?>" class="w-full h-full object-contain group-hover:scale-105 transition duration-300">
                            </a>

                            <!-- Flipkart Assured Small Tag -->
                            <div class="px-2.5 sm:px-3 pt-1 flex items-center gap-1 text-[9px] sm:text-[10px] font-bold text-fk-blue">
                                <span class="text-fk-yellow font-black italic">Plus</span>
                                <span class="text-gray-400 font-normal">&bull; 100% Farm Fresh</span>
                            </div>

                            <!-- Product Details -->
                            <div class="p-2.5 sm:p-3 flex-1 flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between text-[10px] sm:text-xs text-gray-400 mb-0.5">
                                        <span class="truncate max-w-[65%]"><?= htmlspecialchars($p['category_name']) ?></span>
                                        <span class="font-medium text-emerald-600 shrink-0"><?= htmlspecialchars($p['unit']) ?></span>
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
                                            <span>BUY</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-2xl p-12 text-center border border-gray-100 shadow-sm">
                    <div class="w-20 h-20 mx-auto rounded-full bg-orange-100 text-orange-500 flex items-center justify-center text-3xl mb-4">
                        <i class="fa-solid fa-apple-whole"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-1">No fruits found matching your criteria</h3>
                    <p class="text-xs text-gray-500 max-w-md mx-auto mb-6">Try clearing your filters or search for something else like "Apple", "Mango", or "Kiwi".</p>
                    <a href="<?= SITE_URL ?>/products.php" class="bg-fk-blue text-white text-xs font-bold px-6 py-2.5 rounded-xl shadow hover:bg-fk-blue-dark transition inline-block">
                        RESET ALL FILTERS
                    </a>
                </div>
            <?php endif; ?>

        </div>

    </div>

</div>

<!-- Mobile Filter Modal / Drawer -->
<div id="mobile-filter-modal" class="fixed inset-0 z-50 bg-black/60 hidden items-end justify-center backdrop-blur-xs transition-opacity duration-300">
    <div class="bg-white w-full max-w-lg rounded-t-3xl max-h-[85vh] flex flex-col shadow-2xl overflow-hidden">
        
        <!-- Header -->
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-black text-base text-gray-900 flex items-center gap-2">
                <i class="fa-solid fa-sliders text-fk-blue"></i>
                <span>Filter Fruits</span>
            </h3>
            <div class="flex items-center gap-3">
                <a href="<?= SITE_URL ?>/products.php" class="text-xs font-bold text-rose-600 hover:underline">Reset</a>
                <button type="button" onclick="closeMobileFilterModal()" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <!-- Scrollable Form Body -->
        <div class="p-4 overflow-y-auto space-y-5 flex-1">
            <form action="<?= SITE_URL ?>/products.php" method="GET" id="mobile-filter-form" class="space-y-5">
                <?php if (!empty($search)): ?>
                    <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                <?php endif; ?>
                <?php if (!empty($categorySlug)): ?>
                    <input type="hidden" name="category" value="<?= htmlspecialchars($categorySlug) ?>">
                <?php endif; ?>

                <!-- Price Range -->
                <div>
                    <h4 class="text-xs font-bold text-gray-700 uppercase mb-2">Price Range (₹)</h4>
                    <div class="flex items-center gap-2">
                        <input type="number" name="min_price" value="<?= $minPrice ?>" placeholder="Min" class="w-1/2 p-2 text-xs border rounded-xl text-center bg-gray-50">
                        <span class="text-gray-400 font-bold">to</span>
                        <input type="number" name="max_price" value="<?= $maxPrice ?>" placeholder="Max" class="w-1/2 p-2 text-xs border rounded-xl text-center bg-gray-50">
                    </div>
                </div>

                <!-- Rating Filter -->
                <div>
                    <h4 class="text-xs font-bold text-gray-700 uppercase mb-2">Customer Rating</h4>
                    <div class="grid grid-cols-2 gap-2">
                        <?php foreach ([4 => '4★ & above', 3 => '3★ & above'] as $val => $label): ?>
                            <label class="flex items-center gap-2 p-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-700 cursor-pointer has-checked:border-fk-blue has-checked:bg-blue-50">
                                <input type="radio" name="rating" value="<?= $val ?>" <?= $minRating == $val ? 'checked' : '' ?> class="text-fk-blue">
                                <span><?= $label ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Discount Filter -->
                <div>
                    <h4 class="text-xs font-bold text-gray-700 uppercase mb-2">Discount</h4>
                    <div class="grid grid-cols-3 gap-2">
                        <?php foreach ([10 => '10%+ Off', 20 => '20%+ Off', 30 => '30%+ Off'] as $dVal => $dLabel): ?>
                            <label class="flex items-center justify-center p-2 rounded-xl border border-gray-200 text-xs font-bold text-gray-700 cursor-pointer has-checked:border-fk-blue has-checked:bg-blue-50 text-center">
                                <input type="radio" name="discount" value="<?= $dVal ?>" <?= $minDiscount == $dVal ? 'checked' : '' ?> class="sr-only">
                                <span><?= $dLabel ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Footer submit inside modal -->
                <div class="pt-2">
                    <button type="submit" class="w-full bg-fk-blue hover:bg-fk-blue-dark text-white font-extrabold text-sm py-3 rounded-xl shadow transition">
                        APPLY FILTERS
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openMobileFilterModal() {
        const modal = document.getElementById('mobile-filter-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    function closeMobileFilterModal() {
        const modal = document.getElementById('mobile-filter-modal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    // Close on backdrop click
    document.getElementById('mobile-filter-modal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeMobileFilterModal();
        }
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
