<?php
require_once 'config/db.php';

// حساب الإحصائيات الفعليّة من قاعدة البيانات
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$lowStock = $pdo->query("SELECT COUNT(*) FROM products WHERE quantity > 0 AND quantity <= min_stock_level")->fetchColumn();
$outOfStock = $pdo->query("SELECT COUNT(*) FROM products WHERE quantity = 0")->fetchColumn();
$inStock = $pdo->query("SELECT COUNT(*) FROM products WHERE quantity > min_stock_level")->fetchColumn();

// تجنب القسمة على صفر في شريط التقدم
$stockPercentage = ($totalProducts > 0) ? round(($inStock / $totalProducts) * 100) : 0;

include_once 'includes/header.php';
?>

<div id="pageLoader" class="fixed inset-0 bg-[#060913] z-50 flex flex-col items-center justify-center transition-opacity duration-700">
    <div class="relative mb-6">
        <div class="absolute inset-0 rounded-3xl blur-2xl bg-cyan-400/50 animate-pulse"></div>
        <div class="relative w-20 h-20 rounded-3xl bg-gradient-to-br from-cyan-400 to-blue-600 flex items-center justify-center shadow-[0_0_50px_rgba(34,211,238,.6)]">
            <i class="fa-solid fa-mug-hot text-4xl text-white animate-bounce"></i>
        </div>
    </div>
    <h1 class="text-white font-black tracking-widest text-lg mb-2">PLASTO<span class="text-cyan-400">CUP</span> OS</h1>
    <p class="text-xs text-slate-500 uppercase tracking-widest mb-6">جارٍ تهيئة لوحة القيادة وتحليل المخزون...</p>
    <div class="w-48 h-1.5 bg-slate-800 rounded-full overflow-hidden">
        <div id="loaderBar" class="h-full bg-gradient-to-r from-cyan-400 to-blue-500 transition-all duration-300 w-0"></div>
    </div>
</div>

<button id="scrollToTopBtn" onclick="scrollToTop()" class="fixed bottom-6 left-6 w-11 h-11 bg-cyan-500/20 hover:bg-cyan-500/40 border border-cyan-500/30 text-cyan-400 rounded-2xl flex items-center justify-center shadow-lg transition-all opacity-0 pointer-events-none z-30 backdrop-blur-xl">
    <i class="fa-solid fa-arrow-up"></i>
</button>

<div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
    <div class="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-slate-950 via-slate-900 to-black"></div>
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-cyan-500/20 blur-[140px] rounded-full animate-pulse"></div>
    <div class="absolute bottom-0 right-0 w-96 h-96 bg-purple-500/20 blur-[150px] rounded-full animate-pulse"></div>
</div>
<div id="particles" class="fixed inset-0 -z-10 pointer-events-none"></div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/tsparticles@3/tsparticles.bundle.min.js"></script>

<style>
.dashboard {
    opacity: 0;
    transform: translateY(40px);
    animation: show 0.8s forwards;
}
@keyframes show {
    to {
        opacity: 1;
        transform: none;
    }
}
.card {
    transform-style: preserve-3d;
    transition: transform 0.4s ease, box-shadow 0.4s ease;
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 40px rgba(0, 255, 255, 0.05), inset 0 0 20px rgba(255, 255, 255, 0.03);
}
.card:hover {
    transform: rotateX(6deg) rotateY(-6deg) scale(1.02);
    box-shadow: 0 0 20px #06b6d4, 0 0 40px rgba(6, 182, 212, 0.3), 0 0 60px rgba(6, 182, 212, 0.1);
}
</style>

<div class="dashboard space-y-8 pb-16">
    <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-white tracking-tight">لوحة القيادة الرئيسية</h2>
            <p class="text-gray-400 mt-1">نظرة عامة على حالة المستودع والمؤشرات الحيوية لخط الإنتاج في الوقت الفعلي.</p>
        </div>
        <div class="flex items-center space-x-4 space-x-reverse">
            <div id="clock" class="text-cyan-400 text-lg font-bold px-3 py-1 bg-white/5 rounded-lg border border-cyan-500/10 shadow-inner">--:--:--</div>
            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                <span class="w-2 h-2 ml-1.5 rounded-full bg-emerald-400 animate-pulse"></span> النظام متصل بالبيانات
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="card p-6 rounded-2xl relative overflow-hidden group">
            <i class="fa-solid fa-boxes-stacked text-5xl text-cyan-400 opacity-20 absolute top-5 left-5 pointer-events-none"></i>
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">إجمالي المنتجات</h3>
            <p class="counter text-5xl font-black text-cyan-400 mt-3" data-target="<?= $totalProducts ?>">0</p>
            <div class="mt-6">
                <div class="flex justify-between text-xs text-gray-400 mb-1">
                    <span>حالة المخزون المتوفر</span>
                    <span class="text-cyan-400 font-semibold"><?= $stockPercentage ?>%</span>
                </div>
                <div class="w-full h-2.5 bg-slate-800/80 rounded-full overflow-hidden border border-slate-700/50">
                    <div class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-green-400 transition-all duration-1000" style="width: <?= $stockPercentage ?>%"></div>
                </div>
            </div>
        </div>
        
        <div class="card p-6 rounded-2xl relative overflow-hidden group">
            <i class="fa-solid fa-triangle-exclamation text-5xl text-amber-500 opacity-20 absolute top-5 left-5 pointer-events-none"></i>
            <h3 class="text-xs font-bold uppercase tracking-wider text-amber-500">منتجات منخفضة المخزون</h3>
            <p class="counter text-5xl font-black text-amber-500 mt-3" data-target="<?= $lowStock ?>">0</p>
            <div class="mt-6">
                <div class="flex justify-between text-xs text-gray-400 mb-1">
                    <span>تنبيه الحد الأدنى</span>
                    <span class="text-amber-400 font-semibold"><?= ($totalProducts > 0) ? round(($lowStock / $totalProducts) * 100) : 0 ?>%</span>
                </div>
                <div class="w-full h-2.5 bg-slate-800/80 rounded-full overflow-hidden border border-slate-700/50">
                    <div class="h-full rounded-full bg-gradient-to-r from-amber-500 to-yellow-300 transition-all duration-1000" style="width: <?= ($totalProducts > 0) ? round(($lowStock / $totalProducts) * 100) : 0 ?>%"></div>
                </div>
            </div>
        </div>
        
        <div class="card p-6 rounded-2xl relative overflow-hidden group">
            <i class="fa-solid fa-circle-xmark text-5xl text-rose-500 opacity-20 absolute top-5 left-5 pointer-events-none"></i>
            <h3 class="text-xs font-bold uppercase tracking-wider text-rose-500">منتجات نافدة</h3>
            <p class="counter text-5xl font-black text-rose-500 mt-3" data-target="<?= $outOfStock ?>">0</p>
            <div class="mt-6">
                <div class="flex justify-between text-xs text-gray-400 mb-1">
                    <span>نسبة العجز</span>
                    <span class="text-rose-400 font-semibold"><?= ($totalProducts > 0) ? round(($outOfStock / $totalProducts) * 100) : 0 ?>%</span>
                </div>
                <div class="w-full h-2.5 bg-slate-800/80 rounded-full overflow-hidden border border-slate-700/50">
                    <div class="h-full rounded-full bg-gradient-to-r from-rose-500 to-red-400 transition-all duration-1000" style="width: <?= ($totalProducts > 0) ? round(($outOfStock / $totalProducts) * 100) : 0 ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="card p-6 rounded-2xl flex flex-col justify-between">
            <h3 class="text-base font-bold text-white mb-4">توزيع حالات المخزون</h3>
            <div class="relative w-full h-64 flex justify-center items-center">
                <canvas id="stockStatusChart"></canvas>
            </div>
        </div>
        
        <div class="card p-6 rounded-2xl flex flex-col justify-between">
            <h3 class="text-base font-bold text-white mb-4">مقارنة الكميات الحيوية</h3>
            <div class="relative w-full h-64 flex justify-center items-center">
                <canvas id="stockBarChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// شاشة التحميل التفاعلية Preloader
window.addEventListener('load', () => {
    let bar = document.getElementById('loaderBar');
    if (bar) bar.style.width = '100%';
    setTimeout(() => {
        let loader = document.getElementById('pageLoader');
        if (loader) {
            loader.style.opacity = '0';
            setTimeout(() => loader.remove(), 700);
        }
    }, 400);
});

// زر الصعود لأعلى الصفحة وإظهاره عند النزول
window.onscroll = function() {
    let btn = document.getElementById('scrollToTopBtn');
    if (btn) {
        if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
            btn.style.opacity = '1';
            btn.style.pointerEvents = 'auto';
        } else {
            btn.style.opacity = '0';
            btn.style.pointerEvents = 'none';
        }
    }
};
function scrollToTop() {
    window.scrollTo({top: 0, behavior: 'smooth'});
}

// سابعاً: الساعة المباشرة
setInterval(() => {
    let clockEl = document.getElementById("clock");
    if (clockEl) clockEl.innerHTML = new Date().toLocaleTimeString();
}, 1000);

// ثانياً: عداد يتحرك للارقام
document.querySelectorAll(".counter").forEach(counter => {
    let target = +counter.dataset.target;
    let count = 0;
    let step = target / 60;
    let update = () => {
        count += step;
        if (count < target) {
            counter.innerHTML = Math.floor(count);
            requestAnimationFrame(update);
        } else {
            counter.innerHTML = target.toLocaleString();
        }
    };
    update();
});

// ثاني عشر: جسيمات بالخلفية (Particles)
if (typeof tsParticles !== 'undefined') {
    tsParticles.load("particles", {
        particles: {
            number: { value: 60 },
            color: { value: "#06b6d4" },
            move: { enable: true, speed: 0.8 },
            links: {
                enable: true,
                color: "#06b6d4",
                opacity: 0.15
            },
            size: { value: 2 }
        }
    });
}

// ثامناً: إعدادات الرسوم البيانية الاحترافية
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    animation: {
        duration: 2500,
        easing: 'easeOutBounce'
    },
    plugins: {
        legend: {
            labels: {
                color: "#fff",
                font: { size: 14 }
            }
        }
    }
};

// رسم المخطط الدائري
const ctxPie = document.getElementById('stockStatusChart').getContext('2d');
new Chart(ctxPie, {
    type: 'doughnut',
    data: {
        labels: ['متوفر بأمان', 'منخفض', 'نافد'],
        datasets: [{
            data: [<?= $inStock ?>, <?= $lowStock ?>, <?= $outOfStock ?>],
            backgroundColor: ['#10b981', '#f59e0b', '#f43f5e'],
            borderWidth: 0,
            hoverOffset: 6
        }]
    },
    options: {
        ...chartOptions,
        plugins: {
            ...chartOptions.plugins,
            legend: { position: 'bottom', labels: { color: '#94a3b8' } }
        }
    }
});

// رسم المخطط العمودي
const ctxBar = document.getElementById('stockBarChart').getContext('2d');
new Chart(ctxBar, {
    type: 'bar',
    data: {
        labels: ['إجمالي الأصناف', 'منخفضة', 'نافدة'],
        datasets: [{
            label: 'عدد المنتجات',
            data: [<?= $totalProducts ?>, <?= $lowStock ?>, <?= $outOfStock ?>],
            backgroundColor: ['#06b6d4', '#f59e0b', '#f43f5e'],
            borderRadius: 6
        }]
    },
    options: {
        ...chartOptions,
        scales: {
            x: { ticks: { color: '#94a3b8' }, grid: { display: false } },
            y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(51, 65, 85, 0.2)' } }
        },
        plugins: { legend: { display: false } }
    }
});
</script>

<?php include_once 'includes/footer.php'; ?>