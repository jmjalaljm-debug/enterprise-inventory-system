<?php
require_once 'config/db.php';

$message = '';
$msg_type = 'success';

// معالجة حذف منتج
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $message = "تم حذف الصنف من النظام بنجاح!";
        $msg_type = 'success';
    } catch (PDOException $e) {
        $message = "فشل الحذف (المنتج مرتبط بسجل الحركات المخزونية): " . $e->getMessage();
        $msg_type = 'error';
    }
}

// معالجة إضافة أو تعديل منتج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_product']) || isset($_POST['edit_product']))) {
    $name = trim($_POST['name']);
    $sku = trim($_POST['sku']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $quantity = intval($_POST['quantity']);
    $min_stock_level = intval($_POST['min_stock_level']);
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

    if ($product_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE products SET name = ?, sku = ?, description = ?, price = ?, quantity = ?, min_stock_level = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $sku, $description, $price, $quantity, $min_stock_level, $product_id]);
            $message = "تم تحديث بيانات الصنف بنجاح!";
            $msg_type = 'success';
        } catch (PDOException $e) {
            $message = "خطأ في التحديث: " . $e->getMessage();
            $msg_type = 'error';
        }
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO products (name, sku, description, price, quantity, min_stock_level) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $sku, $description, $price, $quantity, $min_stock_level]);
            $message = "تم إدراج الصنف الجديد في المستودع بنجاح!";
            $msg_type = 'success';
        } catch (PDOException $e) {
            $message = "خطأ في الإضافة (تأكد من عدم تكرار رمز SKU): " . $e->getMessage();
            $msg_type = 'error';
        }
    }
}

// جلب المنتجات
$stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// حساب الإحصائيات
$total_items = count($products);
$total_stock = array_sum(array_column($products, 'quantity'));
$total_value = array_sum(array_map(fn($p) => $p['price'] * $p['quantity'], $products));
$low_stock_count = count(array_filter($products, fn($p) => $p['quantity'] <= $p['min_stock_level'] && $p['quantity'] > 0));
$out_of_stock_count = count(array_filter($products, fn($p) => $p['quantity'] <= 0));

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
    <p class="text-xs text-slate-500 uppercase tracking-widest mb-6">جارٍ تهيئة مستودع الأكواب البلاستيكية...</p>
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
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Server Online
            </span>
            <span class="text-slate-400 hidden sm:inline">| لوحة تحكم خط إنتاج الأكواب الفردية</span>
        </div>
        <div class="flex items-center gap-4 font-mono text-cyan-400">
            <div id="liveClock" class="bg-white/5 px-3 py-1 rounded-xl border border-white/5">--:--:-- --</div>
            <div class="text-slate-400 hidden md:inline"><span>CTRL+N</span> للإضافة | <span>CTRL+F</span> للبحث</div>
        </div>
    </div>

    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800/80 pb-6">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 uppercase tracking-widest">Inventory Matrix v3.5</span>
            </div>
            <h2 class="text-3xl font-black text-white tracking-tight mt-1">إدارة الأصناف والمنتجات</h2>
            <p class="text-sm text-slate-400 mt-1">متابعة دقيقة للأكواد (SKU)، مستويات الأكواب البلاستيكية، وتنبيهات خطوط التصنيع.</p>
        </div>
        <div class="flex items-center flex-wrap gap-3">
            <button onclick="exportTableToCSV()" class="ripple-btn px-4 py-2.5 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl text-sm font-semibold transition flex items-center gap-2 text-slate-300 relative overflow-hidden">
                <i class="fa-solid fa-file-excel text-cyan-400"></i> تصدير CSV
            </button>
            <button onclick="window.print()" class="ripple-btn px-4 py-2.5 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl text-sm font-semibold transition flex items-center gap-2 text-slate-300 relative overflow-hidden">
                <i class="fa-solid fa-print text-cyan-400"></i> طباعة
            </button>
            <button onclick="openAddModal()" class="ripple-btn px-5 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-white rounded-xl text-sm font-bold shadow-[0_0_25px_rgba(6,182,212,.35)] hover:shadow-[0_0_35px_rgba(6,182,212,.55)] transition-all flex items-center gap-2 relative overflow-hidden">
                <i class="fa-solid fa-plus"></i> إضافة صنف جديد <span class="text-[10px] opacity-75 font-mono">(CTRL+N)</span>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="relative rounded-2xl border border-white/10 bg-gradient-to-br from-slate-900/80 to-slate-900/40 p-5 backdrop-blur-xl shadow-lg overflow-hidden group hover:border-cyan-500/40 transition-all hover:-translate-y-1">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-cyan-500/10 rounded-full blur-2xl group-hover:bg-cyan-500/20 transition"></div>
            <div class="flex justify-between items-start">
                <span class="text-xs font-semibold text-slate-400">إجمالي الأصناف</span>
                <div class="w-9 h-9 rounded-xl bg-cyan-500/10 flex items-center justify-center text-cyan-400 border border-cyan-500/20"><i class="fa-solid fa-boxes-stacked"></i></div>
            </div>
            <div class="text-2xl font-black text-white mt-3 font-mono counter" data-target="<?= $total_items ?>">0</div>
            <div class="text-[11px] text-slate-500 mt-1">أنواع أكواب وعبوات مسجلة</div>
        </div>

        <div class="relative rounded-2xl border border-white/10 bg-gradient-to-br from-slate-900/80 to-slate-900/40 p-5 backdrop-blur-xl shadow-lg overflow-hidden group hover:border-blue-500/40 transition-all hover:-translate-y-1">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-blue-500/10 rounded-full blur-2xl group-hover:bg-blue-500/20 transition"></div>
            <div class="flex justify-between items-start">
                <span class="text-xs font-semibold text-slate-400">إجمالي الوحدات بالمخزون</span>
                <div class="w-9 h-9 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-400 border border-blue-500/20"><i class="fa-solid fa-layer-group"></i></div>
            </div>
            <div class="text-2xl font-black text-white mt-3 font-mono counter" data-target="<?= $total_stock ?>">0</div>
            <div class="text-[11px] text-slate-500 mt-1">قطعة كوب جاهزة للاستعمال</div>
        </div>

        <div class="relative rounded-2xl border border-white/10 bg-gradient-to-br from-slate-900/80 to-slate-900/40 p-5 backdrop-blur-xl shadow-lg overflow-hidden group hover:border-emerald-500/40 transition-all hover:-translate-y-1">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition"></div>
            <div class="flex justify-between items-start">
                <span class="text-xs font-semibold text-slate-400">القيمة التقديرية للمخزون</span>
                <div class="w-9 h-9 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 border border-emerald-500/20"><i class="fa-solid fa-dollar-sign"></i></div>
            </div>
            <div class="text-2xl font-black text-white mt-3 font-mono counter-float" data-target="<?= $total_value ?>">0.00</div>
            <div class="text-[11px] text-slate-500 mt-1">القيمة الإجمالية بالدولار</div>
        </div>

        <div class="relative rounded-2xl border border-white/10 bg-gradient-to-br from-slate-900/80 to-slate-900/40 p-5 backdrop-blur-xl shadow-lg overflow-hidden group hover:border-amber-500/40 transition-all hover:-translate-y-1">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-amber-500/10 rounded-full blur-2xl group-hover:bg-amber-500/20 transition"></div>
            <div class="flex justify-between items-start">
                <span class="text-xs font-semibold text-slate-400">تنبيهات النفاذ / العجز</span>
                <div class="w-9 h-9 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-400 border border-amber-500/20"><i class="fa-solid fa-triangle-exclamation"></i></div>
            </div>
            <div class="text-2xl font-black text-amber-400 mt-3 font-mono counter" data-target="<?= $low_stock_count + $out_of_stock_count ?>">0</div>
            <div class="text-[11px] text-slate-500 mt-1">تتطلب عملية تصنيع عاجلة</div>
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
                <input type="text" id="tableSearch" onkeyup="filterTable()" placeholder="بحث حي فوري بالاسم أو الرمز (CTRL+F)..." class="w-full bg-white/5 border border-white/10 rounded-xl py-2 pr-10 pl-4 text-xs text-white outline-none focus:border-cyan-400 focus:ring-4 focus:ring-cyan-400/20 transition">
            </div>
            <div class="flex items-center gap-2">
                <select id="statusFilter" onchange="filterTable()" class="bg-white/5 border border-white/10 rounded-xl py-2 px-3 text-xs text-slate-300 outline-none focus:border-cyan-400">
                    <option value="" class="bg-slate-900">كل الحالات التشغيلية</option>
                    <option value="متوفر" class="bg-slate-900">متوفر</option>
                    <option value="منخفض" class="bg-slate-900">مخزون منخفض</option>
                    <option value="نَفَد" class="bg-slate-900">نافد</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse" id="productsTable">
                <thead>
                    <tr class="border-b border-white/10 text-slate-400 text-xs font-semibold bg-white/[0.02]">
                        <th class="p-4.5">اسم الصنف (المنتج)</th>
                        <th class="p-4.5">رمز التخزين SKU</th>
                        <th class="p-4.5">السعر للوحدة</th>
                        <th class="p-4.5">الكمية بالمستودع</th>
                        <th class="p-4.5">الحالة التشغيلية</th>
                        <th class="p-4.5 text-center">إجراءات النظام</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-xs text-slate-300">
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="6" class="p-16 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <i class="fa-solid fa-box-open text-4xl text-slate-600 mb-2"></i>
                                    <span class="text-sm font-semibold text-white">لا توجد أي أكواب أو أصناف مسجلة حالياً.</span>
                                    <button onclick="openAddModal()" class="mt-2 px-4 py-2 bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 rounded-xl text-xs font-bold">إضافة أول صنف الآن</button>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <?php 
                                $status_text = 'متوفر';
                                if ($p['quantity'] <= 0) $status_text = 'نَفَد';
                                elseif ($p['quantity'] <= $p['min_stock_level']) $status_text = 'منخفض';
                            ?>
                            <tr class="hover:bg-white/[0.03] transition-colors group" data-status="<?= $status_text ?>">
                                <td class="p-4.5 font-bold text-white flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400 group-hover:scale-105 transition"><i class="fa-solid fa-mug-hot"></i></div>
                                    <span class="product-name"><?= htmlspecialchars($p['name']) ?></span>
                                </td>
                                <td class="p-4.5 font-mono text-cyan-400/80 product-sku"><?= htmlspecialchars($p['sku']) ?></td>
                                <td class="p-4.5 font-mono text-slate-200"><?= number_format($p['price'], 2) ?> $</td>
                                <td class="p-4.5 font-bold font-mono text-white text-sm"><?= number_format($p['quantity']) ?></td>
                                <td class="p-4.5 product-status">
                                    <?php if ($p['quantity'] > $p['min_stock_level']): ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 text-[11px] font-semibold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> متوفر
                                        </span>
                                    <?php elseif ($p['quantity'] > 0): ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 text-[11px] font-semibold rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> مخزون منخفض
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 text-[11px] font-semibold rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-400 animate-ping"></span> نَفَد المخزون
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4.5 text-center">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button onclick='openEditModal(<?= json_encode($p) ?>)' class="px-2.5 py-1.5 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 rounded-lg transition border border-amber-500/20 font-semibold flex items-center gap-1"><i class="fa-solid fa-pen-to-square"></i> تعديل</button>
                                        <button onclick='openQuickView(<?= json_encode($p) ?>)' class="px-2.5 py-1.5 bg-white/5 hover:bg-white/10 text-slate-300 rounded-lg transition border border-white/10 font-semibold flex items-center gap-1"><i class="fa-solid fa-eye text-cyan-400"></i> معاينة</button>
                                        <a href="products.php?delete=<?= $p['id'] ?>" onclick="return confirm('هل أنت متأكد من حذف هذا الصنف من النظام؟')" class="px-2.5 py-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-lg transition border border-rose-500/20 font-semibold flex items-center gap-1"><i class="fa-solid fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="addProductModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-xl hidden flex items-center justify-center p-4 z-50">
    <div class="relative bg-gradient-to-b from-slate-900 via-slate-950 to-slate-900 border border-cyan-500/20 w-full max-w-lg p-6 sm:p-8 rounded-3xl shadow-[0_0_50px_rgba(6,182,212,.15)] overflow-hidden">
        <div class="absolute top-0 right-0 w-48 h-48 bg-cyan-400/10 blur-3xl pointer-events-none"></div>
        
        <div class="flex justify-between items-center mb-6 border-b border-slate-800 pb-4 relative z-10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400"><i class="fa-solid fa-mug-hot"></i></div>
                <h3 id="modalTitle" class="text-lg font-bold text-white">إضافة صنف جديد للمستودع</h3>
            </div>
            <button onclick="toggleModal(false)" class="text-slate-400 hover:text-white transition w-8 h-8 rounded-full bg-white/5 flex items-center justify-center">&times;</button>
        </div>

        <form method="POST" class="space-y-4 relative z-10 text-xs">
            <input type="hidden" name="product_id" id="productId">
            <div>
                <label class="block text-slate-400 mb-1.5 font-semibold">اسم الصنف (أكواب بلاستيك شفافة 180مل للاستعمال لمرة واحدة)</label>
                <input type="text" name="name" id="productName" required class="w-full bg-slate-900/90 border border-white/10 rounded-xl p-3 text-white outline-none focus:border-cyan-400 focus:ring-4 focus:ring-cyan-400/20 transition">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-slate-400 mb-1.5 font-semibold">رمز التخزين SKU</label>
                    <input type="text" name="sku" id="productSku" required placeholder="CUP-PL-180" class="w-full bg-slate-900/90 border border-white/10 rounded-xl p-3 text-white font-mono outline-none focus:border-cyan-400 focus:ring-4 focus:ring-cyan-400/20 transition">
                </div>
                <div>
                    <label class="block text-slate-400 mb-1.5 font-semibold">السعر للوحدة ($)</label>
                    <input type="number" step="0.01" name="price" id="productPrice" required class="w-full bg-slate-900/90 border border-white/10 rounded-xl p-3 text-white font-mono outline-none focus:border-cyan-400 focus:ring-4 focus:ring-cyan-400/20 transition">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-slate-400 mb-1.5 font-semibold">الكمية الابتدائية</label>
                    <input type="number" name="quantity" id="productQuantity" value="0" required class="w-full bg-slate-900/90 border border-white/10 rounded-xl p-3 text-white font-mono outline-none focus:border-cyan-400 focus:ring-4 focus:ring-cyan-400/20 transition">
                </div>
                <div>
                    <label class="block text-slate-400 mb-1.5 font-semibold">حد التنبيه الأدنى</label>
                    <input type="number" name="min_stock_level" id="productMinStock" value="1000" required class="w-full bg-slate-900/90 border border-white/10 rounded-xl p-3 text-white font-mono outline-none focus:border-cyan-400 focus:ring-4 focus:ring-cyan-400/20 transition">
                </div>
            </div>
            <div>
                <label class="block text-slate-400 mb-1.5 font-semibold">مواصفات التعبئة والتغليف</label>
                <textarea name="description" id="productDescription" rows="2" class="w-full bg-slate-900/90 border border-white/10 rounded-xl p-3 text-white outline-none focus:border-cyan-400 focus:ring-4 focus:ring-cyan-400/20 transition"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-3">
                <button type="button" onclick="toggleModal(false)" class="px-4 py-2.5 bg-white/5 hover:bg-white/10 text-slate-300 rounded-xl transition font-semibold">إلغاء <span class="opacity-65 font-mono">(ESC)</span></button>
                <button type="submit" name="add_product" id="submitBtn" class="px-6 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 text-white rounded-xl font-bold shadow-[0_0_20px_rgba(6,182,212,.3)] transition">حفظ الصنف</button>
            </div>
        </form>
    </div>
</div>

<div id="quickViewModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-xl hidden flex items-center justify-center p-4 z-50">
    <div class="relative bg-gradient-to-b from-slate-900 to-slate-950 border border-white/10 w-full max-w-md p-6 rounded-3xl shadow-2xl overflow-hidden">
        <div class="flex justify-between items-center mb-4 border-b border-slate-800 pb-3">
            <div class="flex items-center gap-2.5 text-white font-bold text-sm">
                <i class="fa-solid fa-circle-info text-cyan-400"></i> تفاصيل الصنف المخزوني
            </div>
            <button onclick="toggleQuickView(false)" class="text-slate-400 hover:text-white">&times;</button>
        </div>
        <div id="quickViewContent" class="space-y-2.5 text-xs text-slate-300"></div>
        <div class="mt-6 flex justify-end">
            <button onclick="toggleQuickView(false)" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-semibold transition">إغلاق المعاينة</button>
        </div>
    </div>
</div>

<script>
// شاشة التحميل التفاعلية Preloader
window.addEventListener('load', () => {
    let bar = document.getElementById('loaderBar');
    bar.style.width = '100%';
    setTimeout(() => {
        let loader = document.getElementById('pageLoader');
        loader.style.opacity = '0';
        setTimeout(() => loader.remove(), 700);
    }, 400);
    
    // تشغيل العدادات المتحركة للبطاقات
    runCounters();
});

// عدادات الأرقام المتحركة (CountUp Animation)
function runCounters() {
    const counters = document.querySelectorAll('.counter');
    counters.forEach(counter => {
        const target = +counter.getAttribute('data-target');
        let count = 0;
        const speed = target / 40;
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

    const floatCounters = document.querySelectorAll('.counter-float');
    floatCounters.forEach(counter => {
        const target = +counter.getAttribute('data-target');
        let count = 0;
        const speed = target / 35;
        const updateFloat = () => {
            count += speed;
            if (count < target) {
                counter.innerText = count.toFixed(2);
                setTimeout(updateFloat, 25);
            } else {
                counter.innerText = target.toFixed(2);
            }
        };
        updateFloat();
    });
}

// الساعة الحية التفاعلية
function updateClock() {
    const now = new Date();
    document.getElementById('liveClock').innerText = now.toLocaleTimeString('en-US');
}
setInterval(updateClock, 1000);
updateClock();

// زر الصعود لأعلى الصفحة وإظهاره عند النزول
window.onscroll = function() {
    let btn = document.getElementById('scrollToTopBtn');
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

// اختصارات لوحة المفاتيح الاحترافية (Keyboard Shortcuts)
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
        toggleQuickView(false);
    }
});

// تأثيرات الأزرار (Ripple Effect)
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

// إدارة فتح وإغلاق النوافذ Modal
function toggleModal(show) {
    document.getElementById('addProductModal').classList.toggle('hidden', !show);
}

function openAddModal() {
    document.getElementById('modalTitle').innerText = 'إضافة صنف جديد للمستودع';
    document.getElementById('productId').value = '';
    document.getElementById('productName').value = '';
    document.getElementById('productSku').value = '';
    document.getElementById('productPrice').value = '';
    document.getElementById('productQuantity').value = '0';
    document.getElementById('productMinStock').value = '1000';
    document.getElementById('productDescription').value = '';
    document.getElementById('submitBtn').name = 'add_product';
    document.getElementById('submitBtn').innerText = 'حفظ الصنف';
    toggleModal(true);
}

function openEditModal(product) {
    document.getElementById('modalTitle').innerText = 'تعديل مواصفات الصنف';
    document.getElementById('productId').value = product.id;
    document.getElementById('productName').value = product.name;
    document.getElementById('productSku').value = product.sku;
    document.getElementById('productPrice').value = product.price;
    document.getElementById('productQuantity').value = product.quantity;
    document.getElementById('productMinStock').value = product.min_stock_level;
    document.getElementById('productDescription').value = product.description || '';
    document.getElementById('submitBtn').name = 'edit_product';
    document.getElementById('submitBtn').innerText = 'تحديث البيانات';
    toggleModal(true);
}

function openQuickView(product) {
    const content = `
        <div class="p-3.5 bg-white/5 rounded-2xl border border-white/5 space-y-2.5 font-mono">
            <p><strong class="text-slate-400 font-sans">اسم الصنف:</strong> <span class="text-white font-bold font-sans">${product.name}</span></p>
            <p><strong class="text-slate-400 font-sans">رمز التخزين SKU:</strong> <span class="text-cyan-400">${product.sku}</span></p>
            <p><strong class="text-slate-400 font-sans">سعر الوحدة:</strong> ${product.price} $</p>
            <p><strong class="text-slate-400 font-sans">الكمية المتوفرة:</strong> ${product.quantity}</p>
            <p><strong class="text-slate-400 font-sans">حد التنبيه الأدنى:</strong> ${product.min_stock_level}</p>
            <p><strong class="text-slate-400 font-sans">مواصفات التغليف:</strong> <span class="font-sans">${product.description || 'لا يوجد مواصفات إضافية'}</span></p>
            <p><strong class="text-slate-400 font-sans">آخر تعديل:</strong> ${product.updated_at || 'لم يتم التعديل'}</p>
        </div>
    `;
    document.getElementById('quickViewContent').innerHTML = content;
    document.getElementById('quickViewModal').classList.remove('hidden');
}

function toggleQuickView(show) {
    document.getElementById('quickViewModal').classList.toggle('hidden', !show);
}

// دالة الفلترة والبحث الحي داخل الجدول
function filterTable() {
    let input = document.getElementById("tableSearch").value.toLowerCase();
    let statusFilter = document.getElementById("statusFilter").value;
    let table = document.getElementById("productsTable");
    let tr = table.getElementsByTagName("tr");

    for (let i = 1; i < tr.length; i++) {
        let tdName = tr[i].querySelector(".product-name");
        let tdSku = tr[i].querySelector(".product-sku");
        let rowStatus = tr[i].getAttribute("data-status");
        
        if (tdName && tdSku) {
            let nameText = tdName.textContent || tdName.innerText;
            let skuText = tdSku.textContent || tdSku.innerText;
            
            let matchesSearch = nameText.toLowerCase().indexOf(input) > -1 || skuText.toLowerCase().indexOf(input) > -1;
            let matchesStatus = statusFilter === "" || rowStatus === statusFilter;

            if (matchesSearch && matchesStatus) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
}

// تصدير الجدول إلى ملف CSV
function exportTableToCSV() {
    let csv = [];
    let rows = document.querySelectorAll("#productsTable tr");
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length - 1; j++) {
            row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
        }
        csv.push(row.join(","));
    }
    let csvFile = new Blob([csv.join("\n")], { type: "text/csv" });
    let downloadLink = document.createElement("a");
    downloadLink.download = "plastocup_inventory_matrix.csv";
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
    to {
        transform: scale(4);
        opacity: 0;
    }
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<?php include_once 'includes/footer.php'; ?>