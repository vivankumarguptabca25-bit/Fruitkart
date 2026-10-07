<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Detect computer local IP address
$hostName = gethostname();
$localIp = gethostbyname($hostName);

// If accessed via non-localhost IP in browser
if (isset($_SERVER['HTTP_HOST']) && !in_array(explode(':', $_SERVER['HTTP_HOST'])[0], ['localhost', '127.0.0.1'])) {
    $localIp = explode(':', $_SERVER['HTTP_HOST'])[0];
} elseif (isset($_GET['ip']) && !empty($_GET['ip'])) {
    $localIp = trim($_GET['ip']);
} elseif ($localIp === '127.0.0.1' || empty($localIp)) {
    $localIp = $_SERVER['SERVER_ADDR'] ?? '192.168.1.10';
}

$mobileUrl = "http://" . $localIp . "/Fruit%20Shopping%20App";
$adminUrl = "http://" . $localIp . "/Fruit%20Shopping%20App/admin/index.php";
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=" . urlencode($mobileUrl);
$adminQrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=" . urlencode($adminUrl);

$pageTitle = "Open FruitKart & Admin on Mobile Phone - QR Code & Direct Link";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 py-8">

    <!-- Hero Card with QR Code & Tabs -->
    <div class="bg-gradient-to-br from-blue-900 via-fk-blue to-indigo-900 rounded-3xl p-6 sm:p-10 text-white shadow-2xl relative overflow-hidden mb-8">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-8">
            <div class="space-y-4 text-center md:text-left flex-1">
                <span class="inline-flex items-center gap-1.5 bg-white/20 text-white border border-white/30 px-3.5 py-1 rounded-full text-xs font-black uppercase tracking-wider">
                    <i class="fa-solid fa-mobile-screen-button text-amber-300"></i>
                    <span>Mobile Access &amp; Admin Guide</span>
                </span>
                <h1 class="text-2xl sm:text-4xl font-black text-white leading-tight">
                    Phone me Website Aur Admin Kaise Kholein 📲
                </h1>
                <p class="text-xs sm:text-sm text-blue-100/90 leading-relaxed max-w-lg">
                    Phone me <strong>localhost</strong> nahi likhte, laptop ka Wi-Fi IP address use hota hai. Neeche diye gaye QR code ko apne phone camera ya Google Lens se scan karein aur website ya admin turant open ho jayega!
                </p>

                <!-- Mode Switch Tabs -->
                <div class="flex items-center justify-center md:justify-start gap-2 pt-1">
                    <button 
                        type="button" 
                        onclick="switchQrMode('store')" 
                        id="tab-btn-store" 
                        class="px-4 py-2 rounded-xl text-xs font-black transition flex items-center gap-2 bg-white text-fk-blue shadow-lg"
                    >
                        <i class="fa-solid fa-store"></i>
                        <span>Customer Store</span>
                    </button>
                    <button 
                        type="button" 
                        onclick="switchQrMode('admin')" 
                        id="tab-btn-admin" 
                        class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 bg-white/20 hover:bg-white/30 text-white border border-white/20"
                    >
                        <i class="fa-solid fa-shield-halved text-amber-300"></i>
                        <span>Admin Panel</span>
                    </button>
                </div>
                
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-2 pt-2">
                    <span class="inline-flex items-center gap-1.5 bg-emerald-500/30 text-emerald-200 border border-emerald-400/30 px-3 py-1 rounded-lg text-xs font-bold">
                        <i class="fa-solid fa-wifi text-emerald-300"></i> Same Wi-Fi / Hotspot
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-amber-500/30 text-amber-200 border border-amber-400/30 px-3 py-1 rounded-lg text-xs font-bold">
                        <i class="fa-solid fa-bolt text-amber-300"></i> 1-Scan Direct Login
                    </span>
                    <span class="inline-flex items-center gap-1.5 bg-purple-500/30 text-purple-200 border border-purple-400/30 px-3 py-1 rounded-lg text-xs font-bold">
                        <i class="fa-solid fa-shield-halved text-purple-300"></i> Responsive Admin Drawer
                    </span>
                </div>
            </div>

            <!-- Big QR Code Card -->
            <div class="bg-white p-5 rounded-2xl shadow-2xl text-center shrink-0 border-4 border-white/20">
                <img 
                    id="mobile-qr-image"
                    src="<?= $qrCodeUrl ?>" 
                    alt="Mobile Scan QR Code" 
                    class="w-52 h-52 mx-auto rounded-xl shadow-inner border border-gray-100 transition-all duration-300"
                >
                <div class="mt-3 text-xs font-black text-gray-800 uppercase tracking-wider flex items-center justify-center gap-1.5" id="qr-label-title">
                    <i class="fa-solid fa-qrcode text-fk-blue text-sm"></i>
                    <span>Customer Store QR</span>
                </div>
                <div class="mt-1 text-[10px] text-gray-500 font-medium" id="qr-label-subtitle">
                    (Google Lens, Paytm ya Camera se scan karein)
                </div>
            </div>
        </div>
    </div>

    <!-- Step by Step Instructions -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
        
        <!-- Step 1 -->
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-3 hover:shadow-md transition">
            <div class="w-10 h-10 rounded-xl bg-blue-100 text-fk-blue flex items-center justify-center font-black text-base shadow-xs">
                1
            </div>
            <h3 class="text-sm font-black text-gray-900">Same Wi-Fi Ya Hotspot</h3>
            <p class="text-xs text-gray-600 leading-relaxed">
                Aapka <strong>Laptop</strong> aur <strong>Phone</strong> dono ek hi Wi-Fi network se connect hone chahiye.
                <br><br>
                <span class="text-emerald-700 font-medium bg-emerald-50 px-2 py-1 rounded border border-emerald-200 block">
                    💡 <strong>Tip:</strong> Phone ka <strong>Hotspot</strong> on karein aur Laptop ko usse connect karein. Yeh sabse aasan tarika hai!
                </span>
            </p>
        </div>

        <!-- Step 2 -->
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-3 hover:shadow-md transition">
            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center font-black text-base shadow-xs">
                2
            </div>
            <h3 class="text-sm font-black text-gray-900">XAMPP Apache Running</h3>
            <p class="text-xs text-gray-600 leading-relaxed">
                Laptop me <strong>XAMPP Control Panel</strong> khula hona chahiye aur usme <strong>Apache</strong> aur <strong>MySQL</strong> dono Start (Green) hone chahiye.
                <br><br>
                <span class="text-amber-800 font-medium bg-amber-50 px-2 py-1 rounded border border-amber-200 block">
                    🟢 Apache: Port 80, 443<br>
                    🟢 MySQL: Port 3306
                </span>
            </p>
        </div>

        <!-- Step 3 -->
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-3 hover:shadow-md transition">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-black text-base shadow-xs">
                3
            </div>
            <h3 class="text-sm font-black text-gray-900">Phone Me Open Karein</h3>
            <p class="text-xs text-gray-600 leading-relaxed">
                Phone ke <strong>Camera / Google Lens</strong> se QR code scan karein, ya Phone ke <strong>Chrome / Safari</strong> browser me neeche diya gaya direct URL daalein.
                <br><br>
                <span class="text-blue-800 font-medium bg-blue-50 px-2 py-1 rounded border border-blue-200 block">
                    📱 Mobile view responsive layout aur bottom navigation automatically enable ho jayega!
                </span>
            </p>
        </div>

    </div>

    <!-- Direct URL Box to Copy and IP Adjuster -->
    <div class="bg-white rounded-2xl p-6 border-2 border-emerald-500/40 shadow-sm space-y-5 mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-gray-100">
            <div>
                <span class="text-[10px] font-black uppercase text-emerald-600 tracking-wider">Direct Mobile Link</span>
                <h3 class="text-base font-black text-gray-900">Phone ke Browser me yeh URL dalein:</h3>
            </div>
            <span class="text-xs bg-emerald-100 text-emerald-800 font-bold px-3 py-1 rounded-full w-fit">
                🟢 Live Ready
            </span>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-2">
            <input 
                type="text" 
                id="phone-url-input" 
                readonly 
                value="<?= htmlspecialchars($mobileUrl) ?>" 
                class="w-full p-3.5 bg-gray-50 border border-gray-200 rounded-xl font-mono font-bold text-sm sm:text-base text-gray-900 select-all focus:outline-none"
            >
            <button 
                type="button" 
                onclick="copyMobileUrl()" 
                id="copy-url-btn"
                class="w-full sm:w-auto px-6 py-3.5 bg-fk-blue hover:bg-fk-blue-dark text-white font-black text-xs rounded-xl shadow transition shrink-0 uppercase tracking-wider flex items-center justify-center gap-2 cursor-pointer"
            >
                <i class="fa-regular fa-copy"></i>
                <span id="copy-btn-text">Copy URL</span>
            </button>
        </div>

        <!-- Admin Credentials Card (Highlighted for easy reference) -->
        <div id="admin-info-box" class="p-4 bg-amber-500/10 border-2 border-amber-400 rounded-xl space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs font-black text-amber-900 uppercase tracking-wider flex items-center gap-1.5">
                    <i class="fa-solid fa-shield-halved text-amber-600"></i>
                    <span>Admin Login Credentials:</span>
                </span>
                <span class="text-[10px] bg-amber-200 text-amber-900 px-2 py-0.5 rounded font-bold">1-Click Auto</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                <div class="bg-white p-2.5 rounded-lg border border-amber-200">
                    <span class="text-gray-400 text-[10px] block font-bold">ADMIN EMAIL</span>
                    <strong class="text-gray-900 font-mono">admin@fruitkart.com</strong>
                </div>
                <div class="bg-white p-2.5 rounded-lg border border-amber-200">
                    <span class="text-gray-400 text-[10px] block font-bold">ADMIN PASSWORD</span>
                    <strong class="text-gray-900 font-mono">admin123</strong>
                </div>
            </div>
        </div>

        <!-- IP Customizer / Tester -->
        <div class="p-4 bg-gray-50 rounded-xl border border-gray-200 space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <span class="text-xs font-bold text-gray-700 flex items-center gap-1.5">
                    <i class="fa-solid fa-network-wired text-fk-blue"></i>
                    Laptop IP Address Change Karein (Agar Auto-detect alag ho):
                </span>
                <span class="text-[11px] text-gray-500 font-mono">
                    Auto-detected IP: <strong><?= htmlspecialchars($localIp) ?></strong>
                </span>
            </div>

            <div class="flex items-center gap-2">
                <input 
                    type="text" 
                    id="ip-address-input" 
                    value="<?= htmlspecialchars($localIp) ?>" 
                    placeholder="e.g. 192.168.1.15" 
                    oninput="refreshCurrentUrl()"
                    class="p-2.5 bg-white border border-gray-300 rounded-lg font-mono text-sm text-gray-800 w-full max-w-xs focus:ring-2 focus:ring-fk-blue focus:outline-none"
                >
                <button 
                    type="button" 
                    onclick="refreshCurrentUrl()" 
                    class="px-4 py-2.5 bg-gray-800 hover:bg-black text-white text-xs font-bold rounded-lg transition"
                >
                    Update QR Code
                </button>
            </div>
            <p class="text-[11px] text-gray-500">
                Laptop ka IP dekhne ke liye: Press <code>Windows + R</code> &rarr; type <code>cmd</code> &rarr; press Enter &rarr; type <code>ipconfig</code> &rarr; <strong>IPv4 Address</strong> copy karein.
            </p>
        </div>

        <!-- IP Troubleshooting Note -->
        <div class="p-4 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900 space-y-2">
            <div class="font-bold flex items-center gap-1.5 text-amber-800">
                <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
                <span>Phone me "localhost" kyu nahi chalta?</span>
            </div>
            <p class="text-[11px] leading-relaxed text-amber-950">
                Jab aap phone ke browser me <code>localhost</code> likhte hain, toh phone aapke laptop ko nahi balki <strong>apne aap (phone)</strong> ko dhoondhta hai. Isliye phone par hamesha laptop ka <strong>Wi-Fi IP Address (<?= htmlspecialchars($localIp) ?>)</strong> likhna hota hai!
            </p>
            <ol class="list-decimal list-inside text-[11px] text-amber-900/90 space-y-1 pl-1">
                <li><strong>Hotspot Tip:</strong> Phone ka <strong>Mobile Hotspot ON karein</strong> aur laptop ko connect karein. Dono turant connect ho jayenge.</li>
                <li><strong>Firewall Check:</strong> Agar tab bhi na khule, to Windows Defender Firewall me <strong>Apache HTTP Server</strong> ko allow karein.</li>
            </ol>
        </div>
    </div>

</div>

<script>
let currentMode = 'store';

function switchQrMode(mode) {
    currentMode = mode;
    const storeBtn = document.getElementById('tab-btn-store');
    const adminBtn = document.getElementById('tab-btn-admin');
    const qrTitle = document.getElementById('qr-label-title');
    const qrSubtitle = document.getElementById('qr-label-subtitle');

    if (mode === 'admin') {
        adminBtn.className = 'px-4 py-2 rounded-xl text-xs font-black transition flex items-center gap-2 bg-amber-400 text-slate-950 shadow-lg';
        storeBtn.className = 'px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 bg-white/20 hover:bg-white/30 text-white border border-white/20';
        if (qrTitle) qrTitle.innerHTML = `<i class="fa-solid fa-shield-halved text-amber-500 text-sm"></i><span>Admin Panel QR</span>`;
        if (qrSubtitle) qrSubtitle.innerText = '(Scan karke seedha Admin Dashboard kholein)';
    } else {
        storeBtn.className = 'px-4 py-2 rounded-xl text-xs font-black transition flex items-center gap-2 bg-white text-fk-blue shadow-lg';
        adminBtn.className = 'px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 bg-white/20 hover:bg-white/30 text-white border border-white/20';
        if (qrTitle) qrTitle.innerHTML = `<i class="fa-solid fa-qrcode text-fk-blue text-sm"></i><span>Customer Store QR</span>`;
        if (qrSubtitle) qrSubtitle.innerText = '(Google Lens, Paytm ya Camera se scan karein)';
    }

    refreshCurrentUrl();
}

function refreshCurrentUrl() {
    const ipInput = document.getElementById('ip-address-input');
    let ip = ipInput ? ipInput.value.trim() : '<?= $localIp ?>';
    if (!ip) ip = '192.168.1.10';

    let targetUrl = currentMode === 'admin' 
        ? `http://${ip}/Fruit%20Shopping%20App/admin/index.php` 
        : `http://${ip}/Fruit%20Shopping%20App`;

    const qrImg = document.getElementById('mobile-qr-image');
    const urlInput = document.getElementById('phone-url-input');
    
    if (urlInput) urlInput.value = targetUrl;
    if (qrImg) qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=${encodeURIComponent(targetUrl)}`;
}

function copyMobileUrl() {
    const input = document.getElementById('phone-url-input');
    const btnText = document.getElementById('copy-btn-text');
    if (input) {
        input.select();
        navigator.clipboard.writeText(input.value);
        if (btnText) btnText.innerText = 'Copied! ✓';
        setTimeout(() => {
            if (btnText) btnText.innerText = 'Copy URL';
        }, 2000);
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

