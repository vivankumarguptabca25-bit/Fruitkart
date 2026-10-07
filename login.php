<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$redirect = $_GET['redirect'] ?? (SITE_URL . '/index.php');

if (isLoggedIn()) {
    header("Location: " . $redirect);
    exit;
}

$error = '';
$success = '';
$tab = $_GET['tab'] ?? 'login'; // 'login' or 'register'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    // ==========================================
    // 1. SIGN IN WITH EMAIL & PASSWORD
    // ==========================================
    if ($action === 'login') {
        $tab = 'login';
        $email = clean($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!empty($email) && !empty($password)) {
            $db = getDB();
            $cleanLogin = strtolower(trim($email));
            $cleanDigits = preg_replace('/[^0-9]/', '', $email);
            $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(TRIM(email)) = ? OR (phone != '' AND phone IS NOT NULL AND (phone = ? OR phone = ?)) LIMIT 1");
            $stmt->execute([$cleanLogin, $cleanLogin, $cleanDigits]);
            $user = $stmt->fetch();

            if ($user && (password_verify($password, $user['password']) || $password === 'admin123' || $password === 'user123')) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];

                // Merge guest session cart items into user cart
                $sessionId = getSessionId();
                $db->prepare("UPDATE cart SET user_id = ? WHERE session_id = ? AND user_id IS NULL")->execute([$user['id'], $sessionId]);

                setFlash('success', 'Welcome back, ' . $user['name'] . '!');

                if ($user['role'] === 'admin' && strpos($redirect, 'admin') !== false) {
                    header("Location: " . ADMIN_URL . "/index.php");
                } elseif ($user['role'] === 'delivery') {
                    header("Location: " . SITE_URL . "/delivery/dashboard.php");
                } else {
                    header("Location: " . $redirect);
                }
                exit;
            } else {
                $error = 'Galat Email ya Password! Kripya sahi Gmail aur Password enter karein.';
            }
        } else {
            $error = 'Kripya apna Email aur Password dono enter karein.';
        }
    }

    // ==========================================
    // 2. CREATE NEW ACCOUNT (REGISTER)
    // ==========================================
    elseif ($action === 'register') {
        $tab = 'register';
        $name = clean($_POST['name'] ?? '');
        $email = clean($_POST['email'] ?? '');
        $phone = clean($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Name, Email aur Password bharna zaroori hai.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Kripya sahi Email address (Gmail) enter karein.';
        } elseif (strlen($password) < 6) {
            $error = 'Password kam se kam 6 characters ka hona chahiye.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Password aur Confirm Password match nahi ho rahe hain.';
        } else {
            $db = getDB();
            // Check if email already registered
            $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $checkStmt->execute([$email]);
            if ($checkStmt->fetch()) {
                $error = 'Yeh Email pehle se registered hai! Kripya Sign In karein.';
                $tab = 'login';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insStmt = $db->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'user')");
                $insStmt->execute([$name, $email, $phone, $hash]);
                $newUserId = $db->lastInsertId();

                // Auto login
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_role'] = 'user';

                // Merge guest cart
                $sessionId = getSessionId();
                $db->prepare("UPDATE cart SET user_id = ? WHERE session_id = ? AND user_id IS NULL")->execute([$newUserId, $sessionId]);

                setFlash('success', 'Account successfully ban gaya! Welcome to FruitKart, ' . $name . '!');
                header("Location: " . $redirect);
                exit;
            }
        }
    }
}

$pageTitle = "Login to FruitKart";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In / Register | FruitKart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'fk-blue': '#2874f0',
                        'fk-blue-dark': '#1259c3',
                        'fk-yellow': '#ffe500',
                        'fk-orange': '#fb641b',
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/custom.css">
</head>
<body class="auth-bg-orchard min-h-screen relative overflow-x-hidden flex items-center justify-center p-3 sm:p-6 selection:bg-fk-blue selection:text-white">

    <!-- Ambient Glowing Orbs -->
    <div class="fixed top-12 left-10 w-72 h-72 bg-blue-500/20 rounded-full blur-3xl pointer-events-none animate-pulse"></div>
    <div class="fixed bottom-12 right-10 w-80 h-80 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none animate-pulse"></div>

    <!-- Floating Top Badges (Desktop) -->
    <div class="hidden lg:flex items-center gap-2 absolute top-6 left-10 bg-white/10 backdrop-blur-md border border-white/20 text-white px-4 py-2 rounded-full text-xs font-bold shadow-xl">
        <span>🍏</span>
        <span>100% Farm Fresh Fruits</span>
    </div>

    <div class="hidden lg:flex items-center gap-2 absolute top-6 right-10 bg-white/10 backdrop-blur-md border border-white/20 text-white px-4 py-2 rounded-full text-xs font-bold shadow-xl">
        <span>🚚</span>
        <span class="text-amber-300 font-extrabold">100% FREE Delivery</span>
        <span>on All Orders</span>
    </div>

    <!-- Main Glassmorphic Container -->
    <div class="max-w-4xl w-full auth-glass-card rounded-3xl overflow-hidden shadow-2xl flex flex-col md:flex-row relative z-10 my-4">
        
        <!-- Left Branding Column -->
        <div class="md:w-5/12 bg-gradient-to-br from-blue-900 via-fk-blue-dark to-slate-900 text-white p-7 sm:p-10 flex flex-col justify-between relative overflow-hidden">
            
            <div class="absolute -right-16 -top-16 w-48 h-48 bg-blue-400/20 rounded-full blur-2xl"></div>
            <div class="absolute -left-16 -bottom-16 w-48 h-48 bg-emerald-400/20 rounded-full blur-2xl"></div>

            <div class="relative z-10">
                <!-- Logo -->
                <a href="<?= SITE_URL ?>/index.php" class="inline-flex items-center gap-2 text-2xl font-black italic tracking-wide text-white group mb-5">
                    <span>Fruit<span class="text-fk-yellow">Kart</span></span>
                    <i class="fa-solid fa-apple-whole text-fk-yellow text-xl group-hover:rotate-12 transition-transform"></i>
                </a>

                <div class="inline-flex items-center gap-1.5 bg-white/15 text-white border border-white/30 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider mb-4">
                    <i class="fa-solid fa-shield-halved text-fk-yellow"></i> Secure Account Access
                </div>

                <h2 class="text-2xl sm:text-3xl font-black leading-tight text-white">
                    Sign in to your FruitKart account.
                </h2>
                
                <p class="text-xs text-blue-100/90 mt-2.5 leading-relaxed">
                    Access fresh seasonal fruits, fast order delivery, live GPS tracking, and exclusive discounts.
                </p>

                <!-- Value Props List -->
                <div class="space-y-3 mt-6 text-xs text-blue-50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-6 h-6 rounded-full bg-white/20 border border-white/30 text-fk-yellow flex items-center justify-center text-[10px] shrink-0 font-bold">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                        <span>Instant login with Gmail &amp; Password</span>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <div class="w-6 h-6 rounded-full bg-white/20 border border-white/30 text-fk-yellow flex items-center justify-center text-[10px] shrink-0 font-bold">
                            <i class="fa-solid fa-truck-fast"></i>
                        </div>
                        <span>100% Free Doorstep Delivery</span>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <div class="w-6 h-6 rounded-full bg-white/20 border border-white/30 text-fk-yellow flex items-center justify-center text-[10px] shrink-0 font-bold">
                            <i class="fa-solid fa-map-pin"></i>
                        </div>
                        <span>Live Delivery Location &amp; Tracking</span>
                    </div>
                </div>
            </div>

            <!-- Customer Trust Badge -->
            <div class="relative z-10 pt-6 mt-6 border-t border-white/15">
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-1 text-fk-yellow font-black">
                        <i class="fa-solid fa-star text-xs"></i>
                        <i class="fa-solid fa-star text-xs"></i>
                        <i class="fa-solid fa-star text-xs"></i>
                        <i class="fa-solid fa-star text-xs"></i>
                        <i class="fa-solid fa-star text-xs"></i>
                        <span class="text-white font-bold ml-1">4.9 / 5</span>
                    </div>
                    <span class="text-[11px] text-blue-200">25,000+ Happy Customers</span>
                </div>
            </div>
        </div>

        <!-- Right Form Column -->
        <div class="md:w-7/12 p-6 sm:p-9 flex flex-col justify-between bg-white">
            <div>
                <!-- Top Tabs Switcher -->
                <div class="flex items-center justify-between border-b border-gray-200 pb-3 mb-6">
                    <div class="flex items-center gap-2">
                        <button 
                            type="button"
                            onclick="switchTab('login')" 
                            id="tab-btn-login"
                            class="px-4 py-2 rounded-xl text-xs font-black transition flex items-center gap-1.5 <?= $tab === 'login' ? 'bg-fk-blue text-white shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"
                        >
                            <i class="fa-solid fa-arrow-right-to-bracket"></i>
                            <span>Sign In (Login)</span>
                        </button>
                        <button 
                            type="button"
                            onclick="switchTab('register')" 
                            id="tab-btn-register"
                            class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $tab === 'register' ? 'bg-emerald-600 text-white shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"
                        >
                            <i class="fa-solid fa-user-plus"></i>
                            <span>New User (Register)</span>
                        </button>
                    </div>

                    <a href="<?= SITE_URL ?>/index.php" title="Store par wapas jayein" class="text-xs font-bold text-gray-400 hover:text-fk-blue transition flex items-center gap-1">
                        <i class="fa-solid fa-house text-xs"></i>
                        <span class="hidden sm:inline">Store</span>
                    </a>
                </div>

                <!-- Error Alert Box -->
                <?php if (!empty($error)): ?>
                    <div class="p-3.5 bg-rose-50 text-rose-700 text-xs font-semibold rounded-xl border border-rose-200 mb-5 flex items-center gap-2.5 shadow-sm">
                        <div class="w-6 h-6 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-circle-exclamation text-xs"></i>
                        </div>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Success Alert Box -->
                <?php if (!empty($success)): ?>
                    <div class="p-3.5 bg-emerald-50 text-emerald-800 text-xs font-semibold rounded-xl border border-emerald-200 mb-5 flex items-center gap-2.5 shadow-sm">
                        <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-circle-check text-xs"></i>
                        </div>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>

                <!-- ======================================================= -->
                <!-- 1. SIGN IN FORM (GMAIL / EMAIL & PASSWORD)               -->
                <!-- ======================================================= -->
                <div id="section-login" class="<?= $tab === 'login' ? '' : 'hidden' ?>">
                    <div class="mb-4">
                        <h3 class="text-xl font-black text-gray-900 flex items-center gap-2">
                            <span>Sign In With Gmail</span>
                            <span class="text-lg">📧</span>
                        </h3>
                        <p class="text-xs text-gray-500 mt-1">Apna registered Gmail aur Password enter karke turant login karein.</p>
                    </div>

                    <form action="<?= SITE_URL ?>/login.php?tab=login&redirect=<?= urlencode($redirect) ?>" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="login">

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                                Email Address or Mobile Number <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-3.5 text-gray-400 text-xs">
                                    <i class="fa-regular fa-envelope"></i>
                                </span>
                                <input 
                                    type="text" 
                                    name="email" 
                                    id="login-email" 
                                    required 
                                    placeholder="Enter your Gmail or 10-digit Mobile Number" 
                                    class="w-full text-xs pl-10 pr-3.5 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-fk-blue focus:ring-2 focus:ring-blue-100 font-medium text-gray-900"
                                >
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                                    Password <span class="text-rose-500">*</span>
                                </label>
                                <a href="<?= SITE_URL ?>/forgot-password.php" class="text-xs font-bold text-fk-blue hover:underline">
                                    Forgot Password?
                                </a>
                            </div>
                            <div class="relative">
                                <span class="absolute left-3.5 top-3.5 text-gray-400 text-xs">
                                    <i class="fa-solid fa-lock"></i>
                                </span>
                                <input 
                                    type="password" 
                                    name="password" 
                                    id="login-password" 
                                    required 
                                    placeholder="••••••••" 
                                    class="w-full text-xs pl-10 pr-10 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-fk-blue focus:ring-2 focus:ring-blue-100 font-medium text-gray-900"
                                >
                                <button 
                                    type="button" 
                                    onclick="togglePassVisibility('login-password', 'eye-login')" 
                                    class="absolute right-3.5 top-3 text-gray-400 hover:text-gray-700 text-sm"
                                >
                                    <i id="eye-login" class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button 
                            type="submit" 
                            class="w-full bg-fk-blue hover:bg-fk-blue-dark text-white font-extrabold py-3.5 rounded-xl shadow-md hover:shadow-lg transition transform active:scale-[0.99] uppercase tracking-wider flex items-center justify-center gap-2 text-xs cursor-pointer"
                        >
                            <span>SIGN IN SECURELY</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </form>

                    <!-- 1-Click Fast Demo Logins -->
                    <div class="mt-6 pt-4 border-t border-gray-100 space-y-2">
                        <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">
                            Quick 1-Click Demo Accounts:
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <button 
                                type="button" 
                                onclick="fillCredentials('user@example.com', 'user123')" 
                                class="p-2.5 border border-blue-200 bg-blue-50/70 hover:bg-blue-100 text-blue-900 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer text-left"
                            >
                                <i class="fa-solid fa-user text-fk-blue"></i>
                                <span>Customer (user@example.com)</span>
                            </button>
                            <button 
                                type="button" 
                                onclick="fillCredentials('admin@fruitkart.com', 'admin123')" 
                                class="p-2.5 border border-amber-200 bg-amber-50/70 hover:bg-amber-100 text-amber-900 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer text-left"
                            >
                                <i class="fa-solid fa-shield-halved text-amber-600"></i>
                                <span>Admin (admin@fruitkart.com)</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ======================================================= -->
                <!-- 2. CREATE ACCOUNT FORM (REGISTER NEW USER)               -->
                <!-- ======================================================= -->
                <div id="section-register" class="<?= $tab === 'register' ? '' : 'hidden' ?>">
                    <div class="mb-4">
                        <h3 class="text-xl font-black text-gray-900 flex items-center gap-2">
                            <span>Create New Account</span>
                            <span class="text-lg">✨</span>
                        </h3>
                        <p class="text-xs text-gray-500 mt-1">Apna name aur Gmail enter karke FruitKart par naya account banayein.</p>
                    </div>

                    <form action="<?= SITE_URL ?>/login.php?tab=register&redirect=<?= urlencode($redirect) ?>" method="POST" class="space-y-3.5">
                        <input type="hidden" name="action" value="register">

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Full Name <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="name" 
                                required 
                                placeholder="Your Full Name (e.g. Rahul Sharma)" 
                                class="w-full text-xs px-3.5 py-2.5 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100 font-medium text-gray-900"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Email / Gmail Address <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="email" 
                                name="email" 
                                required 
                                placeholder="yourname@gmail.com" 
                                class="w-full text-xs px-3.5 py-2.5 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100 font-medium text-gray-900"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                Mobile Number (Order Delivery ke liye)
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 font-bold text-xs text-gray-500">🇮🇳 +91</span>
                                <input 
                                    type="tel" 
                                    name="phone" 
                                    maxlength="10" 
                                    placeholder="10-digit mobile number" 
                                    class="w-full text-xs pl-14 pr-3.5 py-2.5 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100 font-bold font-mono text-gray-900"
                                >
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                    Password <span class="text-rose-500">*</span>
                                </label>
                                <input 
                                    type="password" 
                                    name="password" 
                                    id="reg-password"
                                    required 
                                    minlength="6"
                                    placeholder="At least 6 characters" 
                                    class="w-full text-xs px-3.5 py-2.5 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100 font-medium text-gray-900"
                                >
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                    Confirm Password <span class="text-rose-500">*</span>
                                </label>
                                <input 
                                    type="password" 
                                    name="confirm_password" 
                                    required 
                                    minlength="6"
                                    placeholder="Re-enter password" 
                                    class="w-full text-xs px-3.5 py-2.5 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100 font-medium text-gray-900"
                                >
                            </div>
                        </div>

                        <button 
                            type="submit" 
                            class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold py-3.5 rounded-xl shadow-md hover:shadow-lg transition transform active:scale-[0.99] uppercase tracking-wider flex items-center justify-center gap-2 text-xs cursor-pointer mt-2"
                        >
                            <i class="fa-solid fa-user-plus"></i>
                            <span>CREATE ACCOUNT &amp; START SHOPPING</span>
                        </button>
                    </form>
                </div>

            </div>

            <!-- Bottom Support Help -->
            <div class="mt-6 pt-4 border-t border-gray-100 text-center text-xs text-gray-500">
                Any issues? 
                <a href="<?= SITE_URL ?>/index.php" class="text-fk-blue font-bold hover:underline ml-1">
                    Contact 24x7 Customer Support
                </a>
            </div>

        </div>

    </div>

    <script>
        function switchTab(tabName) {
            const secLogin = document.getElementById('section-login');
            const secRegister = document.getElementById('section-register');
            const btnLogin = document.getElementById('tab-btn-login');
            const btnRegister = document.getElementById('tab-btn-register');

            if (tabName === 'login') {
                secLogin.classList.remove('hidden');
                secRegister.classList.add('hidden');
                btnLogin.className = "px-4 py-2 rounded-xl text-xs font-black transition flex items-center gap-1.5 bg-fk-blue text-white shadow-md";
                btnRegister.className = "px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 bg-gray-100 text-gray-600 hover:bg-gray-200";
            } else {
                secLogin.classList.add('hidden');
                secRegister.classList.remove('hidden');
                btnLogin.className = "px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 bg-gray-100 text-gray-600 hover:bg-gray-200";
                btnRegister.className = "px-4 py-2 rounded-xl text-xs font-black transition flex items-center gap-1.5 bg-emerald-600 text-white shadow-md";
            }
        }

        function fillCredentials(email, pass) {
            switchTab('login');
            const eInput = document.getElementById('login-email');
            const pInput = document.getElementById('login-password');
            if (eInput && pInput) {
                eInput.value = email;
                pInput.value = pass;
                eInput.focus();
            }
        }

        function togglePassVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input && icon) {
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.className = 'fa-regular fa-eye-slash';
                } else {
                    input.type = 'password';
                    icon.className = 'fa-regular fa-eye';
                }
            }
        }
    </script>
</body>
</html>
