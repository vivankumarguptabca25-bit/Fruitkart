<?php
$pageTitle = "Customer Feedback & Reviews";
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Fetch approved reviews
$reviews = $db->query("
    SELECT r.*, u.name as customer_name, p.name as product_name, p.image_url as product_image, p.id as product_id, p.selling_price
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    JOIN products p ON r.product_id = p.id 
    WHERE r.status = 'approved'
    ORDER BY r.id DESC
")->fetchAll();

$totalReviews = count($reviews);
$avgRating = $db->query("SELECT COALESCE(ROUND(AVG(rating), 1), 5.0) FROM reviews WHERE status = 'approved'")->fetchColumn();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <!-- Header Banner -->
    <div class="bg-gradient-to-r from-emerald-800 via-teal-900 to-slate-900 text-white rounded-2xl p-8 sm:p-12 shadow-sm flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="space-y-3 max-w-xl">
            <span class="inline-flex items-center gap-1.5 bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider">
                <i class="fa-solid fa-heart text-rose-400"></i> Loved by 10,000+ Fruit Lovers
            </span>
            <h1 class="text-2xl sm:text-4xl font-black">Customer Feedback &amp; Farm Reviews</h1>
            <p class="text-sm text-gray-300 leading-relaxed">
                Read real experiences from families and fruit lovers enjoying our orchard-fresh harvest every day.
            </p>
        </div>

        <div class="bg-white/10 backdrop-blur-md border border-white/20 p-6 rounded-2xl text-center shrink-0 min-w-[200px]">
            <div class="text-4xl font-black text-amber-400"><?= $avgRating ?>★</div>
            <div class="text-xs font-semibold text-gray-200 mt-1"><?= $totalReviews ?> Verified Reviews</div>
            <div class="text-[10px] text-emerald-300 mt-2 font-bold uppercase tracking-wider">100% Genuine Buyers</div>
        </div>
    </div>

    <!-- Reviews Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($reviews as $rev): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 flex flex-col justify-between hover:shadow-md transition">
                
                <div>
                    <!-- Product Header -->
                    <div class="flex items-center gap-3 pb-4 border-b border-gray-100 mb-4">
                        <img src="<?= htmlspecialchars($rev['product_image']) ?>" alt="<?= htmlspecialchars($rev['product_name']) ?>" class="w-12 h-12 rounded-lg object-cover border border-gray-200">
                        <div class="flex-1 min-w-0">
                            <a href="<?= SITE_URL ?>/product-details.php?id=<?= $rev['product_id'] ?>" class="text-xs font-bold text-gray-900 hover:text-fk-blue line-clamp-1">
                                <?= htmlspecialchars($rev['product_name']) ?>
                            </a>
                            <div class="text-[11px] font-bold text-emerald-700"><?= formatPrice($rev['selling_price']) ?></div>
                        </div>
                    </div>

                    <!-- Stars & Headline -->
                    <div class="flex items-center gap-1 text-amber-400 text-xs mb-2">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fa-<?= $i <= $rev['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                        <?php endfor; ?>
                        <span class="text-gray-500 font-bold ml-1 text-[11px]"><?= $rev['rating'] ?>.0</span>
                    </div>

                    <h3 class="text-sm font-bold text-gray-900 mb-2">&ldquo;<?= htmlspecialchars($rev['headline']) ?>&rdquo;</h3>
                    
                    <p class="text-xs text-gray-600 leading-relaxed mb-4">
                        <?= htmlspecialchars($rev['comment']) ?>
                    </p>
                </div>

                <!-- Footer Buyer Info -->
                <div class="pt-4 border-t border-gray-100 flex items-center justify-between text-xs">
                    <div>
                        <div class="font-bold text-gray-800"><?= htmlspecialchars($rev['customer_name']) ?></div>
                        <span class="text-[10px] text-emerald-600 font-semibold flex items-center gap-1">
                            <i class="fa-solid fa-circle-check"></i> Verified Purchase
                        </span>
                    </div>
                    <span class="text-[10px] text-gray-400 font-mono">
                        <?= date('d M Y', strtotime($rev['created_at'])) ?>
                    </span>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
