<?php
require_once 'config/db.php';

// تأكد من بدء الجلسة إذا كنت تخزن اسم المستخدم في الـ Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$msg_type = 'success';

// معالجة تسجيل حركة مخزونية جديدة (إرسال، استلام، مرتجع)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_transaction'])) {
    $product_id = intval($_POST['product_id']);
    $type = trim($_POST['type']); // in, out, return
    $quantity = intval($_POST['quantity']);
    $notes = trim($_POST['notes']);
    $user_name = trim($_POST['user_name']) ?: ($_SESSION['user_name'] ?? 'مسؤول المستودع');
    $user_id = 1; // رقم افتراضي للمستخدم (يمكن ربطه بجلسة تسجيل الدخول الحالية)

    if ($product_id > 0 && $quantity > 0) {
        try {
            $pdo->beginTransaction();

            // التحقق من الكمية الحالية للمنتج لمعالجة الصادر ومنع العجز (Phantom Shipments)
            $stmt_prod = $pdo->prepare("SELECT quantity, name FROM products WHERE id = ? FOR UPDATE");
            $stmt_prod->execute([$product_id]);
            $prod = $stmt_prod->fetch(PDO::FETCH_ASSOC);

            if (!$prod) {
                throw new Exception("الصنف غير موجود في قاعدة البيانات.");
            }

            $current_qty = intval($prod['quantity']);
            $new_qty = $current_qty;

            if ($type === 'out') {
                if ($quantity > $current_qty) {
                    throw new Exception("الكمية المطلوبة للصادر ($quantity) أكبر من المتوفر الحالي بالمستودع ($current_qty).");
                }
                $new_qty = $current_qty - $quantity;
            } elseif ($type === 'in' || $type === 'return') {
                $new_qty = $current_qty + $quantity;
            }

            // تحديث رصيد المخزون في جدول المنتجات
            $stmt_update = $pdo->prepare("UPDATE products SET quantity = ?, updated_at = NOW() WHERE id = ?");
            $stmt_update->execute([$new_qty, $product_id]);

            // تسجيل الحركة في جدول الحركات المخزونية (باستخدام الأعمدة الصحيحة quantity_changed و user_id)
            $stmt_insert = $pdo->prepare("INSERT INTO inventory_transactions (product_id, user_id, type, quantity_changed, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt_insert->execute([$product_id, $user_id, $type, $quantity, $notes]);

            $pdo->commit();
            $message = "تم تسجيل حركة المخزون وتحديث رصيد الصنف بنجاح!";
            $msg_type = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "خطأ في المعالجة: " . $e->getMessage();
            $msg_type = 'error';
        }
    } else {
        $message = "الرجاء اختيار الصنف وتحديد كمية صحيحة أكبر من الصفر.";
        $msg_type = 'error';
    }
}

// جلب قائمة المنتجات لاختيارها في نموذج إضافة الحركة
$products_stmt = $pdo->query("SELECT id, name, sku, quantity FROM products ORDER BY name ASC");
$all_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب سجل الحركات المخزونية مع ربطها بأسماء الأصناف
$trans_stmt = $pdo->query("
    SELECT t.*, p.name as product_name, p.sku as product_sku, u.name as user_name 
    FROM inventory_transactions t 
    LEFT JOIN products p ON t.product_id = p.id 
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY t.id DESC
");
$transactions = $trans_stmt->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات سريعة للحركات
$total_trans = count($transactions);
$total_in = count(array_filter($transactions, fn($t) => $t['type'] === 'in'));
$total_out = count(array_filter($transactions, fn($t) => $t['type'] === 'out'));
$total_returns = count(array_filter($transactions, fn($t) => $t['type'] === 'return'));

include_once 'includes/header.php';
?>

<div id="pageLoader" class="fixed inset-0 bg-[#060913] z-50 flex flex-col items-center justify-center transition-opacity duration-700">
    <div class="relative mb-6">
        <div class="absolute inset-0 rounded-3xl blur-2xl bg-cyan-400/50 animate-pulse"></div>
        <div class="relative w-20 h-20 rounded-3xl bg-gradient-to-br from-cyan-400 to-blue-600 flex items-center justify-center shadow-[0_0_50px_rgba(34,211,238,.6)]">
            <i class="fa-solid fa-right-left text-4xl text-white animate-spin"></i>
        </div>
    </div>
    <h1 class="text-white font-black tracking-widest text-lg mb-2">PLASTO<span class="text-cyan-400">CUP</span> OS</h1>
    <p class="text-xs text-slate-500 uppercase tracking-widest mb-6">جارٍ مزامنة سجل حركات المستودع...</p>
    <div class="w-48 h-1.5 bg-slate-800 rounded-full overflow-hidden">
        <div id="loaderBar" class="h-full bg-gradient-to-r from-cyan-400 to-blue-500 transition-all duration-300 w-0"></div>
    </div>
</div>

<button id="scrollToTopBtn" onclick="scrollToTop()" class="fixed bottom-6 left-6 w-11 h-11 bg-cyan-500/20 hover:bg-cyan-500/40 border border-cyan-500/30 text-cyan-400 rounded-2xl flex items-center justify-center shadow-lg transition-all opacity-0 pointer-events-none z-30 backdrop-blur-xl">
    <i class="fa-solid fa-arrow-up"></i>
</button>

<div class="space-y-8 pb-16 relative">

    <div class="flex flex-wrap items-center justify-between gap-4 p-4 rounded-2xl border border-white/10 bg-slate-900/40 backdrop-blur-xl shadow-lg text-xs">
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-mono">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Ledger Synchronized
            </span>
            <span class="text-slate-400 hidden sm:inline">| تتبع تدفقات المواد الخام والأكواب الجاهزة</span>
        </div>
        <div class="flex items-center gap-4 font-mono text-cyan-400">
            <div id="liveClock" class="bg-white/5 px-3 py-1 rounded-xl border border-white/5">--:--:-- --</div>
            <div class="text-slate-400 hidden md:inline"><span>CTRL+N</span> حركة جديدة | <span>CTRL+F</span> للبحث</div>
        </div>
    </div>

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800/80 pb-6">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 uppercase tracking-widest">Movements Audit Trail</span>
            </div>
            <h2 class="text-3xl font-black text-white tracking-tight mt-1">سجل الحركات المخزونية</h2>
            <p class="text-sm text-slate-400 mt-1">متابعة عمليات الوارد (الإدخال)، الصادر (الشحن)، والمرتجعات بدقة كاملة.</p>
        </div>
        <div class="flex items-center flex-wrap gap-3">
            <button onclick="exportTableToCSV()" class="ripple-btn px-4 py-2.5 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl text-sm font-semibold transition flex items-center gap-2 text-slate-300 relative overflow-hidden">
                <i class="fa-solid fa-file-excel text-cyan-400"></i> تصدير CSV
            </button>
            <button onclick="window.print()" class="ripple-btn px-4 py-2.5 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl text-sm font-semibold transition flex items-center gap-2 text-slate-300 relative overflow-hidden">
                <i class="fa-solid fa-print text-cyan-400"></i> طباعة السجل
            </button>
            <button onclick="openAddModal()" class="ripple-btn px-5 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-white rounded-xl text-sm font-bold shadow-[0_0_25px_rgba(6,182,212,.35)] hover:shadow-[0_0_35px_rgba(6,182,212,.55)] transition-all flex items-center gap-2 relative overflow-hidden">
                <i class="fa-solid fa-plus"></i> تسجيل حركة جديدة <span class="text-[10px] opacity-75 font-mono">(CTRL+N)</span>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="relative rounded-2xl border border-white/10 bg-gradient-to-br from-slate-900/80 to-slate-900/40 p-5 backdrop-blur-xl shadow-lg overflow-hidden group hover:border-cyan-500/40 transition-all hover:-translate-y-1">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-cyan-500/10 rounded-full blur-2xl group-hover:bg-cyan-500/20 transition"></div>
            <div class="flex justify-between items-start">
                <span class="text-xs font-semibold text-slate-400">إجمالي الحركات</span>
                <div class="w-9 h-9 rounded-xl bg-cyan-500/10 flex items-center justify-center text-cyan-400 border border-cyan-500/20"><i class="fa-solid fa-right-left"></i></div>
            </div>
            <div class="text-2xl font-black text-white mt-3 font-mono counter" data-target="<?= $total_trans ?>">0</div>
            <div class="text-[11px] text-slate-500 mt-1">عملية مسجلة بالنظام</div>
        </div>

        <div class="relative rounded-2xl border border-white/10 bg-gradient-to-br from-slate-900/80 to-slate-900/40 p-5 backdrop-blur-xl shadow-lg overflow-hidden group hover:border-emerald-500/40 transition-all hover:-translate-y-1">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition"></div>
            <div class="flex justify-between items-start">
                <span class="text-xs font-semibold text-slate-400">حركات الوارد (Inbound)</span>
                <div class="w-9 h-9 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 border border-emerald-500/20"><i class="fa-solid fa-arrow-down-long"></i></div>
            </div>
            <div class="text-2xl font-black text-white mt-3 font-mono counter" data-target="<?= $total_in ?>">0</div>
            <div class="text-[11px] text-slate-500 mt-1">إيداع إلى المستودع</div>
        </div>

        <div class="relative rounded-2xl border border-white/10 bg-gradient-to-br from-slate-900/80 to-slate-900/40 p-5 backdrop-blur-xl shadow-lg overflow-hidden group hover:border-rose-500/40 transition-all hover:-translate-y-1">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-rose-500/10 rounded-full blur-2xl group-hover:bg-rose-500/20 transition"></div>
            <div class="flex justify-between items-start">
                <span class="text-xs font-semibold text-slate-400">حركات الصادر (Outbound)</span>
                <div class="w-9 h-9 rounded-xl bg-rose-500/10 flex items-center justify-center text-rose-400 border border-rose-500/20"><i class="fa-solid fa-arrow-up-long"></i></div>
            </div>
            <div class="text-2xl font-black text-white mt-3 font-mono counter" data-target="<?= $total_out ?>">0</div>
            <div class="text-[11px] text-slate-500 mt-1">شحن وتوزيع للأسواق</div>
        </div>

        <div class="relative rounded-2xl border border-white/10 bg-gradient-to-br from-slate-900/80 to-slate-900/40 p-5 backdrop-blur-xl shadow-lg overflow-hidden group hover:border-blue-500/40 transition-all hover:-translate-y-1">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-blue-500/10 rounded-full blur-2xl group-hover:bg-blue-500/20 transition"></div>
            <div class="flex justify-between items-start">
                <span class="text-xs font-semibold text-slate-400">المرتجعات (Returns)</span>
                <div class="w-9 h-9 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-400 border border-blue-500/20"><i class="fa-solid fa-rotate-left"></i></div>
            </div>
            <div class="text-2xl font-black text-blue-400 mt-3 font-mono counter" data-target="<?= $total_returns ?>">0</div>
            <div class="text-[11px] text-slate-500 mt-1">مرتجع من العملاء</div>
        </div>
    </div>

    <?php if ($message): ?>
        <div id="systemToast" class="p-4 rounded-2xl border flex items-center justify-between text-sm shadow-xl backdrop-blur-xl transition-all <?= $msg_type === 'success' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-rose-500/10 border-rose-500/20 text-rose-400' ?>">
            <div class="flex items-center gap-3">
                <i class="fa-solid <?= $msg_type === 'success' ? 'fa-circle-check text-lg' : 'fa-triangle-exclamation text-lg' ?>"></i>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
            <button onclick="document.getElementById('systemToast').remove()" class="text-slate-400 hover:text-white transition"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>

    <div class="rounded-2xl border border-white/10 bg-slate-950/40 backdrop-blur-xl shadow-2xl overflow-hidden">
        
        <div class="p-4 border-b border-white/5 flex flex-col sm:flex-row items-center justify-between gap-4 bg-white/[0.01]">
            <div class="relative w-full sm:w-80">
                <i class="fa-solid fa-search absolute right-4 top-3 text-slate-500 text-xs"></i>
                <input type="text" id="tableSearch" onkeyup="filterTable()" placeholder="بحث حي فوري بالصنف أو الملاحظات (CTRL+F)..." class="w-full bg-white/5 border border-white/10 rounded-xl py-2 pr-10 pl-4 text-xs text-white outline-none focus:border-cyan-400 focus:ring-4 focus:ring-cyan-400/20 transition">
            </div>
            <div class="flex items-center gap-2">
                <select id="typeFilter" onchange="filterTable()" class="bg-white/5 border border-white/10 rounded-xl py-2 px-3 text-xs text-slate-300 outline-none focus:border-cyan-400">
                    <option value="" class="bg-slate-900">كل أنواع الحركات</option>
                    <option value="وارد" class="bg-slate-900">وارد (In)</option>
                    <option value="صادر" class="bg-slate-900">صادر (Out)</option>
                    <option value="مرتجع" class="bg-slate-900">مرتجع (Return)</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse" id="transactionsTable">
                <thead>
                    <tr class="border-b border-white/10 text-slate-400 text-xs font-semibold bg-white/[0.02]">
                        <th class="p-4.5">رقم الحركة</th>
                        <th class="p-4.5">اسم الصنف (المنتج)</th>
                        <th class="p-4.5">نوع الحركة</th>
                        <th class="p-4.5">الكمية</th>
                        <th class="p-4.5">المسؤول / الملاحظات</th>
                        <th class="p-4.5">تاريخ ووقت الحركة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-xs text-slate-300">
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="6" class="p-16 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <i class="fa-solid fa-file-invoice text-4xl text-slate-600 mb-2"></i>
                                    <span class="text-sm font-semibold text-white">لا توجد أي حركات مخزونية مسجلة حتى الآن.</span>
                                    <button onclick="openAddModal()" class="mt-2 px-4 py-2 bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 rounded-xl text-xs font-bold">تسجيل أول حركة الآن</button>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
                            <?php 
                                $type_label = 'وارد';
                                $badge_class = 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
                                if ($t['type'] === 'out') {
                                    $type_label = 'صادر';
                                    $badge_class = 'bg-rose-500/10 text-rose-400 border-rose-500/20';
                                } elseif ($t['type'] === 'return') {
                                    $type_label = 'مرتجع';
                                    $badge_class = 'bg-blue-500/10 text-blue-400 border-blue-500/20';
                                }
                                $qty_val = isset($t['quantity_changed']) ? intval($t['quantity_changed']) : 0;
                                // حل آمن لاسم المسؤول من الـ Database أو السشن
                                $current_user_name = $t['user_name'] ?? ($_SESSION['user_name'] ?? 'مسؤول المستودع');
                            ?>
                            <tr class="hover:bg-white/[0.03] transition-colors group" data-type="<?= $type_label ?>">
                                <td class="p-4.5 font-mono text-cyan-400/80">#TR-<?= $t['id'] ?></td>
                                <td class="p-4.5 font-bold text-white flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400"><i class="fa-solid fa-mug-hot"></i></div>
                                    <div>
                                        <div class="product-name font-bold"><?= htmlspecialchars($t['product_name'] ?? 'صنف محذوف') ?></div>
                                        <div class="text-[10px] font-mono text-slate-500"><?= htmlspecialchars($t['product_sku'] ?? '') ?></div>
                                    </div>
                                </td>
                                <td class="p-4.5">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 text-[11px] font-semibold rounded-full border <?= $badge_class ?>">
                                        <?= $type_label ?>
                                    </span>
                                </td>
                                <td class="p-4.5 font-bold font-mono text-white text-sm">
                                    <?= ($t['type'] === 'out' ? '-' : '+') . number_format($qty_val) ?>
                                </td>
                                <td class="p-4.5">
                                    <div class="font-semibold text-white transaction-notes"><?= htmlspecialchars($t['notes'] ?: 'بدون ملاحظات') ?></div>
                                    <div class="text-[10px] text-slate-500 font-mono mt-0.5">بواسطة: <?= htmlspecialchars($current_user_name) ?></div>
                                </td>
                                <td class="p-4.5 font-mono text-slate-400"><?= $t['created_at'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="addTransactionModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-xl hidden flex items-center justify-center p-4 z-50">
    <div class="relative bg-gradient-to-b from-slate-900 via-slate-950 to-slate-900 border border-cyan-500/20 w-full max-w-lg p-6 sm:p-8 rounded-3xl shadow-[0_0_50px_rgba(6,182,212,.15)] overflow-hidden">
        <div class="absolute top-0 right-0 w-48 h-48 bg-cyan-400/10 blur-3xl pointer-events-none"></div>
        
        <div class="flex justify-between items-center mb-6 border-b border-slate-800 pb-4 relative z-10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400"><i class="fa-solid fa-right-left"></i></div>
                <h3 class="text-lg font-bold text-white">تسجيل حركة مخزون جديدة</h3>
            </div>
            <button onclick="toggleModal(false)" class="text-slate-400 hover:text-white transition w-8 h-8 rounded-full bg-white/5 flex items-center justify-center">&times;</button>
        </div>

        <form method="POST" class="space-y-4 relative z-10 text-xs">
            <div>
                <label class="block text-slate-400 mb-1.5 font-semibold">اختر الصنف (المنتج)</label>
                <select name="product_id" required class="w-full bg-slate-900/90 border border-white/10 rounded-xl p-3 text-white outline-none focus:border-cyan-400 transition">
                    <option value="" class="bg-slate-900">-- حدد صنف الأكواب المطلوب --</option>
                    <?php foreach ($all_products as $ap): ?>
                        <option value="<?= $ap['id'] ?>" class="bg-slate-900"><?= htmlspecialchars($ap['name']) ?> (المتوفر: <?= $ap['quantity'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-slate-400 mb-1.5 font-semibold">نوع الحركة</label>
                    <select name="type" required class="w-full bg-slate-900/90 border border-white/10 rounded-xl p-3 text-white outline-none focus:border-cyan-400 transition">
                        <option value="in" class="bg-slate-900">وارد (إضافة مخزون)</option>
                        <option value="out" class="bg-slate-900">صادر (شحن وتوزيع)</option>
                        <option value="return" class="bg-slate-900">مرتجع (استرجاع للمستودع)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-400 mb-1.5 font-semibold">الكمية</label>
                    <input type="number" name="quantity" min="1" required placeholder="500" class="w-full bg-slate-900/90 border border-white/10 rounded-xl p-3 text-white font-mono outline-none focus:border-cyan-400 transition">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-slate-400 mb-1.5 font-semibold">اسم المسؤول</label>
                    <input type="text" name="user_name" placeholder="اسم الموظف أو المسؤول" class="w-full bg-slate-900/90 border border-white/10 rounded-xl p-3 text-white outline-none focus:border-cyan-400 transition">
                </div>
                <div>
                    <label class="block text-slate-400 mb-1.5 font-semibold">ملاحظات خط الإنتاج / الطلبية</label>
                    <input type="text" name="notes" placeholder="رقم الشاحنة أو سبب الإرجاع" class="w-full bg-slate-900/90 border border-white/10 rounded-xl p-3 text-white outline-none focus:border-cyan-400 transition">
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-3">
                <button type="button" onclick="toggleModal(false)" class="px-4 py-2.5 bg-white/5 hover:bg-white/10 text-slate-300 rounded-xl transition font-semibold">إلغاء <span class="opacity-65 font-mono">(ESC)</span></button>
                <button type="submit" name="add_transaction" class="px-6 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 text-white rounded-xl font-bold shadow-[0_0_20px_rgba(6,182,212,.3)] transition">اعتماد وتسجيل الحركة</button>
            </div>
        </form>
    </div>
</div>

<script>
window.addEventListener('load', () => {
    let bar = document.getElementById('loaderBar');
    if(bar) bar.style.width = '100%';
    setTimeout(() => {
        let loader = document.getElementById('pageLoader');
        if(loader) { loader.style.opacity = '0'; setTimeout(() => loader.remove(), 700); }
    }, 400);
    runCounters();
});

function runCounters() {
    document.querySelectorAll('.counter').forEach(counter => {
        const target = +counter.getAttribute('data-target');
        let count = 0;
        const speed = target / 35 || 1;
        const updateCount = () => {
            count += speed;
            if (count < target) {
                counter.innerText = Math.ceil(count).toLocaleString();
                setTimeout(updateCount, 25);
            } else {
                counter.innerText = target.toLocaleString();
            }
        };
        updateCount();
    });
}

function updateClock() {
    const now = new Date();
    let clock = document.getElementById('liveClock');
    if(clock) clock.innerText = now.toLocaleTimeString('en-US');
}
setInterval(updateClock, 1000);
updateClock();

window.onscroll = function() {
    let btn = document.getElementById('scrollToTopBtn');
    if (!btn) return;
    if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
        btn.style.opacity = '1';
        btn.style.pointerEvents = 'auto';
    } else {
        btn.style.opacity = '0';
        btn.style.pointerEvents = 'none';
    }
};
function scrollToTop() {
    window.scrollTo({top: 0, behavior: 'smooth'});
}

document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        openAddModal();
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.getElementById('tableSearch').focus();
    }
    if (e.key === 'Escape') {
        toggleModal(false);
    }
});

document.querySelectorAll('.ripple-btn').forEach(button => {
    button.addEventListener('click', function (e) {
        let circle = document.createElement('span');
        let diameter = Math.max(this.clientWidth, this.clientHeight);
        let radius = diameter / 2;
        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${e.clientX - this.getBoundingClientRect().left - radius}px`;
        circle.style.top = `${e.clientY - this.getBoundingClientRect().top - radius}px`;
        circle.classList.add('ripple');
        let ripple = this.getElementsByClassName('ripple')[0];
        if (ripple) ripple.remove();
        this.appendChild(circle);
    });
});

function toggleModal(show) {
    document.getElementById('addTransactionModal').classList.toggle('hidden', !show);
}

function openAddModal() {
    toggleModal(true);
}

function filterTable() {
    let input = document.getElementById("tableSearch").value.toLowerCase();
    let typeFilter = document.getElementById("typeFilter").value;
    let table = document.getElementById("transactionsTable");
    let tr = table.getElementsByTagName("tr");

    for (let i = 1; i < tr.length; i++) {
        let tdName = tr[i].querySelector(".product-name");
        let tdNotes = tr[i].querySelector(".transaction-notes");
        let rowType = tr[i].getAttribute("data-type");
        
        if (tdName && tdNotes) {
            let nameText = tdName.textContent || tdName.innerText;
            let notesText = tdNotes.textContent || tdNotes.innerText;
            
            let matchesSearch = nameText.toLowerCase().indexOf(input) > -1 || notesText.toLowerCase().indexOf(input) > -1;
            let matchesType = typeFilter === "" || rowType === typeFilter;

            if (matchesSearch && matchesType) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
}

function exportTableToCSV() {
    let csv = [];
    let rows = document.querySelectorAll("#transactionsTable tr");
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) {
            row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
        }
        csv.push(row.join(","));
    }
    let csvFile = new Blob([csv.join("\n")], { type: "text/csv" });
    let downloadLink = document.createElement("a");
    downloadLink.download = "plastocup_inventory_ledger.csv";
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
}
</script>

<style>
.ripple {
    position: absolute;
    border-radius: 50%;
    transform: scale(0);
    animation: ripple-anim 0.6s linear;
    background-color: rgba(255, 255, 255, 0.3);
    pointer-events: none;
}
@keyframes ripple-anim {
    to { transform: scale(4); opacity: 0; }
}
</style>

<?php include_once 'includes/footer.php'; ?>