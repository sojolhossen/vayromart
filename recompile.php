<?php
// Bootstrap Laravel HTTP kernel
require_once __DIR__ . '/core/vendor/autoload.php';
$app = require_once __DIR__ . '/core/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Models\ChatbotLandingPage;
use App\Models\Product;
use App\Http\Controllers\Admin\AdminLandingController;

try {
    $pages = ChatbotLandingPage::all();
    if ($pages->isEmpty()) {
        echo "No landing pages found to recompile.";
        exit;
    }
    
    $controller = new AdminLandingController();
    $reflector = new \ReflectionClass(AdminLandingController::class);
    $method = $reflector->getMethod('compileManualTemplate');
    $method->setAccessible(true);

    foreach ($pages as $page) {
        $product = Product::find($page->product_id);
        if (!$product) {
            echo "Skipping Page: {$page->title} (Product not found)<br>";
            continue;
        }
        
        $settings = $page->design_settings;
        $settings['id'] = $page->id;
        
        $html = $method->invoke($controller, $settings, $product);
        
        $page->update([
            'content' => $html
        ]);
        
        echo "Successfully recompiled: <b>{$page->title}</b> (slug: {$page->slug})<br>";
    }
    echo "<br><b>All landing pages updated & recompiled successfully!</b>";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
