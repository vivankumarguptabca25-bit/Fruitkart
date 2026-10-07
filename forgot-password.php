<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$message = '';
$error = '';
$step = 1; // 1: Enter email/phone, 2: Enter new password, 3: Success

// Check if user is currently resetting password in session
$resetUserId = $_SESSION['reset_user_id'] ?? null;
$resetUserData = null;

if ($resetUserId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, email, phone FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$resetUserId]);
    $resetUserData = $stmt->fetch();
    if ($resetUserData) {
        $step = 2;
    } else {
        unset($_SESSION['reset_user_id']);
        $step = 1;
    }
}

// Reset cancel action
if (isset($_GET['cancel'])) {
    unset($_SESSION['reset_user_id']);
    header("Location: " . SITE_URL . "/forgot-password.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'find_account';

    // STEP 1: Find Account by Email OR Phone
    if ($action === 'find_account') {
        $identifier = clean($_POST['identifier'] ?? $_POST['email'] ?? '');

        if (!empty($identifier)) {
            $db = getDB();
            $cleanIdent = strtolower(trim($identifier));
            $cleanDigits = preg_replace('/[^0-9]/', '', $identifier);

            // Match Email (case-insensitive) OR Phone (with/without country code)
            $stmt = $db->prepare("
                SELECT id, name, email, phone 
                FROM users 
                WHERE LOWER(TRIM(email)) = ? 
                   OR (phone != '' AND phone IS NOT NULL AND (phone = ? OR phone = ?))
                LIMIT 1
            ");
            $stmt->execute([$cleanIdent, $cleanIdent, $cleanDigits]);
            $foundUser = $stmt->fetch();

            if ($foundUser) {
                $_SESSION['reset_user_id'] = $foundUser['id'];
                $resetUserData = $foundUser;
                $step = 2;
                $message = "Account verified! Hello, " . htmlspecialchars($foundUser['name']) . ". Ab aap apna naya password set karein.";
            } else {
                $error = "No account found with '" . htmlspecialchars($identifier) . "'. Kripya sahi Gmail ya 10-digit mobile number enter karein.";
            }
        } else {
            $error = 'Kripya apna registered Gmail ya Mobile number enter karein.';
        }
    }

    // STEP 2: Update with New Password
    if ($action === 'reset_password') {
        if (!$resetUserId || !$resetUserData) {
            $error = 'Session expired. Kripya shuru se try karein.';
            $step = 1;
        } else {
            $newPass = $_POST['new_password'] ?? '';
            $confirmPass = $_POST['confirm_password'] ?? '';

            if (empty($newPass) || empty($confirmPass)) {
                $error = 'Dono password fields bharna anivarya hai.';
                $step = 2;
            } elseif (strlen($newPass) < 4) {
                $error = 'Naya password kam se kam 4 characters ka hona chahiye.';
                $step = 2;
            } elseif ($newPass !== $confirmPass) {
                $error = 'New Password aur Confirm Password aapas me match nahi karte!';
                $step = 2;
            } else {
                // Update password in DB
                $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                $db = getDB();
                $update = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update->execute([$newHash, $resetUserId]);

                unset($_SESSION['reset_user_id']);
                $step = 3;
                $message = "Aapka naya password successfully update ho gaya hai! Ab aap naye password se login kar sakte hain.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | FruitKart - Fresh Farm Fruits Online</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/custom.css">
</head>
<body class="auth-bg-orchard min-h-screen relative overflow-x-hidden flex items-center justify-center p-4 sm:p-6 selection:bg-emerald-500 selection:text-white">

    <div class="max-w-md w-full auth-glass-card rounded-3xl shadow-2xl p-6 sm:p-8 border border-white/60 text-center relative z-10">
        
        <!-- Brand Logo -->
        <a href="<?= SITE_URL ?>/index.php" class="inline-flex items-center gap-1.5 text-2xl font-black italic tracking-wide text-gray-900 mb-4 group">
            <span>Fruit<span class="text-amber-500">Kart</span></span>
            <i class="fa-solid fa-apple-whole text-amber-500 text-xl group-hover:rotate-12 transition-transform"></i>
        </a>

        <!-- Header Icon -->
        <div class="w-16 h-16 <?= $step === 3 ? 'bg-emerald-100 text-emerald-600 border-emerald-300' : 'bg-gradient-to-tr from-amber-100 to-emerald-100 text-emerald-700 border-emerald-200' ?> rounded-2xl flex items-center justify-center text-2xl mx-auto mb-3 shadow-inner border">
            <i class="fa-solid <?= $step === 3 ? 'fa-circle-check text-3xl' : ($step === 2 ? 'fa-lock-open' : 'fa-key') ?>"></i>
        </div>

        <h2 class="text-xl font-black text-gray-900 mb-1">
            <?= $step === 3 ? 'Password Reset Successful! 🎉' : ($step === 2 ? 'Set New Password 🔐' : 'Forgot Your Password? 🔑') ?>
        </h2>
        
        <p class="text-xs text-gray-500 mb-5">
            <?php if ($step === 1): ?>
                Apna registered <strong>Gmail</strong> ya <strong>10-digit Mobile Number</strong> enter karein.
            <?php elseif ($step === 2): ?>
                Naya password create karein aur save karein.
            <?php else: ?>
                Aapka password update ho gaya hai. Ab aap Sign In kar sakte hain.
            <?php endif; ?>
        </p>

        <!-- Success Alert Box -->
        <?php if (!empty($message)): ?>
            <div class="p-3.5 bg-emerald-50 text-emerald-800 text-xs font-semibold rounded-xl border border-emerald-200 mb-4 text-left flex items-start gap-2 shadow-2xs">
                <i class="fa-solid fa-circle-check mt-0.5 text-emerald-600 shrink-0"></i>
                <span><?= $message ?></span>
            </div>
        <?php endif; ?>

        <!-- Error Alert Box -->
        <?php if (!empty($error)): ?>
            <div class="p-3.5 bg-rose-50 text-rose-700 text-xs font-semibold rounded-xl border border-rose-200 mb-4 text-left flex items-start gap-2 shadow-2xs">
                <i class="fa-solid fa-circle-exclamation mt-0.5 text-rose-600 shrink-0"></i>
                <div class="space-y-1">
                    <p><?= htmlspecialchars($error) ?></p>
                    <?php if ($step === 1): ?>
                        <div class="pt-1.5 flex items-center gap-2">
                            <a href="<?= SITE_URL ?>/signup.php" class="inline-block bg-rose-600 hover:bg-rose-700 text-white font-bold px-3 py-1 rounded-lg text-[11px] shadow">
                                + Naya Account Banayein (Sign Up)
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ======================================================== -->
        <!-- STEP 1: FIND ACCOUNT BY EMAIL OR PHONE                   -->
        <!-- ======================================================== -->
        <?php if ($step === 1): ?>
            <form action="<?= SITE_URL ?>/forgot-password.php" method="POST" class="space-y-4 text-left">
                <input type="hidden" name="action" value="find_account">

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Registered Email or Mobile Number <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-3.5 text-gray-400 text-xs">
                            <i class="fa-regular fa-envelope"></i>
                        </span>
                        <input 
                            type="text" 
                            name="identifier" 
                            required 
                            autofocus
                            placeholder="e.g. user@gmail.com ya 9876543210" 
                            class="w-full text-xs pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 font-medium text-gray-900 bg-gray-50/50 hover:bg-white transition"
                        >
                    </div>
                </div>

                <button 
                    type="submit" 
                    class="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-black py-3 rounded-xl shadow-lg hover:shadow-xl transition transform active:scale-[0.99] uppercase tracking-wider text-xs cursor-pointer flex items-center justify-center gap-2"
                >
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <span>Find Account &amp; Reset Password</span>
                </button>
            </form>

            <!-- Quick Account Suggestions / Help Box -->
            <div class="mt-5 p-3.5 bg-slate-50 rounded-xl border border-slate-200 text-left text-xs space-y-2">
                <div class="font-bold text-slate-700 text-[11px] uppercase tracking-wider flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-question text-amber-500"></i>
                    <span>Email Yaad Nahi Hai?</span>
                </div>
                <p class="text-[11px] text-slate-500 leading-relaxed">
                    Aap apna <strong>10-digit Phone Number</strong> bhi daal sakte hain. Agar abhi tak account nahi hai to naya account banayein:
                </p>
                <div class="pt-1 flex flex-wrap gap-2">
                    <a href="<?= SITE_URL ?>/signup.php" class="text-xs font-bold text-emerald-700 hover:underline">
                        &rarr; Click here to Sign Up
                    </a>
                    <span class="text-slate-300">|</span>
                    <a href="<?= SITE_URL ?>/login.php" class="text-xs font-bold text-slate-600 hover:underline">
                        Sign In Page
                    </a>
                </div>
            </div>

        <!-- ======================================================== -->
        <!-- STEP 2: SET NEW PASSWORD                                 -->
        <!-- ======================================================== -->
        <?php elseif ($step === 2 && $resetUserData): ?>
            
            <!-- User Info Badge -->
            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-xl text-left text-xs flex items-center justify-between">
                <div>
                    <div class="text-[10px] text-blue-600 font-bold uppercase tracking-wider">Account Found ✓</div>
                    <div class="font-black text-gray-900"><?= htmlspecialchars($resetUserData['name']) ?></div>
                    <div class="text-[11px] text-gray-500 font-mono"><?= htmlspecialchars($resetUserData['email']) ?></div>
                </div>
                <a href="<?= SITE_URL ?>/forgot-password.php?cancel=1" class="text-[11px] text-rose-600 font-bold hover:underline shrink-0">
                    Change Email
                </a>
            </div>

            <form action="<?= SITE_URL ?>/forgot-password.php" method="POST" class="space-y-4 text-left">
                <input type="hidden" name="action" value="reset_password">

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        New Password <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-3.5 text-gray-400 text-xs">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input 
                            type="password" 
                            name="new_password" 
                            id="new-pass-input"
                            required 
                            minlength="4"
                            placeholder="Enter new password (min. 4 characters)" 
                            class="w-full text-xs pl-10 pr-10 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 font-medium text-gray-900 bg-gray-50/50 hover:bg-white transition"
                        >
                        <button 
                            type="button" 
                            onclick="togglePasswordVisibility('new-pass-input', 'eye-icon-1')" 
                            class="absolute right-3.5 top-3.5 text-gray-400 hover:text-gray-700 text-xs cursor-pointer"
                        >
                            <i id="eye-icon-1" class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Confirm New Password <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-3.5 text-gray-400 text-xs">
                            <i class="fa-solid fa-lock-open"></i>
                        </span>
                        <input 
                            type="password" 
                            name="confirm_password" 
                            id="confirm-pass-input"
                            required 
                            minlength="4"
                            placeholder="Re-enter new password" 
                            class="w-full text-xs pl-10 pr-10 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 font-medium text-gray-900 bg-gray-50/50 hover:bg-white transition"
                        >
                        <button 
                            type="button" 
                            onclick="togglePasswordVisibility('confirm-pass-input', 'eye-icon-2')" 
                            class="absolute right-3.5 top-3.5 text-gray-400 hover:text-gray-700 text-xs cursor-pointer"
                        >
                            <i id="eye-icon-2" class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button 
                    type="submit" 
                    class="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-black py-3 rounded-xl shadow-lg hover:shadow-xl transition transform active:scale-[0.99] uppercase tracking-wider text-xs cursor-pointer flex items-center justify-center gap-2"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Save &amp; Update Password</span>
                </button>
            </form>

        <!-- ======================================================== -->
        <!-- STEP 3: SUCCESS CONFIRMATION                             -->
        <!-- ======================================================== -->
        <?php elseif ($step === 3): ?>
            
            <div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-200 text-emerald-900 text-xs space-y-2 mb-5">
                <div class="font-black text-sm text-emerald-800">
                    Aapka Password Badla Jaa Chuka Hai!
                </div>
                <p class="text-emerald-700 leading-relaxed">
                    Naya password safalta-purvak save ho gaya hai. Ab aap apne naye password se turant Sign In kar sakte hain.
                </p>
            </div>

            <a 
                href="<?= SITE_URL ?>/login.php?tab=login" 
                class="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-black py-3 rounded-xl shadow-lg hover:shadow-xl transition transform active:scale-[0.99] uppercase tracking-wider text-xs flex items-center justify-center gap-2"
            >
                <i class="fa-solid fa-arrow-right-to-bracket"></i>
                <span>Sign In With New Password ➔</span>
            </a>

        <?php endif; ?>

        <!-- Footer Back Link -->
        <div class="mt-6 pt-4 border-t border-gray-100 text-xs flex items-center justify-between">
            <a href="<?= SITE_URL ?>/login.php" class="text-emerald-700 font-extrabold hover:text-emerald-800 hover:underline flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left text-xs"></i>
                <span>Back to Sign In</span>
            </a>
            <a href="<?= SITE_URL ?>/signup.php" class="text-amber-600 font-bold hover:underline">
                Create Account
            </a>
        </div>

    </div>

    <script>
    function togglePasswordVisibility(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input && icon) {
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fa-regular fa-eye-slash text-emerald-600';
            } else {
                input.type = 'password';
                icon.className = 'fa-regular fa-eye';
            }
        }
    }
    </script>

</body>
</html>
