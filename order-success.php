<?php
$pageTitle = "Order Placed Successfully";
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$orderId = (int)($_GET['order_id'] ?? 0);
$db = getDB();

$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: " . SITE_URL . "/my-orders.php");
    exit;
}

$itemStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemStmt->execute([$orderId]);
$items = $itemStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Confetti Canvas for Visual Celebration -->
<canvas id="confetti-canvas" class="fixed inset-0 pointer-events-none z-50 w-full h-full"></canvas>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 relative">

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden text-center p-8 sm:p-12 relative">
        
        <?php $isPendingVerification = ($order['payment_status'] === 'Pending Verification' || $order['order_status'] === 'Payment Verification Pending'); ?>

        <?php if ($isPendingVerification): ?>
            <!-- Verification Pending State -->
            <div class="w-24 h-24 mx-auto bg-amber-100 text-amber-600 rounded-full flex items-center justify-center text-4xl mb-6 shadow-inner ring-4 ring-amber-50">
                <i class="fa-solid fa-hourglass-half animate-spin"></i>
            </div>

            <span class="inline-block bg-amber-100 text-amber-900 border border-amber-300 text-xs font-bold uppercase tracking-wider px-3.5 py-1 rounded-full mb-2">
                <i class="fa-solid fa-clock"></i> Bank Credit Verification Pending
            </span>

            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-2">
                Order Submitted &mdash; Awaiting Bank Verification
            </h1>
            
            <p class="text-sm text-gray-600 max-w-lg mx-auto mb-4">
                Aapka order receive ho gaya hai aur aapka 12-digit UPI UTR (<strong class="font-mono text-gray-900"><?= htmlspecialchars($order['transaction_id']) ?></strong>) submit ho chuka hai. Admin ke bank account me credit verify hote hi aapka order Confirm ho jayega!
            </p>

            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3.5 max-w-md mx-auto mb-6 text-xs text-amber-900 text-left flex items-start gap-2.5">
                <i class="fa-solid fa-shield-halved text-amber-600 mt-0.5 text-sm shrink-0"></i>
                <div class="leading-relaxed">
                    <strong>Suraksha Notice:</strong> Fake ya invalid UTR dalne par order confirm nahi hota hai. Real bank credit check hone ke baad order packing shuru hogi.
                </div>
            </div>
        <?php else: ?>
            <!-- Confirmed / Standard State -->
            <div class="w-24 h-24 mx-auto bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-4xl mb-4 shadow-inner animate-bounce">
                <i class="fa-solid fa-check"></i>
            </div>

            <span class="inline-block bg-emerald-100 text-emerald-800 border border-emerald-300 text-xs font-bold uppercase tracking-wider px-3.5 py-1 rounded-full mb-2">
                <i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i> 
                <?= $order['payment_method'] === 'UPI' ? 'Payment Auto-Verified & Paid' : 'Payment ' . htmlspecialchars($order['payment_status']) ?>
            </span>

            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-2">
                <?= $order['order_status'] === 'Confirmed' ? 'Order Confirmed & Payment Received! 🎉' : 'Order Placed Successfully!' ?>
            </h1>
            
            <p class="text-sm text-gray-600 max-w-lg mx-auto mb-4">
                <?php if ($order['payment_method'] === 'UPI'): ?>
                    Aapka ₹<?= number_format($order['final_amount'], 2) ?> ka UPI payment successfully Admin ke Bank Account me credit ho chuka hai. Hamari farm team aapke taaza fruits pack kar rahi hai!
                <?php elseif ($order['payment_method'] === 'CARD'): ?>
                    Aapka ₹<?= number_format($order['final_amount'], 2) ?> ka Card Payment (Bank 3D Secure Verified) successfully receive ho chuka hai. Hamari farm team aapke taaza fruits pack kar rahi hai!
                <?php else: ?>
                    Thank you for shopping with FruitKart! We have received your order and our harvest quality team is handpicking your fresh fruits now.
                <?php endif; ?>
            </p>

            <!-- Sound Celebration Badge & Interactive Replay Button -->
            <div class="mb-6 flex flex-wrap items-center justify-center gap-3">
                <button 
                    type="button" 
                    id="celebration-sound-btn" 
                    onclick="triggerOrderCelebration(true)" 
                    class="inline-flex items-center gap-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-black text-xs px-5 py-2.5 rounded-full shadow-md hover:shadow-lg transition transform hover:scale-105 cursor-pointer ring-4 ring-emerald-100"
                >
                    <i class="fa-solid fa-volume-high text-sm animate-pulse" id="sound-btn-icon"></i>
                    <span id="sound-btn-text">🔔 Play Confirmation Sound (Replay)</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 max-w-md mx-auto mb-8 text-left space-y-2 text-xs">
            <div class="flex justify-between">
                <span class="text-gray-500">Order Reference:</span>
                <strong class="font-mono text-gray-900 text-sm"><?= htmlspecialchars($order['order_number']) ?></strong>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Payment Mode:</span>
                <span class="font-bold text-gray-800"><?= htmlspecialchars($order['payment_method']) ?></span>
            </div>
            <?php if (!empty($order['transaction_id'])): ?>
                <div class="flex justify-between">
                    <span class="text-gray-500">UTR / Ref Number:</span>
                    <span class="font-mono font-bold text-gray-800"><?= htmlspecialchars($order['transaction_id']) ?></span>
                </div>
            <?php endif; ?>
            <div class="flex justify-between">
                <span class="text-gray-500">Estimated Delivery:</span>
                <span class="font-bold text-emerald-700">Today, <?= htmlspecialchars($order['delivery_slot']) ?></span>
            </div>
            <div class="flex justify-between border-t border-gray-200 pt-2 text-sm">
                <span class="font-bold text-gray-700">Amount:</span>
                <span class="font-black text-gray-900"><?= formatPrice($order['final_amount']) ?></span>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center justify-center gap-4 mb-10">
            <a href="<?= SITE_URL ?>/order-tracking.php?order_id=<?= $order['id'] ?>" class="bg-fk-blue hover:bg-fk-blue-dark text-white font-bold text-xs px-6 py-3 rounded-lg shadow-md transition flex items-center gap-2 uppercase tracking-wide">
                <i class="fa-solid fa-location-crosshairs"></i>
                <span>Track Delivery Status</span>
            </a>

            <a href="<?= SITE_URL ?>/order-details.php?id=<?= $order['id'] ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold text-xs px-6 py-3 rounded-lg transition flex items-center gap-2 uppercase tracking-wide">
                <i class="fa-solid fa-receipt"></i>
                <span>View Full Invoice</span>
            </a>

            <a href="<?= SITE_URL ?>/products.php" class="text-xs font-bold text-fk-blue hover:underline py-3 px-4">
                Continue Shopping &rarr;
            </a>
        </div>

        <!-- Items Preview -->
        <div class="border-t border-gray-100 pt-6 text-left">
            <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-4">Ordered Fruits (<?= count($items) ?> items)</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php foreach ($items as $item): ?>
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" class="w-12 h-12 rounded object-cover border border-gray-200">
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-bold text-gray-900 truncate"><?= htmlspecialchars($item['product_name']) ?></div>
                            <div class="text-[11px] text-gray-500">Qty: <?= $item['quantity'] ?> &bull; <?= htmlspecialchars($item['unit']) ?></div>
                        </div>
                        <div class="text-xs font-bold text-gray-900">
                            <?= formatPrice($item['total_price']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

</div>

<!-- Sound & Confetti Celebration Engine -->
<script>
    // Audio Chime Synthesis (Direct Inline Fallback to ensure 0ms latency and 100% reliability)
    function playCelebrationAudio() {
        try {
            if (typeof window.playOrderCelebrationChime === 'function') {
                window.playOrderCelebrationChime();
                return;
            }

            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            if (ctx.state === 'suspended') ctx.resume();

            const now = ctx.currentTime;
            const notes = [
                { freq: 523.25, time: 0.00, dur: 0.35, gain: 0.40 },  // C5
                { freq: 659.25, time: 0.11, dur: 0.38, gain: 0.45 },  // E5
                { freq: 783.99, time: 0.22, dur: 0.42, gain: 0.50 },  // G5
                { freq: 1046.50, time: 0.34, dur: 1.20, gain: 0.65 }, // C6
                { freq: 1318.51, time: 0.35, dur: 1.00, gain: 0.25 }, // E6
                { freq: 1567.98, time: 0.36, dur: 0.90, gain: 0.20 }  // G6
            ];

            notes.forEach(n => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(n.freq, now + n.time);

                gain.gain.setValueAtTime(0.0001, now + n.time);
                gain.gain.exponentialRampToValueAtTime(n.gain, now + n.time + 0.015);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + n.time + n.dur);

                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now + n.time);
                osc.stop(now + n.time + n.dur);
            });
        } catch (err) {
            console.warn('Audio celebration error:', err);
        }
    }

    // Interactive Trigger Handler
    function triggerOrderCelebration(isManual = false) {
        playCelebrationAudio();
        fireConfettiBurst();

        const btnText = document.getElementById('sound-btn-text');
        const btnIcon = document.getElementById('sound-btn-icon');
        if (btnText && btnIcon) {
            btnText.innerHTML = 'Playing Celebration Chime... 🎶';
            btnIcon.className = 'fa-solid fa-music text-sm animate-bounce text-yellow-300';
            setTimeout(() => {
                btnText.innerHTML = '🔔 Play Confirmation Sound (Replay)';
                btnIcon.className = 'fa-solid fa-volume-high text-sm animate-pulse';
            }, 1600);
        }
    }

    // Colorful Confetti Burst Engine
    function fireConfettiBurst() {
        const canvas = document.getElementById('confetti-canvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const colors = ['#2874f0', '#ffe500', '#10b981', '#fb641b', '#ec4899', '#8b5cf6', '#3b82f6'];
        const particles = [];
        const count = 90;

        for (let i = 0; i < count; i++) {
            particles.push({
                x: canvas.width / 2 + (Math.random() * 200 - 100),
                y: canvas.height * 0.35 + (Math.random() * 80 - 40),
                vx: (Math.random() - 0.5) * 14,
                vy: Math.random() * -12 - 4,
                size: Math.random() * 8 + 4,
                color: colors[Math.floor(Math.random() * colors.length)],
                rotation: Math.random() * 360,
                rotationSpeed: (Math.random() - 0.5) * 10,
                gravity: 0.38,
                opacity: 1
            });
        }

        let frame = 0;
        function render() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            let active = false;

            particles.forEach(p => {
                p.x += p.vx;
                p.y += p.vy;
                p.vy += p.gravity;
                p.rotation += p.rotationSpeed;
                p.vx *= 0.98;

                if (frame > 35) {
                    p.opacity -= 0.015;
                }

                if (p.opacity > 0 && p.y < canvas.height + 50) {
                    active = true;
                    ctx.save();
                    ctx.translate(p.x, p.y);
                    ctx.rotate((p.rotation * Math.PI) / 180);
                    ctx.globalAlpha = Math.max(0, p.opacity);
                    ctx.fillStyle = p.color;
                    ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
                    ctx.restore();
                }
            });

            frame++;
            if (active && frame < 180) {
                requestAnimationFrame(render);
            } else {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            }
        }
        render();
    }

    // Auto-play Management with browser policy fallbacks
    let autoPlayed = false;
    function attemptCelebrationAutoplay() {
        if (autoPlayed) return;
        autoPlayed = true;
        triggerOrderCelebration(false);
    }

    // Try on load
    window.addEventListener('load', () => {
        setTimeout(attemptCelebrationAutoplay, 350);
    });

    // Fallback: If autoplay was blocked by browser security policy, play on first user interaction
    ['click', 'touchstart', 'keydown'].forEach(evt => {
        document.addEventListener(evt, () => {
            if (!autoPlayed) {
                attemptCelebrationAutoplay();
            }
        }, { once: true });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
