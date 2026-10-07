<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$productId = (int)($_GET['id'] ?? 0);
$db = getDB();

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isLoggedIn()) {
        setFlash('warning', 'Please login to submit a review.');
        header("Location: " . SITE_URL . "/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }

    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $headline = clean($_POST['headline'] ?? '');
    $comment = clean($_POST['comment'] ?? '');

    if (!empty($headline) && !empty($comment)) {
        $stmt = $db->prepare("INSERT INTO reviews (product_id, user_id, rating, headline, comment) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$productId, $_SESSION['user_id'], $rating, $headline, $comment]);

        // Update product average rating
        $avgStmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM reviews WHERE product_id = ?");
        $avgStmt->execute([$productId]);
        $stats = $avgStmt->fetch();

        $updateProd = $db->prepare("UPDATE products SET rating = ?, rating_count = ? WHERE id = ?");
        $updateProd->execute([round($stats['avg_rating'], 1), $stats['total'], $productId]);

        setFlash('success', 'Thank you! Your verified fruit review has been posted.');
        header("Location: " . SITE_URL . "/product-details.php?id=" . $productId);
        exit;
    } else {
        setFlash('error', 'Please fill in both headline and review details.');
    }
}

// Fetch Product Details
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id = ? AND p.status = 'active'
");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: " . SITE_URL . "/products.php");
    exit;
}

$pageTitle = $product['name'];
require_once __DIR__ . '/includes/header.php';

// Fetch Reviews
$revStmt = $db->prepare("
    SELECT r.*, u.name as user_name 
    FROM reviews r 
    LEFT JOIN users u ON r.user_id = u.id 
    WHERE r.product_id = ? 
    ORDER BY r.id DESC
");
$revStmt->execute([$productId]);
$reviews = $revStmt->fetchAll();

// Related Products
$relStmt = $db->prepare("
    SELECT * FROM products 
    WHERE category_id = ? AND id != ? AND status = 'active' 
    LIMIT 4
");
$relStmt->execute([$product['category_id'], $productId]);
$related = $relStmt->fetchAll();

// Ensure product_images table exists
$db->exec("
    CREATE TABLE IF NOT EXISTS product_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        image_url VARCHAR(255) NOT NULL,
        is_primary TINYINT(1) DEFAULT 0,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Fetch Gallery Images for Multi-Angle Photo Switcher
$gStmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY id ASC");
$gStmt->execute([$productId]);
$galleryImages = $gStmt->fetchAll();

// Auto-seed angles for Pomegranate #11 if empty for instant live demonstration
if ($productId === 11 && empty($galleryImages)) {
    $pomeAngles = [
        'https://images.unsplash.com/photo-1576181256399-834e3b3a49bf?w=600&q=80',
        'https://images.unsplash.com/photo-1582281298055-e25b84a30b0b?w=600&q=80'
    ];
    foreach ($pomeAngles as $ang) {
        $db->prepare("INSERT INTO product_images (product_id, image_url) VALUES (11, ?)")->execute([$ang]);
    }
    $gStmt->execute([11]);
    $galleryImages = $gStmt->fetchAll();
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <!-- Breadcrumbs -->
    <nav class="flex text-xs text-gray-500 mb-4 items-center gap-1.5">
        <a href="<?= SITE_URL ?>/index.php" class="hover:text-fk-blue">Home</a>
        <i class="fa-solid fa-chevron-right text-[9px] text-gray-400"></i>
        <a href="<?= SITE_URL ?>/products.php" class="hover:text-fk-blue">Fruits</a>
        <i class="fa-solid fa-chevron-right text-[9px] text-gray-400"></i>
        <a href="<?= SITE_URL ?>/products.php?category=<?= urlencode($product['category_slug']) ?>" class="hover:text-fk-blue"><?= htmlspecialchars($product['category_name']) ?></a>
        <i class="fa-solid fa-chevron-right text-[9px] text-gray-400"></i>
        <span class="text-gray-900 font-semibold truncate max-w-xs"><?= htmlspecialchars($product['name']) ?></span>
    </nav>

    <!-- Flipkart Product View Split Box -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-8">

            <!-- Left Image & Buy Buttons Column (5 cols) -->
            <div class="md:col-span-5 flex flex-col items-center">
                <!-- Main Image Preview with Wishlist Floating Button -->
                <div class="relative w-full aspect-square max-h-[420px] rounded-xl overflow-hidden border border-gray-200 bg-gray-50 flex items-center justify-center p-4">
                    <img id="main-product-image" src="<?= htmlspecialchars($product['image_url']) ?>" onerror="this.onerror=null; this.src='<?= SITE_URL ?>/uploads/fruits/pomegranate.jpg';" alt="<?= htmlspecialchars($product['name']) ?>" class="w-full h-full object-contain hover:scale-105 transition duration-300">
                    
                    <button 
                        onclick="toggleWishlist(<?= $product['id'] ?>, this)" 
                        title="Add to Wishlist"
                        class="absolute top-4 right-4 z-10 w-10 h-10 rounded-full bg-white shadow-md flex items-center justify-center hover:scale-110 transition"
                    >
                        <i class="fa-heart <?= isWishlisted($product['id']) ? 'fa-solid text-rose-500' : 'fa-regular text-gray-400' ?> text-lg"></i>
                    </button>

                    <?php if ($product['discount_percent'] > 0): ?>
                        <span class="absolute top-4 left-4 bg-emerald-600 text-white text-xs font-black px-2.5 py-1 rounded shadow">
                            <?= $product['discount_percent'] ?>% OFF
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Multi-Image Flipkart Thumbnail Strip (if multiple photos available) -->
                <?php if (!empty($galleryImages)): ?>
                    <div class="w-full mt-3">
                        <div class="text-[11px] font-bold text-gray-500 mb-1.5 flex items-center justify-between">
                            <span><i class="fa-solid fa-camera text-fk-blue mr-1"></i> Product Photos (<?= count($galleryImages) + 1 ?> Views):</span>
                            <span class="text-[10px] text-gray-400">Click photo to view</span>
                        </div>
                        <div class="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar justify-start">
                            <!-- Main Cover Photo Thumbnail -->
                            <button 
                                type="button"
                                onclick="switchProductImage('<?= htmlspecialchars($product['image_url']) ?>', this)" 
                                class="product-thumb-btn w-14 h-14 rounded-lg overflow-hidden border-2 border-fk-blue p-0.5 bg-white transition hover:shadow-md shrink-0 focus:outline-none"
                                title="Front Cover View"
                            >
                                <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="Cover View" class="w-full h-full object-cover rounded">
                            </button>

                            <!-- Gallery Angle Thumbnails -->
                            <?php foreach ($galleryImages as $idx => $gImg): ?>
                                <button 
                                    type="button"
                                    onclick="switchProductImage('<?= htmlspecialchars($gImg['image_url']) ?>', this)" 
                                    class="product-thumb-btn w-14 h-14 rounded-lg overflow-hidden border-2 border-gray-200 hover:border-blue-400 p-0.5 bg-white transition hover:shadow-md shrink-0 focus:outline-none"
                                    title="View Angle <?= $idx + 2 ?>"
                                >
                                    <img src="<?= htmlspecialchars($gImg['image_url']) ?>" alt="View <?= $idx + 2 ?>" class="w-full h-full object-cover rounded">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Flipkart Action Buttons -->
                <div class="w-full grid grid-cols-2 gap-4 mt-6">
                    <button 
                        onclick="addToCart(<?= $product['id'] ?>, 1, this)"
                        class="bg-fk-yellow hover:bg-yellow-400 text-gray-900 font-extrabold py-3.5 px-4 rounded-lg shadow-md hover:shadow-lg transition flex items-center justify-center gap-2 text-sm uppercase tracking-wide"
                    >
                        <i class="fa-solid fa-cart-shopping text-base"></i>
                        <span>ADD TO CART</span>
                    </button>

                    <button 
                        onclick="buyNow(<?= $product['id'] ?>)"
                        class="bg-fk-orange hover:bg-orange-600 text-white font-extrabold py-3.5 px-4 rounded-lg shadow-md hover:shadow-lg transition flex items-center justify-center gap-2 text-sm uppercase tracking-wide"
                    >
                        <i class="fa-solid fa-bolt text-base"></i>
                        <span>BUY NOW</span>
                    </button>
                </div>
            </div>

            <!-- Right Details Column (7 cols) -->
            <div class="md:col-span-7 space-y-5">
                
                <!-- Category and Title -->
                <div>
                    <span class="text-xs font-semibold text-fk-blue uppercase tracking-wider bg-blue-50 px-2 py-0.5 rounded">
                        <?= htmlspecialchars($product['category_name']) ?>
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2 leading-tight">
                        <?= htmlspecialchars($product['name']) ?>
                    </h1>
                    <p class="text-xs font-medium text-emerald-700 mt-1 flex items-center gap-2">
                        <i class="fa-solid fa-weight-hanging"></i> Net Quantity: <strong><?= htmlspecialchars($product['unit']) ?></strong>
                    </p>
                </div>

                <!-- Rating Pill & Assured Badge -->
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-1.5 bg-emerald-700 text-white text-xs font-bold px-2 py-1 rounded shadow-sm">
                        <span><?= $product['rating'] ?></span>
                        <i class="fa-solid fa-star text-[10px]"></i>
                    </div>
                    <span class="text-xs text-gray-500 font-medium">
                        <?= $product['rating_count'] ?> Ratings &amp; <?= count($reviews) ?> Reviews
                    </span>
                    <!-- Flipkart Assured Badge -->
                    <span class="inline-flex items-center gap-1 bg-blue-50 text-fk-blue text-xs font-bold px-2 py-0.5 rounded border border-blue-200">
                        <i class="fa-solid fa-certificate text-fk-blue"></i> FruitKart Assured Fresh
                    </span>
                </div>

                <!-- Price Block -->
                <div class="space-y-1 bg-gray-50 p-4 rounded-xl border border-gray-100">
                    <div class="text-xs text-emerald-600 font-bold uppercase tracking-wider">Special Price</div>
                    <div class="flex items-baseline gap-3">
                        <span class="text-3xl font-black text-gray-900"><?= formatPrice($product['selling_price']) ?></span>
                        <?php if ($product['mrp_price'] > $product['selling_price']): ?>
                            <span class="text-base text-gray-400 line-through"><?= formatPrice($product['mrp_price']) ?></span>
                            <span class="text-sm font-bold text-emerald-700"><?= $product['discount_percent'] ?>% off</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-[11px] text-gray-500">Inclusive of all taxes &bull; ₹<?= round($product['selling_price'], 2) ?> / unit</div>
                </div>

                <!-- Flipkart Available Offers -->
                <div class="space-y-2">
                    <h3 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Available Offers</h3>
                    <ul class="text-xs space-y-1.5 text-gray-700">
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-tag text-emerald-600 mt-0.5"></i>
                            <span><strong>Coupon Discount:</strong> Use code <code class="bg-gray-100 px-1 py-0.5 text-fk-blue font-bold rounded">FRESH50</code> to get flat ₹50 OFF on orders above ₹299.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-tag text-emerald-600 mt-0.5"></i>
                            <span><strong>Free Delivery:</strong> Enjoy free express shipping on fruit orders of ₹499 and above.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fa-solid fa-tag text-emerald-600 mt-0.5"></i>
                            <span><strong>Bank Offer:</strong> Flat 5% Cashback on Axis Bank Credit Card on checkout.</span>
                        </li>
                    </ul>
                </div>

                <!-- Delivery Pincode Checker Simulator -->
                <div class="border-t border-b border-gray-100 py-3">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-bold text-gray-700 uppercase">Delivery</span>
                        <div class="relative flex items-center">
                            <input type="text" id="check-pincode" maxlength="6" value="122001" placeholder="Enter Pincode" class="text-xs p-2 border border-gray-300 rounded-l focus:outline-none focus:border-fk-blue w-32 font-mono">
                            <button onclick="checkPincode()" class="bg-fk-blue hover:bg-fk-blue-dark text-white text-xs font-bold px-3 py-2 rounded-r transition">
                                Check
                            </button>
                        </div>
                    </div>
                    <div id="pincode-result" class="text-xs text-emerald-700 font-medium mt-1.5 flex items-center gap-1.5">
                        <i class="fa-solid fa-truck-fast"></i>
                        <span>Delivery by <strong>Today evening (within 2 hrs)</strong> | Free on ₹499+</span>
                    </div>
                </div>

                <!-- Fruit Specifications & Nutrition Grid -->
                <div class="space-y-2">
                    <h3 class="text-xs font-bold text-gray-800 uppercase tracking-wider">Specifications & Nutrition</h3>
                    <div class="grid grid-cols-2 gap-2 text-xs bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <div class="text-gray-500">Origin Orchards:</div>
                        <div class="font-semibold text-gray-900"><?= htmlspecialchars($product['origin']) ?></div>
                        
                        <div class="text-gray-500">Energy &amp; Calories:</div>
                        <div class="font-semibold text-gray-900"><?= htmlspecialchars($product['calories']) ?></div>

                        <div class="text-gray-500">Shelf Life:</div>
                        <div class="font-semibold text-gray-900"><?= htmlspecialchars($product['shelf_life']) ?></div>

                        <div class="text-gray-500">Ripening Process:</div>
                        <div class="font-semibold text-emerald-700">100% Naturally Ripened</div>

                        <div class="text-gray-500">Stock Availability:</div>
                        <div class="font-semibold <?= $product['stock_qty'] > 10 ? 'text-emerald-600' : 'text-rose-600' ?>">
                            <?= $product['stock_qty'] > 10 ? 'In Stock (' . $product['stock_qty'] . ' available)' : 'Hurry! Only ' . $product['stock_qty'] . ' left' ?>
                        </div>
                    </div>
                </div>

                <!-- Product Description -->
                <div>
                    <h3 class="text-xs font-bold text-gray-800 uppercase tracking-wider mb-1.5">Product Description</h3>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        <?= nl2br(htmlspecialchars($product['description'])) ?>
                    </p>
                </div>

            </div>

        </div>
    </div>

    <!-- Ratings & Reviews Section -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-6">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Ratings &amp; Reviews</h3>
                <p class="text-xs text-gray-500">Verified buyer feedback on taste and freshness</p>
            </div>
            
            <button onclick="document.getElementById('write-review-box').classList.toggle('hidden')" class="bg-fk-blue text-white font-bold text-xs px-4 py-2 rounded shadow hover:bg-fk-blue-dark transition">
                Rate Product
            </button>
        </div>

        <!-- Write Review Form (Collapsible) -->
        <div id="write-review-box" class="hidden mb-8 bg-gray-50 p-4 rounded-xl border border-gray-200">
            <h4 class="text-sm font-bold text-gray-900 mb-3">Write Your Review</h4>
            <form action="<?= SITE_URL ?>/product-details.php?id=<?= $product['id'] ?>" method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Your Rating</label>
                    <select name="rating" class="text-xs p-2 border rounded bg-white w-40">
                        <option value="5">5 ★★★★★ - Excellent Taste</option>
                        <option value="4">4 ★★★★☆ - Very Fresh</option>
                        <option value="3">3 ★★★☆☆ - Good</option>
                        <option value="2">2 ★★☆☆☆ - Average</option>
                        <option value="1">1 ★☆☆☆☆ - Poor</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Review Headline</label>
                    <input type="text" name="headline" placeholder="e.g. Crisp, sweet and beautifully packed" required class="w-full text-xs p-2.5 border rounded focus:outline-none focus:border-fk-blue bg-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Detailed Feedback</label>
                    <textarea name="comment" rows="3" placeholder="Share your experience regarding freshness, sweetness, and delivery..." required class="w-full text-xs p-2.5 border rounded focus:outline-none focus:border-fk-blue bg-white"></textarea>
                </div>
                <button type="submit" name="submit_review" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-6 py-2 rounded shadow transition">
                    Submit Verified Review
                </button>
            </form>
        </div>

        <!-- Reviews List -->
        <?php if (count($reviews) > 0): ?>
            <div class="divide-y divide-gray-100 space-y-4">
                <?php foreach ($reviews as $rev): ?>
                    <div class="pt-4 first:pt-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="bg-emerald-700 text-white text-[10px] font-bold px-1.5 py-0.5 rounded flex items-center gap-0.5">
                                <span><?= $rev['rating'] ?></span>
                                <i class="fa-solid fa-star text-[8px]"></i>
                            </span>
                            <span class="text-xs font-bold text-gray-900"><?= htmlspecialchars($rev['headline']) ?></span>
                        </div>
                        <p class="text-xs text-gray-600 mb-2 leading-relaxed"><?= htmlspecialchars($rev['comment']) ?></p>
                        <div class="text-[11px] text-gray-400 flex items-center gap-3">
                            <span class="font-medium text-gray-700"><?= htmlspecialchars($rev['user_name'] ?? 'FruitKart Customer') ?></span>
                            <span class="text-emerald-600 font-semibold"><i class="fa-solid fa-circle-check"></i> Certified Buyer</span>
                            <span><?= date('d M Y', strtotime($rev['created_at'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8 text-gray-400 text-xs">
                <i class="fa-regular fa-comment-dots text-2xl mb-1 block"></i>
                No reviews yet for this fresh harvest. Be the first to share your thoughts!
            </div>
        <?php endif; ?>
    </div>

    <!-- Related Fruits (Flipkart Style Grid) -->
    <?php if (count($related) > 0): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-6 mt-6">
            <div class="flex items-center justify-between mb-4 border-b border-gray-100 pb-3">
                <div>
                    <h3 class="text-base sm:text-lg font-bold text-gray-900">Similar Fresh Fruits</h3>
                    <p class="text-xs text-gray-400">Customers who bought this also bought</p>
                </div>
                <a href="<?= SITE_URL ?>/products.php?category=<?= urlencode($product['category_slug'] ?? '') ?>" class="text-xs font-bold text-fk-blue hover:underline">
                    View More &rarr;
                </a>
            </div>
            
            <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-2.5 sm:gap-4">
                <?php foreach ($related as $r): ?>
                    <div class="product-card bg-white rounded-xl sm:rounded-2xl border border-gray-100 hover:border-blue-200 shadow-2xs hover:shadow-md transition-all flex flex-col justify-between relative group overflow-hidden">
                        
                        <!-- Discount Tag -->
                        <?php if ($r['discount_percent'] > 0): ?>
                            <span class="absolute top-2 left-2 z-10 bg-rose-600 text-white text-[9px] sm:text-[10px] font-black px-1.5 py-0.5 rounded shadow-xs uppercase tracking-wider">
                                <?= $r['discount_percent'] ?>% OFF
                            </span>
                        <?php endif; ?>

                        <!-- Product Image -->
                        <a href="<?= SITE_URL ?>/product-details.php?id=<?= $r['id'] ?>" class="block overflow-hidden h-36 sm:h-44 bg-white p-2.5 relative flex items-center justify-center">
                            <img src="<?= htmlspecialchars($r['image_url']) ?>" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1615485290382-441e4d049cb5?w=600&q=80';" alt="<?= htmlspecialchars($r['name']) ?>" class="w-full h-full object-contain group-hover:scale-105 transition duration-300">
                        </a>

                        <!-- Flipkart Plus Tag -->
                        <div class="px-2.5 sm:px-3 pt-1 flex items-center gap-1 text-[9px] sm:text-[10px] font-bold text-fk-blue">
                            <span class="text-fk-yellow font-black italic">Plus</span>
                            <span class="text-gray-400 font-normal">&bull; 100% Fresh</span>
                        </div>

                        <!-- Product Meta -->
                        <div class="p-2.5 sm:p-3 flex-1 flex flex-col justify-between">
                            <div>
                                <a href="<?= SITE_URL ?>/product-details.php?id=<?= $r['id'] ?>" class="text-xs sm:text-sm font-semibold text-gray-800 hover:text-fk-blue line-clamp-2 leading-snug mb-0.5">
                                    <?= htmlspecialchars($r['name']) ?>
                                </a>
                                <div class="text-[10px] sm:text-[11px] text-gray-400 font-medium mb-1.5"><?= htmlspecialchars($r['unit']) ?></div>
                                
                                <!-- Rating pill -->
                                <div class="flex items-center gap-1 mb-2">
                                    <span class="bg-emerald-700 text-white text-[9px] sm:text-[10px] font-black px-1.5 py-0.5 rounded flex items-center gap-0.5">
                                        <span><?= $r['rating'] ?></span>
                                        <i class="fa-solid fa-star text-[7px] sm:text-[8px]"></i>
                                    </span>
                                    <span class="text-[9px] sm:text-[10px] text-gray-400 font-medium">(<?= $r['rating_count'] ?>)</span>
                                </div>
                            </div>

                            <div>
                                <!-- Price Row -->
                                <div class="flex items-baseline flex-wrap gap-1 mb-2">
                                    <span class="text-sm sm:text-base font-black text-gray-950"><?= formatPrice($r['selling_price']) ?></span>
                                    <?php if ($r['mrp_price'] > $r['selling_price']): ?>
                                        <span class="text-[10px] sm:text-xs text-gray-400 line-through"><?= formatPrice($r['mrp_price']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <!-- Add to Cart Button -->
                                <button 
                                    onclick="addToCart(<?= $r['id'] ?>, 1, this)"
                                    class="w-full bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-700 border border-emerald-600 font-black text-xs py-1.5 rounded-xl transition flex items-center justify-center gap-1 cursor-pointer active:scale-95"
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
    <?php endif; ?>

</div>

<script>
    function switchProductImage(url, el) {
        const mainImg = document.getElementById('main-product-image');
        if (mainImg) {
            mainImg.style.opacity = '0.3';
            mainImg.src = url;
            setTimeout(() => { mainImg.style.opacity = '1'; }, 120);
        }
        document.querySelectorAll('.product-thumb-btn').forEach(btn => {
            btn.classList.remove('border-fk-blue');
            btn.classList.add('border-gray-200');
        });
        if (el) {
            el.classList.add('border-fk-blue');
            el.classList.remove('border-gray-200');
        }
    }

    function checkPincode() {
        const pin = document.getElementById('check-pincode').value.trim();
        const resEl = document.getElementById('pincode-result');
        if (pin.length === 6 && !isNaN(pin)) {
            resEl.innerHTML = `<i class="fa-solid fa-circle-check text-emerald-600"></i> Express delivery available for <strong>${pin}</strong> by Today within 2 hours!`;
            resEl.className = 'text-xs text-emerald-700 font-medium mt-1.5 flex items-center gap-1.5';
        } else {
            resEl.innerHTML = `<i class="fa-solid fa-circle-exclamation text-rose-600"></i> Please enter a valid 6-digit postal code.`;
            resEl.className = 'text-xs text-rose-600 font-medium mt-1.5 flex items-center gap-1.5';
        }
    }

    function buyNow(productId) {
        window.location.href = '<?= SITE_URL ?>/checkout.php?buy_now=1&product_id=' + productId + '&qty=1';
    }

    // Auto-reveal review box if arriving from 'Rate & Review' link
    if (window.location.hash === '#write-review-box') {
        const revBox = document.getElementById('write-review-box');
        if (revBox) {
            revBox.classList.remove('hidden');
            revBox.scrollIntoView({ behavior: 'smooth' });
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
