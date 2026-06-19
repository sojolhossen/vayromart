<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Product;
use App\Models\Category;
use App\Models\Media;
use App\Constants\Status;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class SyncMohasagorProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:mohasagor {--limit= : Limit the number of products to sync for testing} {--out-of-stock-qty=100 : Default stock for out-of-stock products}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize products from Mohasagor API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $configPath = storage_path('app/mohasagor_config.json');
        $apiKey = null;
        $secretKey = null;
        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
            $apiKey = $config['api_key'] ?? null;
            $secretKey = $config['secret_key'] ?? null;
        }
        if (!$apiKey) {
            $apiKey = env('MOHASAGOR_API_KEY');
        }
        if (!$secretKey) {
            $secretKey = env('MOHASAGOR_SECRET_KEY');
        }

        if (!$apiKey || !$secretKey) {
            $this->error('Please configure your Mohasagor API Credentials in the admin panel or define them in the .env file.');
            return Command::FAILURE;
        }

        $limit = $this->option('limit');
        $outOfStockQty = intval($this->option('out-of-stock-qty') ?? 100);
        if ($limit) {
            $this->info("Starting product sync with limit of {$limit} products (Default stock for out-of-stock: {$outOfStockQty})...");
        } else {
            $this->info("Starting full product sync from Mohasagor (Default stock for out-of-stock: {$outOfStockQty})...");
        }

        $page = 1;
        $lastPage = 1;
        $syncedCount = 0;

        do {
            $this->info("Fetching page {$page}...");
            $response = Http::withHeaders([
                'API-KEY' => $apiKey,
                'SECRET-KEY' => $secretKey,
            ])->get("https://mohasagor.com.bd/api/reseller/product", [
                'page' => $page,
            ]);

            if (!$response->successful()) {
                $this->error("Failed to fetch page {$page}: Status " . $response->status());
                break;
            }

            $data = $response->json();
            if (!isset($data['products']) || !is_array($data['products'])) {
                $this->error("No products found in response for page {$page}.");
                break;
            }

            $lastPage = $data['last_page'] ?? 1;

            foreach ($data['products'] as $item) {
                if ($limit && $syncedCount >= $limit) {
                    $this->info("Sync limit of {$limit} reached. Stopping.");
                    break 2;
                }

                $this->syncProduct($item, $outOfStockQty);
                $syncedCount++;
            }

            $page++;
        } while ($page <= $lastPage);

        $this->info("Sync completed! Total products processed: {$syncedCount}");
        return Command::SUCCESS;
    }

    /**
     * Sync a single product from API payload to local database.
     */
    private function syncProduct($item, $outOfStockQty = 100)
    {
        $mohasagorId = $item['id'];
        $sku = 'MHS-' . $mohasagorId;

        $this->info("Syncing product SKU: {$sku} - " . $item['name']);

        // 1. Find or create the product
        $product = Product::where('sku', $sku)->first();
        $isNew = false;

        if (!$product) {
            $product = new Product();
            $product->sku = $sku;
            $isNew = true;
        }

        // 2. Map basic fields
        $product->name = $item['name'];
        if ($isNew) {
            $product->slug = createUniqueSlug($item['name'], Product::class);
        }

        // Customer retail price is Mohasagor's 'price'
        $product->regular_price = $item['price'];
        $product->sale_price = null; // No default discount unless set manually

        // Save Mohasagor's 'sale_price' as wholesale/dropship cost privately in extra_descriptions
        $extraDesc = $product->extra_descriptions ?? [];
        if (!is_array($extraDesc)) {
            $extraDesc = [];
        }
        $newExtraDesc = [];
        $found = false;
        foreach ($extraDesc as $key => $val) {
            if ($key === 'dropship_price') {
                $newExtraDesc[] = [
                    'key' => 'dropship_price',
                    'value' => (string)$val
                ];
                $found = true;
            } elseif (is_array($val) && isset($val['key'])) {
                if ($val['key'] === 'dropship_price') {
                    $newExtraDesc[] = [
                        'key' => 'dropship_price',
                        'value' => (string)$item['sale_price']
                    ];
                    $found = true;
                } else {
                    $newExtraDesc[] = $val;
                }
            }
        }
        if (!$found) {
            $newExtraDesc[] = [
                'key' => 'dropship_price',
                'value' => (string)$item['sale_price']
            ];
        }
        $product->extra_descriptions = $newExtraDesc;

        $product->description = $item['details'];
        $product->summary = strLimit(strip_tags($item['details']), 150);

        // Status mapping
        $product->is_published = ($item['status'] === 'active') ? 1 : 0;
        $product->in_stock = ($item['stock_status'] === 'available') ? 100 : $outOfStockQty;

        // Vayromart defaults
        $product->track_inventory = 1;
        $product->show_stock = 1;
        $product->product_type_id = 0;
        $product->product_type = 1;
        $product->is_downloadable = 0;
        $product->show_in_products_page = 1;

        // 3. Category mapping
        $categoryName = trim($item['category'] ?? 'Uncategorized');
        $category = Category::where('name', $categoryName)->first();
        if (!$category) {
            $category = new Category();
            $category->name = $categoryName;
            $category->slug = Str::slug($categoryName);
            $category->save();
        }

        // 4. Download main image (only if product is new or doesn't have image)
        if ($isNew || !$product->main_image_id) {
            $mainImageId = $this->downloadAndSaveImage($item['thumbnail_img']);
            if ($mainImageId) {
                $product->main_image_id = $mainImageId;
            }
        }

        $product->save();

        // 5. Sync category relationship
        $product->categories()->sync([$category->id]);

        // 6. Download gallery images (only if product is new or has no gallery images yet)
        if ($isNew || $product->galleryImages()->count() === 0) {
            if (isset($item['product_images']) && is_array($item['product_images'])) {
                $galleryIds = [];
                foreach ($item['product_images'] as $pImg) {
                    $gId = $this->downloadAndSaveImage($pImg['product_image']);
                    if ($gId) {
                        $galleryIds[] = $gId;
                    }
                }
                if (!empty($galleryIds)) {
                    $product->galleryImages()->sync($galleryIds);
                }
            }
        }
    }

    /**
     * Download an image, save it locally, generate a thumbnail, and insert a media record.
     */
    private function downloadAndSaveImage($url)
    {
        if (!$url) return null;

        try {
            // Check if we already downloaded this image (to prevent duplicates)
            $parsedUrl = parse_url($url, PHP_URL_PATH);
            $originalFileName = pathinfo($parsedUrl, PATHINFO_BASENAME);
            
            // Check if a media file with this name already exists in assets/images/product
            $existingMedia = Media::where('file_name', $originalFileName)->first();
            if ($existingMedia) {
                // Verify if the file actually exists physically in the public directory
                $physicalPath = base_path('../assets/images/product') . '/' . $existingMedia->file_name;
                if (file_exists($physicalPath)) {
                    return $existingMedia->id;
                }
            }

            // Download the image
            $response = Http::timeout(30)->get($url);
            if (!$response->successful()) return null;

            $imgData = $response->body();
            if (empty($imgData)) return null;

            $ext = pathinfo($parsedUrl, PATHINFO_EXTENSION) ?: 'jpg';
            $fileName = uniqid() . '_' . time() . '.' . $ext;
            
            $dbPath = 'assets/images/product';
            $physicalPath = base_path('../assets/images/product');

            if (!file_exists($physicalPath)) {
                mkdir($physicalPath, 0755, true);
            }

            // Save main image
            file_put_contents($physicalPath . '/' . $fileName, $imgData);

            // Create thumbnail (300x300)
            try {
                $manager = new ImageManager(new Driver());
                $manager->read($imgData)->resize(300, 300)->save($physicalPath . '/thumb_' . $fileName);
            } catch (\Exception $e) {
                // Fallback copy if Intervention fails
                copy($physicalPath . '/' . $fileName, $physicalPath . '/thumb_' . $fileName);
            }

            // Save to media table
            $media = new Media();
            $media->path = $dbPath;
            $media->file_name = $fileName;
            $media->save();

            return $media->id;
        } catch (\Exception $e) {
            $this->error("Failed to download image: " . $url . " - " . $e->getMessage());
            return null;
        }
    }
}
