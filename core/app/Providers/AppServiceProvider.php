<?php

namespace App\Providers;

use App\Constants\Status;
use App\Lib\Searchable;
use App\Models\AdminNotification;
use App\Models\Category;
use App\Models\Deposit;
use App\Models\Frontend;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     */
    public function register(): void {
        Builder::mixin(new Searchable);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {
        if (!cache()->get('SystemInstalled')) {
            $envFilePath = base_path('.env');
            if (!file_exists($envFilePath)) {
                header('Location: install');
                exit;
            }
            $envContents = file_get_contents($envFilePath);
            if (empty($envContents)) {
                header('Location: install');
                exit;
            } else {
                cache()->put('SystemInstalled', true);
            }
        }

        $activeTemplate = activeTemplate();
        $viewShare['activeTemplate'] = $activeTemplate;
        $viewShare['activeTemplateTrue'] = activeTemplate(true);
        $viewShare['emptyMessage'] = 'Data not found';

        $viewShare['parentCategories'] = Category::isParent()
            ->with([
                'specialProducts.brand',
                'allSubcategories' => function($q) {
                    $q->orderBy('position');
                },
                'products' => function ($product) {
                    return $product->published();
                },
                'products.reviews',
                'products'
            ])
            ->orderBy('position')->get();

        view()->share($viewShare);

        view()->composer('admin.partials.sidenav', function ($view) {
            $view->with([
                'bannedUsersCount' => User::banned()->count(),
                'emailUnverifiedUsersCount' => User::emailUnverified()->count(),
                'mobileUnverifiedUsersCount' => User::mobileUnverified()->count(),
                'pendingTicketCount' => SupportTicket::whereIN('status', [Status::TICKET_OPEN, Status::TICKET_REPLY])->count(),
                'pendingDepositsCount' => Deposit::pending()->count(),

                'pendingOrdersCount' => Order::isValidOrder()->pending()->count(),
                'processingOrdersCount' => Order::isValidOrder()->processing()->count(),
                'dispatchedOrdersCount' => Order::isValidOrder()->dispatched()->count(),

                'lowStockProductsCount' => Product::lowStock()->count(),
                'outOfStockProductsCount' => Product::outOfStock()->count(),
                'pendingReviewsCount' => ProductReview::where('status', Status::REVIEW_PENDING)->count(),
                'updateAvailable' => version_compare(gs('available_version'), systemDetails()['version'], '>') ? 'v' . gs('available_version') : false,
            ]);
        });

        view()->composer('admin.partials.topnav', function ($view) {
            $view->with([
                'adminNotifications' => AdminNotification::where('is_read', Status::NO)->with('user')->orderBy('id', 'desc')->take(10)->get(),
                'adminNotificationCount' => AdminNotification::where('is_read', Status::NO)->count(),
            ]);
        });

        view()->composer('partials.seo', function ($view) {
            $seo = Frontend::where('data_keys', 'seo.data')->first();
            $view->with([
                'seo' => $seo ? $seo->data_values : $seo,
            ]);
        });

        if (gs('force_ssl')) {
            \URL::forceScheme('https');
        }

        // Create visitor_logs table if not exists
        try {
            if (!\Schema::hasTable('visitor_logs')) {
                \Schema::create('visitor_logs', function ($table) {
                    $table->id();
                    $table->string('ip', 45);
                    $table->string('device', 20);
                    $table->string('browser', 50);
                    $table->string('os', 50);
                    $table->date('visit_date');
                    $table->timestamps();
                    $table->unique(['ip', 'visit_date']);
                });
            }
        } catch (\Exception $e) {}

        // Log Frontend Visitor
        if (!request()->is('admin*') && !request()->is('api*') && request()->isMethod('GET') && !request()->ajax() && !request()->is('assets*')) {
            try {
                $ip = getRealIP();
                $date = date('Y-m-d');
                
                $exists = \DB::table('visitor_logs')->where('ip', $ip)->where('visit_date', $date)->exists();
                if (!$exists) {
                    $userAgent = request()->userAgent() ?? '';
                    $device = 'PC';
                    if (preg_match('/(tablet|ipad|playbook)|(android(?!.*mobi))/i', $userAgent)) {
                        $device = 'Tablet';
                    } elseif (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $userAgent)) {
                        $device = 'Mobile';
                    }
                    
                    $clientInfo = \App\Lib\ClientInfo::osBrowser();
                    
                    \DB::table('visitor_logs')->insert([
                        'ip' => $ip,
                        'device' => $device,
                        'browser' => $clientInfo['browser'] ?? 'Unknown',
                        'os' => $clientInfo['os_platform'] ?? 'Unknown',
                        'visit_date' => $date,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Exception $e) {}
        }

        // Daily Telegram Report
        $today = date('Y-m-d');
        $lastReportDate = \Cache::get('telegram_last_report_date');
        
        if ($lastReportDate !== $today) {
            try {
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $botToken = env('TELEGRAM_BOT_TOKEN');
                $chatId = env('TELEGRAM_CHAT_ID');
                
                if ($botToken && $chatId) {
                    $totalVisitors = \DB::table('visitor_logs')->where('visit_date', $yesterday)->count();
                    $deviceStats = \DB::table('visitor_logs')
                        ->where('visit_date', $yesterday)
                        ->select('device', \DB::raw('count(*) as total'))
                        ->groupBy('device')
                        ->get()
                        ->pluck('total', 'device')
                        ->toArray();
                    
                    $pcCount = $deviceStats['PC'] ?? 0;
                    $mobileCount = $deviceStats['Mobile'] ?? 0;
                    $tabletCount = $deviceStats['Tablet'] ?? 0;
                    
                    $totalOrders = Order::isValidOrder()->whereDate('created_at', $yesterday)->count();
                    $totalSales = Order::isValidOrder()->whereDate('created_at', $yesterday)->sum('total_amount');
                    $newUsers = User::whereDate('created_at', $yesterday)->count();
                    
                    $message = "📊 <b>Daily Website Report (" . date('d M Y', strtotime($yesterday)) . ")</b>\n";
                    $message .= "━━━━━━━━━━━━━━━━━━━\n\n";
                    $message .= "👥 <b>Traffic Overview:</b>\n";
                    $message .= "• Unique Visitors: <b>" . $totalVisitors . "</b>\n";
                    $message .= "• PC Visitors: <b>" . $pcCount . "</b>\n";
                    $message .= "• Mobile Visitors: <b>" . $mobileCount . "</b>\n";
                    if ($tabletCount > 0) {
                        $message .= "• Tablet Visitors: <b>" . $tabletCount . "</b>\n";
                    }
                    $message .= "\n🛒 <b>Sales & Orders:</b>\n";
                    $message .= "• Total Orders: <b>" . $totalOrders . "</b>\n";
                    $message .= "• Total Sales: <b>" . gs('cur_sym') . showAmount($totalSales, currencyFormat: false) . " " . gs('cur_text') . "</b>\n";
                    $message .= "\n👤 <b>User Registrations:</b>\n";
                    $message .= "• New Signups: <b>" . $newUsers . "</b>\n";
                    $message .= "\n━━━━━━━━━━━━━━━━━━━";
                    
                    $url = "https://api.telegram.org/bot" . $botToken . "/sendMessage";
                    $data = [
                        'chat_id' => $chatId,
                        'text' => $message,
                        'parse_mode' => 'HTML',
                        'disable_web_page_preview' => true,
                    ];

                    $options = [
                        'http' => [
                            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                            'method'  => 'POST',
                            'content' => http_build_query($data),
                            'ssl' => [
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                            ]
                        ]
                    ];

                    $context  = stream_context_create($options);
                    $result = @file_get_contents($url, false, $context);
                    
                    if ($result !== false) {
                        \Cache::put('telegram_last_report_date', $today);
                    }
                }
            } catch (\Exception $e) {}
        }

        Paginator::useBootstrapFive();
    }
}
