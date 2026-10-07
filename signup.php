<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$redirect = $_GET['redirect'] ?? (SITE_URL . '/index.php');

if (isLoggedIn()) {
    header("Location: " . $redirect);
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($name) && !empty($email) && !empty($password)) {
        $db = getDB();
        
        // Check if email already registered
        $check = $db->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'Email is already registered. Please login instead.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'user')");
            $stmt->execute([$name, $email, $phone, $hash]);
            $newId = $db->lastInsertId();

            $_SESSION['user_id'] = $newId;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = 'user';

            // Merge guest cart
            $sessionId = getSessionId();
            $db->prepare("UPDATE cart SET user_id = ? WHERE session_id = ? AND user_id IS NULL")->execute([$newId, $sessionId]);

            setFlash('success', 'Account created successfully! Welcome to FruitKart.');
            header("Location: " . $redirect);
            exit;
        }
    } else {
        $error = 'Please fill all required fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | FruitKart - Fresh Farm Fruits Online</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/custom.css">
</head>
<body class="auth-bg-orchard min-h-screen relative overflow-x-hidden flex items-center justify-center p-4 sm:p-6 selection:bg-emerald-500 selection:text-white">

    <!-- Ambient Glowing Orbs -->
    <div class="fixed top-12 left-10 w-72 h-72 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none animate-pulse"></div>
    <div class="fixed bottom-12 right-10 w-80 h-80 bg-blue-500/20 rounded-full blur-3xl pointer-events-none animate-pulse"></div>

    <!-- Floating Fruit Badges -->
    <div class="hidden lg:flex items-center gap-2 absolute top-8 left-10 bg-white/10 backdrop-blur-md border border-white/20 text-white px-4 py-2 rounded-full text-xs font-bold animate-float shadow-xl">
        <span class="text-base">🎉</span>
        <span class="text-amber-300 font-black">₹50 OFF Welcome Gift</span>
        <span>with code FRESH50</span>
    </div>

    <div class="hidden lg:flex items-center gap-2 absolute top-8 right-10 bg-white/10 backdrop-blur-md border border-white/20 text-white px-4 py-2 rounded-full text-xs font-bold animate-float-delayed shadow-xl">
        <span class="text-base">🚚</span>
        <span class="text-emerald-300 font-extrabold">100% Free Shipping</span>
        <span>Included</span>
    </div>

    <!-- Main Glassmorphic Container -->
    <div class="max-w-4xl w-full auth-glass-card rounded-3xl overflow-hidden shadow-2xl flex flex-col md:flex-row relative z-10 my-4 sm:my-8">
        
        <!-- Left Branding Column -->
        <div class="md:w-5/12 bg-gradient-to-br from-slate-950 via-emerald-950 to-slate-900 text-white p-8 sm:p-10 flex flex-col justify-between relative overflow-hidden">
            
            <div class="absolute -right-16 -top-16 w-48 h-48 bg-emerald-500/20 rounded-full blur-2xl"></div>
            <div class="absolute -left-16 -bottom-16 w-48 h-48 bg-amber-500/20 rounded-full blur-2xl"></div>

            <div class="relative z-10">
                <a href="<?= SITE_URL ?>/index.php" class="inline-flex items-center gap-2 text-2xl font-black italic tracking-wide text-white group mb-6">
                    <span>Fruit<span class="text-amber-400">Kart</span></span>
                    <i class="fa-solid fa-apple-whole text-amber-400 text-xl group-hover:rotate-12 transition-transform"></i>
                </a>

                <div class="inline-flex items-center gap-1.5 bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider mb-3">
                    <i class="fa-solid fa-sparkles text-xs"></i> New Customer Benefits
                </div>

                <h2 class="text-2xl sm:text-3xl font-black leading-tight text-white">
                    Start Your Fresh Fruit Journey.
                </h2>
                
                <p class="text-xs text-emerald-100/80 mt-3 leading-relaxed">
                    Create an account in 30 seconds to unlock exclusive harvest discounts, direct farm deliveries, and express 2-hour doorstep arrival.
                </p>

                <!-- Value Props List -->
                <div class="space-y-2.5 mt-6 text-xs text-gray-200">
                    <div class="flex items-center gap-2.5">
                        <div class="w-6 h-6 rounded-full bg-emerald-500/20 border border-emerald-400/40 text-emerald-400 flex items-center justify-center text-[10px] shrink-0">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <span>Flat ₹50 OFF on first order (Code: FRESH50)</span>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <div class="w-6 h-6 rounded-full bg-emerald-500/20 border border-emerald-400/40 text-emerald-400 flex items-center justify-center text-[10px] shrink-0">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <span>100% Free delivery on all orders</span>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <div class="w-6 h-6 rounded-full bg-emerald-500/20 border border-emerald-400/40 text-emerald-400 flex items-center justify-center text-[10px] shrink-0">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <span>Save favorites to your personal Wishlist</span>
                    </div>
                </div>
            </div>

            <!-- Trust Badge -->
            <div class="relative z-10 pt-6 mt-6 border-t border-white/10">
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-1.5 text-amber-400 font-black">
                        <i class="fa-solid fa-star text-xs"></i>
                        <i class="fa-solid fa-star text-xs"></i>
                        <i class="fa-solid fa-star text-xs"></i>
                        <i class="fa-solid fa-star text-xs"></i>
                        <i class="fa-solid fa-star text-xs"></i>
                        <span class="text-white font-bold ml-1">5.0 Star</span>
                    </div>
                    <span class="text-[11px] text-gray-400">Pure Quality Assured</span>
                </div>
            </div>
        </div>

        <!-- Right Register Form Column -->
        <div class="md:w-7/12 p-7 sm:p-10 flex flex-col justify-between bg-white/95">
            <div>
                <!-- Top Nav / Back to Store -->
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-xl sm:text-2xl font-black text-gray-900 flex items-center gap-2">
                            <span>Create Account</span>
                            <span class="text-xl">✨</span>
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">Sign up to buy orchard-fresh fruits</p>
                    </div>

                    <a href="<?= SITE_URL ?>/index.php" title="Return to Store" class="text-xs font-bold text-gray-400 hover:text-emerald-600 transition flex items-center gap-1.5 bg-gray-100 hover:bg-emerald-50 px-3 py-1.5 rounded-full border border-gray-200">
                        <i class="fa-solid fa-house text-xs"></i>
                        <span>Store</span>
                    </a>
                </div>

                <!-- Error Alert Box -->
                <?php if (!empty($error)): ?>
                    <div class="p-3.5 bg-rose-50 text-rose-700 text-xs font-semibold rounded-xl border border-rose-200 mb-4 flex items-center gap-2.5 shadow-sm">
                        <div class="w-6 h-6 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-circle-exclamation text-xs"></i>
                        </div>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Sign Up Form -->
                <form action="<?= SITE_URL ?>/signup.php?redirect=<?= urlencode($redirect) ?>" method="POST" class="space-y-3.5">
                    
                    <!-- Full Name -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                            Full Name <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-3 text-gray-400 text-xs">
                                <i class="fa-regular fa-user"></i>
                            </span>
                            <input 
                                type="text" 
                                name="name" 
                                required 
                                placeholder="e.g. Rahul Sharma" 
                                class="w-full text-xs pl-9 pr-4 py-2.5 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 font-medium text-gray-900 bg-gray-50/50 hover:bg-white transition"
                            >
                        </div>
                    </div>

                    <!-- Email Address -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                            Email Address <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-3 text-gray-400 text-xs">
                                <i class="fa-regular fa-envelope"></i>
                            </span>
                            <input 
                                type="email" 
                                name="email" 
                                required 
                                placeholder="e.g. rahul@example.com" 
                                class="w-full text-xs pl-9 pr-4 py-2.5 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 font-medium text-gray-900 bg-gray-50/50 hover:bg-white transition"
                            >
                        </div>
                    </div>

                    <!-- Mobile Phone -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                            Mobile Number (Optional)
                        </label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-3 text-gray-400 text-xs">
                                <i class="fa-solid fa-phone"></i>
                            </span>
                            <input 
                                type="tel" 
                                name="phone" 
                                maxlength="10" 
                                placeholder="e.g. 9876543210" 
                                class="w-full text-xs pl-9 pr-4 py-2.5 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 font-medium text-gray-900 bg-gray-50/50 hover:bg-white transition"
                            >
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                            Create Password <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-3 text-gray-400 text-xs">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                            <input 
                                type="password" 
                                name="password" 
                                id="signup-password"
                                required 
                                placeholder="Minimum 6 characters" 
                                class="w-full text-xs pl-9 pr-10 py-2.5 border border-gray-300 rounded-xl focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 font-medium text-gray-900 bg-gray-50/50 hover:bg-white transition"
                            >
                            <button 
                                type="button" 
                                onclick="toggleSignupPassword()" 
                                class="absolute right-3.5 top-2.5 text-gray-400 hover:text-gray-700 text-xs p-0.5"
                                title="Show / Hide Password"
                            >
                                <i id="signup-eye-icon" class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button 
                        type="submit" 
                        class="w-full bg-gradient-to-r from-emerald-600 via-teal-600 to-blue-600 hover:from-emerald-700 hover:to-blue-700 text-white font-black py-3 rounded-xl shadow-lg hover:shadow-xl transition transform active:scale-[0.99] uppercase tracking-wider flex items-center justify-center gap-2 text-xs cursor-pointer mt-2"
                    >
                        <span>Create Account &amp; Continue</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>

            </div>

            <!-- Bottom Switch to Login Link -->
            <div class="mt-5 pt-3 border-t border-gray-100 text-center text-xs text-gray-500">
                Already have an account? 
                <a href="<?= SITE_URL ?>/login.php?redirect=<?= urlencode($redirect) ?>" class="text-emerald-700 font-extrabold hover:text-emerald-800 hover:underline ml-1">
                    Sign In Here &rarr;
                </a>
            </div>

        </div>

    </div>

    <script>
        function toggleSignupPassword() {
            const pass = document.getElementById('signup-password');
            const icon = document.getElementById('signup-eye-icon');
            if (pass.type === 'password') {
                pass.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                pass.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
