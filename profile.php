<?php
$pageTitle = "My Profile & Addresses";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$db = getDB();
$userId = $_SESSION['user_id'];

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = clean($_POST['name'] ?? '');
    $phone = clean($_POST['phone'] ?? '');

    if (!empty($name)) {
        $stmt = $db->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $userId]);
        $_SESSION['user_name'] = $name;
        setFlash('success', 'Profile information updated successfully!');
        header("Location: " . SITE_URL . "/profile.php");
        exit;
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (!empty($newPass) && $newPass === $confirmPass) {
        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $userId]);
        setFlash('success', 'Your password has been changed securely!');
        header("Location: " . SITE_URL . "/profile.php");
        exit;
    } else {
        setFlash('error', 'New passwords do not match or are empty.');
    }
}

// Handle Delete Address
if (isset($_GET['delete_addr'])) {
    $delId = (int)$_GET['delete_addr'];
    $db->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?")->execute([$delId, $userId]);
    setFlash('info', 'Address removed successfully.');
    header("Location: " . SITE_URL . "/profile.php");
    exit;
}

// Handle Set Default Address
if (isset($_GET['set_default'])) {
    $defId = (int)$_GET['set_default'];
    $db->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
    $db->prepare("UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$defId, $userId]);
    setFlash('success', 'Default address updated.');
    header("Location: " . SITE_URL . "/profile.php");
    exit;
}

// Fetch user data
$user = currentUser();

// Fetch addresses
$addrStmt = $db->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$addrStmt->execute([$userId]);
$addresses = $addrStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="grid grid-cols-1 md:grid-cols-12 gap-6">

        <!-- Left Navigation Sidebar (4 cols) -->
        <div class="md:col-span-4 space-y-4">
            
            <!-- User Info Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-4">
                <div class="w-14 h-14 rounded-full bg-fk-blue text-white flex items-center justify-center text-xl font-bold uppercase shadow">
                    <?= substr($user['name'], 0, 1) ?>
                </div>
                <div class="overflow-hidden">
                    <span class="text-[11px] text-gray-400">Hello,</span>
                    <h2 class="text-base font-bold text-gray-900 truncate"><?= htmlspecialchars($user['name']) ?></h2>
                    <span class="text-xs text-gray-500 font-mono"><?= htmlspecialchars($user['email']) ?></span>
                </div>
            </div>

            <!-- Profile Nav Menu -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden divide-y divide-gray-100 text-xs">
                <?php if (isAdmin()): ?>
                    <a href="<?= ADMIN_URL ?>/index.php" class="flex items-center gap-3 px-4 py-3 bg-amber-50 hover:bg-amber-100 text-amber-900 font-black transition">
                        <i class="fa-solid fa-shield-halved text-amber-600 w-4"></i>
                        <span>ADMIN DASHBOARD &amp; PORTAL</span>
                    </a>
                <?php endif; ?>
                <a href="<?= SITE_URL ?>/my-orders.php" class="flex items-center gap-3 px-4 py-3 hover:bg-blue-50 text-gray-700 font-medium transition">
                    <i class="fa-solid fa-box text-fk-blue w-4"></i>
                    <span>MY ORDERS</span>
                </a>
                <a href="<?= SITE_URL ?>/wishlist.php" class="flex items-center gap-3 px-4 py-3 hover:bg-blue-50 text-gray-700 font-medium transition">
                    <i class="fa-solid fa-heart text-rose-500 w-4"></i>
                    <span>MY WISHLIST</span>
                </a>
                <a href="#personal-info" class="flex items-center gap-3 px-4 py-3 bg-blue-50/50 text-fk-blue font-bold">
                    <i class="fa-solid fa-user-gear w-4"></i>
                    <span>ACCOUNT SETTINGS</span>
                </a>
                <a href="<?= SITE_URL ?>/logout.php" class="flex items-center gap-3 px-4 py-3 hover:bg-rose-50 text-rose-600 font-bold transition">
                    <i class="fa-solid fa-arrow-right-from-bracket w-4"></i>
                    <span>LOGOUT</span>
                </a>
            </div>

        </div>

        <!-- Right Content Forms (8 cols) -->
        <div class="md:col-span-8 space-y-6">
            
            <!-- Personal Info Card -->
            <div id="personal-info" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4 pb-2 border-b border-gray-100">
                    Personal Information
                </h3>

                <form action="<?= SITE_URL ?>/profile.php" method="POST" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Full Name</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required class="w-full text-xs p-2.5 border rounded focus:outline-none focus:border-fk-blue">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Mobile Phone</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="10-digit mobile" maxlength="10" class="w-full text-xs p-2.5 border rounded focus:outline-none focus:border-fk-blue">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Email Address (Read Only)</label>
                        <input type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly class="w-full text-xs p-2.5 border rounded bg-gray-50 text-gray-500 cursor-not-allowed">
                    </div>

                    <button type="submit" name="update_profile" class="bg-fk-blue hover:bg-fk-blue-dark text-white font-bold text-xs px-6 py-2.5 rounded shadow transition">
                        Save Changes
                    </button>
                </form>
            </div>

            <!-- Manage Addresses Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">
                        Manage Delivery Addresses
                    </h3>
                    <a href="<?= SITE_URL ?>/checkout.php" class="text-xs font-bold text-fk-blue hover:underline">
                        + Add Address via Checkout
                    </a>
                </div>

                <div class="space-y-3">
                    <?php if (count($addresses) > 0): ?>
                        <?php foreach ($addresses as $addr): ?>
                            <div class="p-4 rounded-lg border <?= $addr['is_default'] ? 'border-blue-300 bg-blue-50/20' : 'border-gray-200' ?> flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-900"><?= htmlspecialchars($addr['full_name']) ?></span>
                                        <span class="bg-gray-200 text-gray-700 text-[10px] font-bold px-2 py-0.5 rounded uppercase"><?= htmlspecialchars($addr['address_type']) ?></span>
                                        <span class="font-mono text-gray-600"><?= htmlspecialchars($addr['phone']) ?></span>
                                        <?php if ($addr['is_default']): ?>
                                            <span class="bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2 py-0.5 rounded">DEFAULT</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-gray-600 mt-1 leading-relaxed">
                                        <?= htmlspecialchars($addr['address_line']) ?>, <?= htmlspecialchars($addr['locality']) ?>, <?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['state']) ?> - <?= htmlspecialchars($addr['pincode']) ?>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 shrink-0 pt-2 sm:pt-0">
                                    <?php if (!$addr['is_default']): ?>
                                        <a href="<?= SITE_URL ?>/profile.php?set_default=<?= $addr['id'] ?>" class="text-xs text-fk-blue font-bold hover:underline">Set Default</a>
                                    <?php endif; ?>
                                    <a href="<?= SITE_URL ?>/profile.php?delete_addr=<?= $addr['id'] ?>" onclick="return confirm('Delete this address?')" class="text-gray-400 hover:text-rose-600 text-sm transition" title="Delete">
                                        <i class="fa-regular fa-trash-can"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-xs text-gray-500 py-3">No delivery addresses saved yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Change Password Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4 pb-2 border-b border-gray-100">
                    Security &amp; Password
                </h3>

                <form action="<?= SITE_URL ?>/profile.php" method="POST" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">New Password</label>
                            <input type="password" name="new_password" required placeholder="Enter new password" class="w-full text-xs p-2.5 border rounded focus:outline-none focus:border-fk-blue">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Confirm New Password</label>
                            <input type="password" name="confirm_password" required placeholder="Re-enter password" class="w-full text-xs p-2.5 border rounded focus:outline-none focus:border-fk-blue">
                        </div>
                    </div>

                    <button type="submit" name="change_password" class="bg-gray-800 hover:bg-black text-white font-bold text-xs px-6 py-2.5 rounded shadow transition">
                        Update Password
                    </button>
                </form>
            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
