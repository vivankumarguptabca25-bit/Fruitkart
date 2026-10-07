<?php
$pageTitle = "My Wishlist";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

// Handle move to cart
if (isset($_GET['move_to_cart'])) {
    $prodId = (int)$_GET['move_to_cart'];
    // Add to cart
    $stmt = $db->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $prodId]);
    $existing = $stmt->fetch();
    if ($existing) {
        $db->prepare("UPDATE cart SET quantity = quantity + 1 WHERE id = ?")->execute([$existing['id']]);
    } else {
        $db->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)")->execute([$userId, $prodId]);
    }
    // Remove from wishlist
    $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?")->execute([$userId, $prodId]);
    setFlash('success', 'Moved fruit to Cart!');
    header("Location: " . SITE_URL . "/wishlist.php");
    exit;
}

// Handle remove from wishlist
if (isset($_GET['remove'])) {
    $prodId = (int)$_GET['remove'];
    $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?")->execute([$userId, $prodId]);
    setFlash('info', 'Item removed from Wishlist.');
    header("Location: " . SITE_URL . "/wishlist.php");
    exit;
}

// Fetch wishlist items
$stmt = $db->prepare("
    SELECT w.id as wishlist_id, p.*, c.name as category_name 
    FROM wishlist w 
    JOIN products p ON w.product_id = p.id 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE w.user_id = ? 
    ORDER BY w.created_at DESC
");
$stmt->execute([$userId]);
$wishlistItems = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        
        <!-- Header -->
        <div class="p-4 sm:p-6 border-b border-gray-100 flex items-center justify-between">
            <h1 class="text-base sm:text-lg font-bold text-gray-900 flex items-center gap-2">
                <i class="fa-solid fa-heart text-rose-500"></i>
                <span>My Wishlist (<?= count($wishlistItems) ?>)</span>
            </h1>
            <a href="<?= SITE_URL ?>/products.php" class="text-xs font-bold text-fk-blue hover:underline">
                Continue Shopping &rarr;
            </a>
        </div>

        <?php if (count($wishlistItems) > 0): ?>
            <div class="divide-y divide-gray-100">
                <?php foreach ($wishlistItems as $item): ?>
                    <div class="p-4 sm:p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 hover:bg-gray-50/50 transition">
                        
                        <!-- Left Details -->
                        <div class="flex items-center gap-4">
                            <a href="<?= SITE_URL ?>/product-details.php?id=<?= $item['id'] ?>" class="w-20 h-20 rounded-lg overflow-hidden border border-gray-200 bg-gray-50 shrink-0">
                                <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover">
                            </a>

                            <div>
                                <a href="<?= SITE_URL ?>/product-details.php?id=<?= $item['id'] ?>" class="text-sm font-bold text-gray-900 hover:text-fk-blue line-clamp-1">
                                    <?= htmlspecialchars($item['name']) ?>
                                </a>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    <?= htmlspecialchars($item['category_name']) ?> &bull; Unit: <?= htmlspecialchars($item['unit']) ?>
                                </div>
                                
                                <div class="flex items-baseline gap-2 mt-1.5">
                                    <span class="text-base font-black text-gray-900"><?= formatPrice($item['selling_price']) ?></span>
                                    <?php if ($item['mrp_price'] > $item['selling_price']): ?>
                                        <span class="text-xs text-gray-400 line-through"><?= formatPrice($item['mrp_price']) ?></span>
                                        <span class="text-xs font-bold text-emerald-700"><?= $item['discount_percent'] ?>% Off</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right Actions -->
                        <div class="flex items-center gap-3 w-full sm:w-auto justify-end pt-2 sm:pt-0 border-t sm:border-t-0 border-gray-100">
                            <a href="<?= SITE_URL ?>/wishlist.php?move_to_cart=<?= $item['id'] ?>" class="bg-fk-yellow hover:bg-yellow-400 text-gray-900 text-xs font-bold px-4 py-2 rounded-lg shadow-sm transition flex items-center gap-1.5">
                                <i class="fa-solid fa-cart-shopping text-xs"></i>
                                <span>Move to Cart</span>
                            </a>

                            <a href="<?= SITE_URL ?>/wishlist.php?remove=<?= $item['id'] ?>" onclick="return confirm('Remove this fruit from your wishlist?')" class="text-gray-400 hover:text-rose-600 p-2 text-sm transition" title="Remove">
                                <i class="fa-regular fa-trash-can"></i>
                            </a>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="p-12 text-center">
                <div class="w-20 h-20 mx-auto rounded-full bg-rose-50 text-rose-500 flex items-center justify-center text-3xl mb-4">
                    <i class="fa-regular fa-heart"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-1">Your Wishlist is Empty</h3>
                <p class="text-xs text-gray-500 max-w-sm mx-auto mb-6">Explore our fresh hand-picked fruits and save your favorites for later.</p>
                <a href="<?= SITE_URL ?>/products.php" class="bg-fk-blue text-white text-xs font-bold px-6 py-2.5 rounded-lg shadow hover:bg-fk-blue-dark transition inline-block">
                    EXPLORE FRESH FRUITS
                </a>
            </div>
        <?php endif; ?>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
