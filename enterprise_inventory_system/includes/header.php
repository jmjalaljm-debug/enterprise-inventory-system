<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plastic Cups Factory - Inventory Suite</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/tsparticles@3/tsparticles.bundle.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        darkBg: '#060913',
                        accent: '#06b6d4'
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes move {
            0% { transform: translateX(-200px); }
            100% { transform: translateX(500px); }
        }
        .sidebar-link {
            position: relative;
            overflow: hidden;
            border-radius: 16px;
            transition: 0.45s;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        .sidebar-link::before {
            content: "";
            position: absolute;
            top: 0;
            right: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.08), transparent);
            transition: 0.7s;
        }
        .sidebar-link:hover::before {
            right: 100%;
        }
        .sidebar-link:hover {
            transform: translateX(-6px);
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 30px rgba(6, 182, 212, 0.18);
        }
        .sidebar-link.active {
            background: linear-gradient(90deg, rgba(6, 182, 212, 0.22), rgba(6, 182, 212, 0.05));
            box-shadow: 0 0 40px rgba(6, 182, 212, 0.25);
        }
    </style>
</head>
<body class="bg-darkBg text-gray-100 min-h-screen flex overflow-x-hidden selection:bg-cyan-500 selection:text-black">

    <aside class="w-72 bg-gradient-to-b from-slate-950 via-slate-900 to-black before:absolute before:top-0 before:right-0 before:w-full before:h-full before:bg-[radial-gradient(circle_at_top,#06b6d420,transparent_70%)] before:pointer-events-none border-l border-cyan-500/10 p-6 flex flex-col justify-between shadow-2xl z-40 relative overflow-hidden">
        
        <div class="absolute top-0 left-0 w-full h-1 overflow-hidden z-20">
            <div class="h-full w-40 bg-cyan-400 animate-[move_5s_linear_infinite] shadow-[0_0_10px_#22d3ee]"></div>
        </div>

        <div class="absolute left-0 top-0 h-full w-px bg-gradient-to-b from-transparent via-cyan-400 to-transparent opacity-70"></div>

        <div id="particles-js" class="absolute inset-0 -z-10 opacity-30 pointer-events-none"></div>

        <div class="relative z-10">
            <div class="flex items-center space-x-3 space-x-reverse mb-6 pb-5 border-b border-slate-800/80">
                <div class="relative">
                    <div class="absolute inset-0 rounded-2xl blur-xl bg-cyan-400/50 animate-pulse"></div>
                    <div class="relative w-14 h-14 rounded-2xl bg-gradient-to-br from-cyan-400 to-blue-600 flex items-center justify-center shadow-[0_0_35px_rgba(34,211,238,.45)]">
                        <i class="fa-solid fa-mug-hot text-2xl text-white"></i>
                    </div>
                </div>
                <div>
                    <h1 class="text-white font-black tracking-wider text-base">PLASTO<span class="text-cyan-400">CUP</span></h1>
                    <p class="text-[10px] text-gray-500 tracking-widest uppercase">Disposable Warehouse</p>
                </div>
            </div>

            <div class="mt-4 mb-6">
                <div class="relative">
                    <i class="fa fa-search absolute right-4 top-3.5 text-slate-500"></i>
                    <input type="text" placeholder="بحث عن صنف أو عبوة..." class="w-full bg-white/5 border border-white/10 rounded-xl py-2.5 pr-11 pl-3 outline-none text-sm text-white focus:border-cyan-400 focus:ring-4 focus:ring-cyan-400/20 transition">
                </div>
            </div>

            <div class="mb-6">
                <div class="rounded-2xl bg-gradient-to-r from-cyan-500/10 to-blue-500/10 border border-cyan-500/20 p-3.5 shadow-lg">
                    <div class="flex justify-between text-xs">
                        <span class="font-bold text-gray-300">مخزون خط الإنتاج</span>
                        <span class="text-cyan-400 font-mono font-semibold">98%</span>
                    </div>
                    <div class="mt-2.5 h-2 rounded-full bg-slate-700/60 overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r from-cyan-400 to-blue-500 shadow-[0_0_10px_#06b6d4]" style="width: 98%"></div>
                    </div>
                </div>
            </div>

            <nav class="space-y-2.5">
                <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
                
                <a href="index.php" class="sidebar-link group flex items-center space-x-3 space-x-reverse py-3 px-4 text-sm font-semibold <?= $current_page == 'index.php' ? 'active' : 'text-gray-400 border border-transparent' ?>">
                    <i class="fa-solid fa-gauge-high w-5 text-center text-cyan-400 group-hover:scale-110 transition-transform"></i>
                    <span>لوحة القيادة</span>
                    <div class="ml-auto w-2 h-2 rounded-full bg-cyan-400 <?= $current_page == 'index.php' ? 'opacity-100 shadow-[0_0_8px_#22d3ee]' : 'opacity-0 group-hover:opacity-100' ?> transition"></div>
                </a>
                
                <a href="products.php" class="sidebar-link group flex items-center space-x-3 space-x-reverse py-3 px-4 text-sm font-semibold <?= $current_page == 'products.php' ? 'active' : 'text-gray-400 border border-transparent' ?>">
                    <i class="fa-solid fa-boxes-stacked w-5 text-center text-cyan-400 group-hover:scale-110 transition-transform"></i>
                    <span>إدارة الأصناف</span>
                    <div class="ml-auto w-2 h-2 rounded-full bg-cyan-400 <?= $current_page == 'products.php' ? 'opacity-100 shadow-[0_0_8px_#22d3ee]' : 'opacity-0 group-hover:opacity-100' ?> transition"></div>
                </a>
                
                <a href="transactions.php" class="sidebar-link group flex items-center space-x-3 space-x-reverse py-3 px-4 text-sm font-semibold <?= $current_page == 'transactions.php' ? 'active' : 'text-gray-400 border border-transparent' ?>">
                    <i class="fa-solid fa-clock-rotate-left w-5 text-center text-cyan-400 group-hover:scale-110 transition-transform"></i>
                    <span>سجل الحركات</span>
                    <div class="ml-auto w-2 h-2 rounded-full bg-cyan-400 <?= $current_page == 'transactions.php' ? 'opacity-100 shadow-[0_0_8px_#22d3ee]' : 'opacity-0 group-hover:opacity-100' ?> transition"></div>
                </a>
            </nav>
        </div>

        <div class="relative z-10 space-y-4 mt-6">
         
           <div class="relative rounded-2xl overflow-hidden border border-cyan-500/15 bg-gradient-to-br from-slate-900 to-slate-800 p-4 shadow-xl">
                <div class="absolute top-0 left-0 w-32 h-32 bg-cyan-400/10 blur-3xl pointer-events-none"></div>
                <div class="relative text-[11px]">
                    <div class="font-bold text-white flex justify-between items-center">
                        <span>Edition</span>
                        <span class="text-cyan-400 font-mono text-xs">v3.5</span>
                    </div>
                    <div class="mt-2 text-[10px] text-gray-400">Realtime Secure Connection</div>
                </div>
            </div>
        </div>
    </aside>

    <script>
        if (typeof tsParticles !== 'undefined') {
            tsParticles.load("particles-js", {
                particles: {
                    number: { value: 30 },
                    color: { value: "#06b6d4" },
                    move: { enable: true, speed: 0.6 },
                    links: { enable: true, color: "#06b6d4", opacity: 0.1 },
                    size: { value: 1.5 }
                }
            });
        }
    </script>

    <main class="flex-1 p-8 overflow-y-auto relative">