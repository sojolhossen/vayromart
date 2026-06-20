<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\ProductManager;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DigitalFile;
use App\Models\Media;
use App\Models\Product;
use App\Services\ProductValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller {

    // Inject an instance of ProductManager into the class
    private $productManager;

    public function __construct(ProductManager $productManager) {
        $this->productManager = $productManager;
    }

    /**
     * Show the form to add a new digital product.
     *
     * @return \Illuminate\View\View
     */
    public function create() {
        return $this->productForm("Add New Product");
    }

    /**
     * Show the form to edit an existing digital product.
     *
     * @param int $id Product ID
     * @return \Illuminate\View\View
     */
    public function edit($id) {
        $product = Product::with(['attributes', 'attributeValues.media'])->findOrFail($id);
        return $this->productForm("Edit Product", $product);
    }

    public function duplicate($id) {
        $product = Product::with(['attributes', 'attributeValues.media'])->findOrFail($id);

        $newProduct = $product->replicate();
        $newProduct->slug = createUniqueSlug($product->name, Product::class, 0);
        $newProduct->is_published = Status::NO;

        $newProduct->save();

        $productManager = new ProductManager();

        if ($newProduct->product_type == Status::PRODUCT_TYPE_VARIABLE) {
            $productManager->adjustProductAttributes($product->attributes->pluck('id')->toArray(), $newProduct, false);
            $productManager->adjustProductAttributeValues($product->attributeValues->unique('id')->pluck('id')->toArray(), $newProduct, false);
        }

        $productManager->adjustGalleryImages($product->galleryImages->pluck('id')->toArray(), $newProduct, false);
        $productManager->adjustCategories($product->categories->pluck('id')->toArray(), $newProduct, false);

        foreach ($product->productVariants as $variant) {
            $newVariant = $variant->replicate();
            $newVariant->product_id = $newProduct->id;
            $newVariant->save();
        }


        $notify[] = ['success', 'Product duplicated successfully'];
        return to_route('admin.products.edit', $newProduct->id)->withNotify($notify);
    }

    /**
     * Common method to set up data for rendering the product form.
     *
     * @param string $pageTitle Page title for the form
     * @param Product|null $product Product (for editing)
     * @return \Illuminate\View\View
     */
    public function productForm($pageTitle, $product = null) {
        $brands                 = Brand::orderBy('name')->get();
        $categories             = Category::with('allSubcategories')->isParent()->get();
        $attributes             = Attribute::with('attributeValues')->get();
        $productAttributes      = [];
        $attributeValues        = [];

        if ($product && $product->attributes->count()) {
            $productAttributes  = $product->attributes->pluck('id');
            $attributeValues    = $product->attributeValues->groupBy('attribute_id');
            $attributeValues    = $attributeValues->map->pluck('pivot.attribute_value_id')->all();
        }
        return view('admin.product.form.setting', compact('pageTitle', 'categories', 'brands', 'product', 'attributes', 'attributeValues', 'productAttributes'));
    }

    /**
     * Adjust the stock of a product based on the form data.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $productId Product ID
     * @return void
     */
    public function store(Request $request, $id = 0) {
        $productManager    = $this->productManager;
        $isUpdate          = $id ? true : false;

        if ($isUpdate) {
            $product = Product::find($id);
            if (!$product) {
                return errorResponse('Product not found');
            }
        } else {
            $product = new Product();
        }

        $validationService = new ProductValidationService();
        $validator         = $validationService->validateProduct($request, $product);
        if ($validator->fails()) {
            $data = [
                'isUpdate' => $isUpdate,
                'redirectTo' => $this->getRedirectUrl($product, $isUpdate)
            ];
            return errorResponse($validator->errors(), $data);
        }

        if ($request->gallery_images) {
            $requestGalleryImages = trim($request->gallery_images, ',');
            $galleryImages = explode(",", $requestGalleryImages);
            $existingImages = Media::whereIn('id', $galleryImages)->pluck('id')->toArray();
            // Check if all the requested IDs exist in the database
            if (count($galleryImages) !== count($existingImages)) {
                return errorResponse('Invalid images selected');
            }
        } else {
            $galleryImages = [];
        }


        $validationService->validateAttributeValues($request);
        $needAttributeAdjustment = $this->isAttributeAdjustmentNeeded($request, $product);
        $slug = createUniqueSlug($request->slug ?? $request->name, Product::class, $id);
        $request->merge(['slug' => $slug]);
        $digitalFileName = null;

        if ($request->is_downloadable && $request->hasFile('file') && $request->delivery_type == Status::DOWNLOAD_INSTANT && $request->product_type == Status::PRODUCT_TYPE_SIMPLE) {
            $digitalFileName = $productManager->uploadDigitalProductFile($request->file, $product->digitalFile->name ?? null);
        }

        // product stock log trackable or not
        $isTrackable = $this->checkStockTrackable($request->track_inventory, $request->in_stock, $product, $isUpdate);
        $changeQty = $isTrackable ? $this->getStockChangeQuantity($request->in_stock, $product, $isUpdate) : 0;

        // Assign the values of products table's columns
        $productManager->setProductEntities($request, $product);


        // create stock log after product save
        if ($isTrackable) {
            $string = Str::plural('product', abs($changeQty));
            $description = $changeQty > 0 ?  $changeQty . " $string added" : abs($changeQty) . " $string subtracted";
            $remark = $changeQty > 0 ? '+' : '-';
            $productManager->createStockLog($product, $changeQty, $description, null, $remark);
        }

        if ($needAttributeAdjustment) {
            $productAttributes = $product->product_type == Status::PRODUCT_TYPE_VARIABLE ? $request->product_attributes : [];
            $attributeValues = $product->product_type == Status::PRODUCT_TYPE_VARIABLE ? $request->attribute_values : [];
            $productManager->adjustProductAttributes($productAttributes, $product, $isUpdate);
            $attributeValues = array_merge(...$attributeValues);
            $productManager->adjustProductAttributeValues($attributeValues, $product, $isUpdate);
            $productManager->adjustProductVariants($product->id);
        }

        // Remove the old digital file if the delivery type changed from instant download to after sale and has old file
        // Also if the product variant from no variant to no variant
        if ($product->digitalFile && ($request->delivery_type == Status::DOWNLOAD_AFTER_SALE || $request->product_type == Status::PRODUCT_TYPE_VARIABLE)) {
            $productManager->removeDigitalProductFile($product->digitalFile->name);
            $product->digitalFile->delete();
        }

        if ($digitalFileName) {
            $digitalFile = $product->digitalFile ?? new DigitalFile();
            $digitalFile->name = $digitalFileName;
            $product->digitalFile()->save($digitalFile);
        }

        $productManager->adjustGalleryImages($galleryImages, $product, $isUpdate);

        $productManager->adjustCategories($request->categories, $product, $isUpdate);

        $this->saveProductVariants($request, $product);

        $message = $isUpdate ? 'Product updated successfully' : 'Product added successfully';

        return response()->json(['status' => 'success', 'message' => $message, 'isUpdate' => $isUpdate, 'redirectTo' => $this->getRedirectUrl($product, $isUpdate)]);
    }

    /**
     * Determine if attribute adjustment is needed for a product.
     *
     * This method checks if the product's attributes need to be adjusted based on
     * changes in the product type or if the product is a variable type.
     *
     * @param \Illuminate\Http\Request $request The incoming request containing product data
     * @param \App\Models\Product $product The product being checked
     * @return bool Returns true if attribute adjustment is needed, false otherwise
     */
    private function isAttributeAdjustmentNeeded($request, $product) {
        // When storing new product and product type is simple
        if (!$product->id && $request->product_type == Status::PRODUCT_TYPE_SIMPLE) {
            return false;
        }

        // When storing new product and product type is variable
        if (!$product->id && $request->product_type == Status::PRODUCT_TYPE_VARIABLE) {
            return true;
        }

        if ($product->id && $product->product_type != $request->product_type) {
            return true;
        }

        $oldAttributes = $product->attributeValues->pluck('id')->toArray();
        $newAttributes = array_merge(...array_values($request->attribute_values ?? []));

        if (array_diff($oldAttributes, $newAttributes) || array_diff($newAttributes, $oldAttributes)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the redirect URL after creating or updating a product.
     *
     * @param \App\Models\Product $product
     * @param bool $isUpdate Indicates whether the product is being updated
     * @return string
     */
    private function getRedirectUrl($product, $isUpdate) {
        return $isUpdate ? $product->editUrl() : route('admin.products.create');
    }

    /**
     * Soft delete a product.
     *
     * @param int $id Product ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function delete($id) {
        $product = Product::where('id', $id)->withTrashed()->first();
        $message = $this->productManager->deleteProduct($product);
        $notify[] = ['success', $message];
        return back()->withNotify($notify);
    }

    /**
     * Download the digital product file.
     *
     * @param int $id Digital File ID
     * @return \Illuminate\Http\Response
     */
    public function digitalDownload($id) {
        return $this->productManager->downloadDigitalProductFile($id);
    }

    /**
     * Generate product variants for a variable product.
     *
     * This method generates all possible combinations of attribute values for a variable product
     * and saves them as product variants.
     *
     * @param int $id Product ID
     * @return \Illuminate\Http\Response
     */
    public function generateVariants($id) {

        $product = Product::with([
            'attributeValues',
            'productVariants' => function ($variants) {
                $variants->withTrashed();
            }
        ])->find($id);

        if (!$product) {
            return errorResponse('Product not found');
        }

        if ($product->product_type != Status::PRODUCT_TYPE_VARIABLE) {
            return errorResponse('This product is not a variable product');
        }

        if ($product->attributeValues->count() == 0) {
            return errorResponse('This product has no attribute value yet');
        }

        $generatedVariants = $this->productManager->generateVariants($product->attributeValues);
        $this->productManager->saveProductVariants($generatedVariants, $product);

        return successResponse('Product variants generated successfully');
    }

    /**
     * Save generated product variants for a variable product.
     *
     * This method takes an array of generated variants and saves them to the database
     * as product variants associated with the given product.
     *
     * @param array $generatedVariants An array of generated product variants
     * @param \App\Models\Product $product The product to which the variants belong
     * @return void
     */
    private function saveProductVariants(Request $request, $product) {

        if (!$request->variants) {
            return; // No variants to save
        }

        $variants       = $product->productVariants;
        $minimumPrice   = null;


        foreach ($request->variants as $requestVariant) {
            $requestVariant            = (object) $requestVariant;
            $variant                   = $variants->where('id', $requestVariant->id)->first();

            $isUpdate = true;
            $isTrackable = $this->checkStockTrackable(@$requestVariant->track_inventory, $requestVariant->in_stock, $variant, $isUpdate);
            $changeQty = $isTrackable ? $this->getStockChangeQuantity($requestVariant->in_stock, $variant, $isUpdate) : 0;

            $variant->regular_price    = $requestVariant->regular_price ?? null;
            $variant->sale_price       = $requestVariant->sale_price ?? null;
            $variant->sale_starts_from = $requestVariant->sale_starts_from;
            $variant->sale_ends_at     = $requestVariant->sale_ends_at;
            $variant->sku              = $requestVariant->sku;
            $variant->main_image_id    = $requestVariant->main_image_id;
            $variant->manage_stock     = @$requestVariant->manage_stock ? Status::YES : Status::NO;
            $variant->track_inventory  = @$requestVariant->track_inventory ? Status::YES : Status::NO;
            $variant->show_stock       = @$requestVariant->show_stock ? Status::YES : Status::NO;
            $variant->in_stock         = @$requestVariant->in_stock ?? 0;
            $variant->alert_quantity   = @$requestVariant->alert_quantity ?? 0;
            $variant->is_published     = @$requestVariant->is_published ? Status::YES : Status::NO;
            $variant->save();

            if ($variant->is_published) {
                if ($minimumPrice == null && $variant->regular_price) {
                    $minimumPrice = $variant->regular_price;
                } elseif ($requestVariant->regular_price && $minimumPrice > $variant->regular_price) {
                    $minimumPrice = $variant->regular_price;
                }
            }


            if ($requestVariant->gallery_images) {
                $requestGalleryImages = trim($requestVariant->gallery_images, ',');
                $galleryImages = explode(",", $requestGalleryImages);
            } else {
                $galleryImages = [];
            }

            $productManager = $this->productManager;


            $productManager->adjustVariantGalleryImages($galleryImages, $variant);


            $digitalFileName    = null;

            if (@$requestVariant->file) {
                $digitalFileName = $this->productManager->uploadDigitalProductFile($requestVariant->file, $variant->digitalFile->name ?? null);
            }

            if ($digitalFileName) {
                $digitalFile = $variant->digitalFile ?? new DigitalFile();
                $digitalFile->name = $digitalFileName;
                $variant->digitalFile()->save($digitalFile);
            }

            if ($isTrackable) {
                $string = Str::plural('product', abs($changeQty));
                $description = $changeQty > 0 ?  $changeQty . " $string added" : abs($changeQty) . " $string subtracted";
                $remark = $changeQty > 0 ? '+' : '-';
                $productManager->createStockLog($product, $changeQty, $description, $variant, $remark);
            }
        }

        $product->regular_price = $minimumPrice;
        $product->save();
    }

    /**
     * check product should be trackable or not
     * @param $isTrackInventory Whether inventory tracking is enabled.
     * @param $quantity The updated stock quantity.
     * @param $product The product or variant instance.
     * @param $isUpdate if stock tracking is required, otherwise false.
     */
    private function checkStockTrackable($isTrackInventory, $quantity, $product, $isUpdate) {
        // If inventory tracking is disabled, no need to track stock.
        if (!$isTrackInventory) {
            return false;
        }

        // If it's a new product and tracking is enabled, track stock.
        if (!$isUpdate) {
            return true;
        }

        // If it's an update and stock quantity has changed, track stock.
        return $product->in_stock != $quantity;
    }

    /**
     * Get the quantity change in stock (added or subtracted).
     *
     * @param $requestQuantity The new quantity from the request.
     * @param $product The product instance whose stock is being updated.
     * @param $isUpdate Indicates whether the product is being updated.
     * @return int The quantity difference (added or subtracted).
     */
    private function getStockChangeQuantity($requestQuantity, $product, $isUpdate) {
        // If it's a new product, return the requested quantity as the added amount.
        if (!$isUpdate) {
            return $requestQuantity;
        }

        // If updating, return the difference between new and existing stock.
        return $requestQuantity - $product->in_stock;
    }

    /**
     * Switch the publish status of a product.
     *
     * This method toggles the `is_published` status of the specified product.
     * If the product is currently published, it will be unpublished and vice versa.
     *
     * @param int $id Product ID
     * @return \Illuminate\Http\JsonResponse A JSON response indicating the new publish status
     */
    public function switchPublishStatus($id) {
        $product         = Product::find($id);
        $product->is_published = !$product->is_published;
        $product->save();
        return successResponse($product->is_published ? 'Published' : 'Unpublished');
    }

    /**
     * Assign media to attribute values for a specific product.
     *
     * @param \Illuminate\Http\Request $request The incoming request containing attribute values and media IDs
     * @param int $id Product ID
     * @return \Illuminate\Http\RedirectResponse A redirect response back to the product edit page with a notification
     */
    public function assignMediaToAttributes(Request $request, $id) {
        $product = Product::findOrFail($id);

        $galleryImages = $product->galleryImages;

        if ($galleryImages->isEmpty()) {
            $notify[] = ['error', 'Invalid request'];
            return back()->withNotify($notify);
        }

        $mediaIdsArray = $galleryImages->pluck('id')->toArray();
        $mediaAttribute = $product->attributes->whereIn('type', [Status::ATTRIBUTE_TYPE_COLOR])->first();
        $mediaAttributeValues = $product->attributeValues->where('attribute_id', $mediaAttribute->id)->pluck('id')->toArray();

        $request->validate([
            'attribute_values' => 'required|array',
            'attribute_values.*.media_id' => 'nullable|in:' . implode(',', $mediaIdsArray),
            'attribute_values.*.attribute_value_id' => 'nullable|in:' . implode(',', $mediaAttributeValues),
        ]);

        $attributes = collect($request->attribute_values);

        $filteredAttributes = $attributes->filter(function ($item) {
            return isset($item['attribute_value_id']);
        });

        foreach ($filteredAttributes as $attribute) {
            $product->attributeValues()->updateExistingPivot(
                $attribute['attribute_value_id'],
                ['media_id' => $attribute['media_id']]
            );
        }

        // Provide a success response
        $notify[] = ['success', 'Media assigned successfully to attribute values'];
        return redirect()->to(route('admin.products.edit', $product->id) . '#media-content')->withNotify($notify);
    }

    public function mohasagorSyncPage()
    {
        $pageTitle = 'Mohasagor Product Sync';
        $categories = Category::orderBy('name')->get();
        
        $configPath = storage_path('app/mohasagor_config.json');
        $apiKey = '';
        $secretKey = '';
        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
            $apiKey = $config['api_key'] ?? '';
            $secretKey = $config['secret_key'] ?? '';
        }
        if (!$apiKey) {
            $apiKey = env('MOHASAGOR_API_KEY') ?: '';
        }
        if (!$secretKey) {
            $secretKey = env('MOHASAGOR_SECRET_KEY') ?: '';
        }

        $mohasagorCategories = [
            'Gadgets & Electronics' => ['total' => 540, 'available' => 59, 'out_of_stock' => 481],
            'Home & Lifestyle' => ['total' => 730, 'available' => 122, 'out_of_stock' => 608],
            'Winter' => ['total' => 399, 'available' => 0, 'out_of_stock' => 399],
            'Foods' => ['total' => 10, 'available' => 8, 'out_of_stock' => 2],
            'Men\'s Fashion' => ['total' => 690, 'available' => 133, 'out_of_stock' => 557],
            'Kids Zone' => ['total' => 124, 'available' => 2, 'out_of_stock' => 122],
            'Women\'s Fashion' => ['total' => 274, 'available' => 2, 'out_of_stock' => 272],
            'Watch' => ['total' => 12, 'available' => 0, 'out_of_stock' => 12],
            'Customize & Gift' => ['total' => 42, 'available' => 1, 'out_of_stock' => 41],
            'Other\'s' => ['total' => 28, 'available' => 3, 'out_of_stock' => 25],
            'Offer' => ['total' => 4, 'available' => 1, 'out_of_stock' => 3],
        ];
        return view('admin.product.mohasagor_sync', compact('pageTitle', 'mohasagorCategories', 'categories', 'apiKey', 'secretKey'));
    }

    public function mohasagorSaveCredentials(Request $request)
    {
        $request->validate([
            'api_key' => 'required|string',
            'secret_key' => 'required|string',
        ]);

        $configPath = storage_path('app/mohasagor_config.json');
        
        $config = [
            'api_key' => $request->api_key,
            'secret_key' => $request->secret_key,
        ];

        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));

        return response()->json([
            'success' => true,
            'message' => 'API Credentials saved successfully!'
        ]);
    }

    public function mohasagorTestConnection()
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
            return response()->json([
                'success' => false,
                'message' => 'API credentials are not configured.'
            ]);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->withHeaders([
                'API-KEY' => $apiKey,
                'SECRET-KEY' => $secretKey,
            ])->get("https://mohasagor.com.bd/api/reseller/product", [
                'page' => 1,
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Connected successfully!'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'API returned status ' . $response->status()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ]);
        }
    }

    public function mohasagorImportChunk(Request $request)
    {
        $category = $request->category;
        $limit = intval($request->limit ?: 10);
        $updateExisting = filter_var($request->update_existing, FILTER_VALIDATE_BOOLEAN);
        $page = intval($request->page ?: 1);
        $importedCount = intval($request->imported_count ?: 0);
        
        $localCategoryId = $request->local_category_id ?: null;
        $priceMarkupType = $request->price_markup_type ?: 'none';
        $priceMarkupValue = floatval($request->price_markup_value ?: 0);
        $publishStatus = $request->has('publish_status') ? intval($request->publish_status) : null;
        $outOfStockDefaultQty = intval($request->out_of_stock_default_qty ?? 100);
        
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
            return response()->json([
                'success' => false,
                'message' => 'Please configure your Mohasagor API Credentials in the configuration panel.'
            ]);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'API-KEY' => $apiKey,
                'SECRET-KEY' => $secretKey,
            ])->get("https://mohasagor.com.bd/api/reseller/product", [
                'page' => $page,
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => "Failed to fetch from Mohasagor API (Status " . $response->status() . ")"
                ]);
            }

            $data = $response->json();
            if (!isset($data['products']) || !is_array($data['products'])) {
                return response()->json([
                    'success' => true,
                    'finished' => true,
                    'current_page' => $page,
                    'last_page' => $page,
                    'imported_count' => $importedCount,
                    'logs' => ['No products found on this page. Finished.'],
                    'imported_products' => []
                ]);
            }

            $lastPage = $data['last_page'] ?? $page;
            $logs = [];
            $importedProducts = [];
            $finished = false;

            foreach ($data['products'] as $item) {
                // Check if we hit the user's limit
                if ($importedCount >= $limit) {
                    $finished = true;
                    break;
                }

                $itemCat = trim($item['category'] ?? '');
                if (strtolower($itemCat) === strtolower($category)) {
                    // Sync the product
                    $result = $this->syncMohasagorProduct($item, $updateExisting, $localCategoryId, $priceMarkupType, $priceMarkupValue, $publishStatus, $outOfStockDefaultQty);
                    
                    if ($result['status'] == 'created') {
                        $stockNote = ($result['api_stock_status'] !== 'available') ? " (API Out of Stock - Local Stock set to {$outOfStockDefaultQty})" : "";
                        $logs[] = "Successfully added: " . $result['name'] . " (SKU: " . $result['sku'] . ")" . $stockNote;
                        $importedCount++;
                        $importedProducts[] = [
                            'name' => $result['name'],
                            'sku' => $result['sku'],
                            'price' => $result['price'],
                            'image' => $result['image'],
                            'status' => 'Created'
                        ];
                    } elseif ($result['status'] == 'updated') {
                        $stockNote = ($result['api_stock_status'] !== 'available') ? " (API Out of Stock - Local Stock set to {$outOfStockDefaultQty})" : "";
                        $logs[] = "Successfully updated: " . $result['name'] . " (SKU: " . $result['sku'] . ")" . $stockNote;
                        $importedCount++;
                        $importedProducts[] = [
                            'name' => $result['name'],
                            'sku' => $result['sku'],
                            'price' => $result['price'],
                            'image' => $result['image'],
                            'status' => 'Updated'
                        ];
                    } elseif ($result['status'] == 'skipped') {
                        $logs[] = "Skipped (already exists): " . $result['name'] . " (SKU: " . $result['sku'] . ")";
                    }
                }
            }

            // If we reached the last page of the API, or we hit the limit, we are finished.
            if ($page >= $lastPage || $importedCount >= $limit) {
                $finished = true;
            }

            return response()->json([
                'success' => true,
                'finished' => $finished,
                'current_page' => $page,
                'last_page' => $lastPage,
                'imported_count' => $importedCount,
                'logs' => $logs,
                'imported_products' => $importedProducts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    private function syncMohasagorProduct($item, $updateExisting, $localCategoryId = null, $priceMarkupType = 'none', $priceMarkupValue = 0, $publishStatus = null, $outOfStockDefaultQty = 100)
    {
        $mohasagorId = $item['id'];
        $sku = 'MHS-' . $mohasagorId;

        $product = Product::where('sku', $sku)->first();
        $isNew = false;

        if (!$product) {
            $product = new Product();
            $product->sku = $sku;
            $isNew = true;
        } else {
            if (!$updateExisting) {
                return ['status' => 'skipped', 'name' => $item['name'], 'sku' => $sku];
            }
        }

        // Map basic fields
        $product->name = $item['name'];
        if ($isNew) {
            $product->slug = createUniqueSlug($item['name'], Product::class);
        }

        // Apply price markup
        $originalPrice = floatval($item['price']);
        $finalPrice = $originalPrice;
        if ($priceMarkupType === 'percent' && $priceMarkupValue > 0) {
            $finalPrice = $originalPrice + ($originalPrice * ($priceMarkupValue / 100));
        } elseif ($priceMarkupType === 'flat' && $priceMarkupValue > 0) {
            $finalPrice = $originalPrice + $priceMarkupValue;
        }
        
        $product->regular_price = round($finalPrice, 2);
        $product->sale_price = null; // No default discount unless set manually

        // Filter out Mohasagor's wholesale/dropship cost from extra_descriptions to keep it hidden
        $extraDesc = $product->extra_descriptions ?? [];
        if (!is_array($extraDesc)) {
            $extraDesc = [];
        }
        $newExtraDesc = [];
        foreach ($extraDesc as $val) {
            if (is_array($val) && isset($val['key'])) {
                if ($val['key'] !== 'dropship_price') {
                    $newExtraDesc[] = $val;
                }
            }
        }
        $product->extra_descriptions = $newExtraDesc;

        $product->description = $item['details'];
        $product->summary = strLimit(strip_tags($item['details']), 150);

        // Status mapping
        if ($publishStatus !== null) {
            $product->is_published = intval($publishStatus);
        } else {
            $product->is_published = ($item['status'] === 'active') ? 1 : 0;
        }
        
        // Stock mapping based on custom value if API reports stock_status != available
        $product->in_stock = ($item['stock_status'] === 'available') ? 100 : $outOfStockDefaultQty;

        // Vayromart defaults
        $product->track_inventory = 1;
        $product->show_stock = 1;
        $product->product_type_id = 0;
        $product->product_type = 1;
        $product->is_downloadable = 0;
        $product->show_in_products_page = 1;

        // Download main image (only if product is new or doesn't have image)
        if ($isNew || !$product->main_image_id) {
            $mainImageId = $this->downloadAndSaveMohasagorImage($item['thumbnail_img']);
            if ($mainImageId) {
                $product->main_image_id = $mainImageId;
            }
        }

        $product->save();

        // Category mapping
        if ($localCategoryId) {
            // Map directly to chosen local category
            $product->categories()->sync([$localCategoryId]);
        } else {
            // Auto create/match by name
            $categoryName = trim($item['category'] ?? 'Uncategorized');
            $category = Category::where('name', $categoryName)->first();
            if (!$category) {
                $category = new Category();
                $category->name = $categoryName;
                $category->slug = Str::slug($categoryName);
                $category->save();
            }
            $product->categories()->sync([$category->id]);
        }

        // Download gallery images (only if product is new or has no gallery images yet)
        if ($isNew || $product->galleryImages()->count() === 0) {
            if (isset($item['product_images']) && is_array($item['product_images'])) {
                $galleryIds = [];
                foreach ($item['product_images'] as $pImg) {
                    $gId = $this->downloadAndSaveMohasagorImage($pImg['product_image']);
                    if ($gId) {
                        $galleryIds[] = $gId;
                    }
                }
                if (!empty($galleryIds)) {
                    $product->galleryImages()->sync($galleryIds);
                }
            }
        }

        // Get main image path for the live UI summary table
        $imagePath = '';
        if ($product->main_image_id) {
            $media = Media::find($product->main_image_id);
            if ($media) {
                $imagePath = asset($media->path . '/' . $media->file_name);
            }
        }

        return [
            'status' => $isNew ? 'created' : 'updated',
            'name' => $item['name'],
            'sku' => $sku,
            'price' => $product->regular_price,
            'image' => $imagePath,
            'api_stock_status' => $item['stock_status']
        ];
    }

    private function downloadAndSaveMohasagorImage($url)
    {
        if (!$url) return null;

        try {
            $parsedUrl = parse_url($url, PHP_URL_PATH);
            $originalFileName = pathinfo($parsedUrl, PATHINFO_BASENAME);
            
            $existingMedia = Media::where('file_name', $originalFileName)->first();
            if ($existingMedia) {
                $physicalPath = base_path('../assets/images/product') . '/' . $existingMedia->file_name;
                if (file_exists($physicalPath)) {
                    return $existingMedia->id;
                }
            }

            $response = \Illuminate\Support\Facades\Http::timeout(30)->get($url);
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

            file_put_contents($physicalPath . '/' . $fileName, $imgData);

            try {
                $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                $manager->read($imgData)->resize(300, 300)->save($physicalPath . '/thumb_' . $fileName);
            } catch (\Exception $e) {
                copy($physicalPath . '/' . $fileName, $physicalPath . '/thumb_' . $fileName);
            }

            $media = new Media();
            $media->path = $dbPath;
            $media->file_name = $fileName;
            $media->save();

            return $media->id;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getMohasagorCatalog($refresh = false)
    {
        $cachePath = storage_path('app/mohasagor_products_cache.json');

        if (!$refresh && file_exists($cachePath) && (time() - filemtime($cachePath) < 12 * 3600)) {
            $data = json_decode(file_get_contents($cachePath), true);
            if (is_array($data)) {
                return $data;
            }
        }

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
            return null;
        }

        $allProducts = [];
        $page = 1;
        $lastPage = 1;

        try {
            do {
                $response = \Illuminate\Support\Facades\Http::timeout(20)->withHeaders([
                    'API-KEY' => $apiKey,
                    'SECRET-KEY' => $secretKey,
                ])->get("https://mohasagor.com.bd/api/reseller/product", [
                    'page' => $page,
                ]);

                if (!$response->successful()) {
                    break;
                }

                $data = $response->json();
                if (!isset($data['products']) || !is_array($data['products'])) {
                    break;
                }

                $allProducts = array_merge($allProducts, $data['products']);
                $lastPage = $data['last_page'] ?? 1;
                $page++;

                if ($page > 50) { // Safety break
                    break;
                }
            } while ($page <= $lastPage);

            if (!empty($allProducts)) {
                file_put_contents($cachePath, json_encode($allProducts));
                return $allProducts;
            }
        } catch (\Exception $e) {
            // Log exception if needed
        }

        if (file_exists($cachePath)) {
            return json_decode(file_get_contents($cachePath), true) ?: [];
        }

        return null;
    }

    public function mohasagorSearch(Request $request)
    {
        $query = strtolower(trim($request->q ?? ''));
        $category = trim($request->category ?? '');
        $refresh = filter_var($request->refresh, FILTER_VALIDATE_BOOLEAN);

        $products = $this->getMohasagorCatalog($refresh);

        if ($products === null) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve products from Mohasagor API. Please check your credentials.'
            ]);
        }

        $cachePath = storage_path('app/mohasagor_products_cache.json');
        $lastUpdated = file_exists($cachePath) ? date('d M Y, h:i A', filemtime($cachePath)) : 'Never';

        $results = [];

        if ($category !== '') {
            foreach ($products as $p) {
                if (strtolower($p['category'] ?? '') === strtolower($category)) {
                    $sku = 'MHS-' . $p['id'];
                    $localProduct = Product::where('sku', $sku)->first();
                    $p['is_imported'] = $localProduct ? true : false;
                    $p['local_id'] = $localProduct ? $localProduct->id : null;
                    $results[] = $p;
                }
            }
        } elseif ($query !== '') {
            foreach ($products as $p) {
                $nameMatch = str_contains(strtolower($p['name'] ?? ''), $query);
                $skuMatch = str_contains(strtolower($p['product_code'] ?? ''), $query) || str_contains(strtolower('MHS-' . ($p['id'] ?? '')), $query);
                $catMatch = str_contains(strtolower($p['category'] ?? ''), $query);

                if ($nameMatch || $skuMatch || $catMatch) {
                    $sku = 'MHS-' . $p['id'];
                    $localProduct = Product::where('sku', $sku)->first();
                    $p['is_imported'] = $localProduct ? true : false;
                    $p['local_id'] = $localProduct ? $localProduct->id : null;
                    $results[] = $p;
                }
            }
        } else {
            $slice = array_slice($products, 0, 50);
            foreach ($slice as $p) {
                $sku = 'MHS-' . $p['id'];
                $localProduct = Product::where('sku', $sku)->first();
                $p['is_imported'] = $localProduct ? true : false;
                $p['local_id'] = $localProduct ? $localProduct->id : null;
                $results[] = $p;
            }
        }

        return response()->json([
            'success' => true,
            'results' => $results,
            'total_catalog' => count($products),
            'count' => count($results),
            'last_updated' => $lastUpdated
        ]);
    }

    public function mohasagorImportSingle(Request $request)
    {
        $localCategoryId = $request->local_category_id ?: null;
        $priceMarkupType = $request->price_markup_type ?: 'none';
        $priceMarkupValue = floatval($request->price_markup_value ?: 0);
        $publishStatus = $request->has('publish_status') ? intval($request->publish_status) : null;
        $outOfStockDefaultQty = intval($request->out_of_stock_default_qty ?? 100);

        $isGeneric = filter_var($request->is_generic, FILTER_VALIDATE_BOOLEAN);

        if ($isGeneric) {
            $name = $request->name;
            $price = floatval($request->price);
            $salePrice = floatval($request->sale_price ?: $price);
            $imageUrl = $request->image;
            $details = $request->details;
            $sku = $request->sku ?: 'SCRP-' . abs(crc32($name));

            try {
                $product = Product::where('sku', $sku)->first();
                $isNew = false;
                if (!$product) {
                    $product = new Product();
                    $product->sku = $sku;
                    $isNew = true;
                }

                $product->name = $name;
                if ($isNew) {
                    $product->slug = createUniqueSlug($name, Product::class);
                }

                // Apply price markup
                $finalPrice = $price;
                if ($priceMarkupType === 'percent' && $priceMarkupValue > 0) {
                    $finalPrice = $price + ($price * ($priceMarkupValue / 100));
                } elseif ($priceMarkupType === 'flat' && $priceMarkupValue > 0) {
                    $finalPrice = $price + $priceMarkupValue;
                }

                $product->regular_price = round($finalPrice, 2);
                $product->sale_price = null;

                // Filter out wholesale/dropship cost from extra_descriptions to keep it hidden
                $extraDesc = $product->extra_descriptions ?? [];
                if (!is_array($extraDesc)) {
                    $extraDesc = [];
                }
                $newExtraDesc = [];
                foreach ($extraDesc as $val) {
                    if (is_array($val) && isset($val['key'])) {
                        if ($val['key'] !== 'dropship_price') {
                            $newExtraDesc[] = $val;
                        }
                    }
                }
                $product->extra_descriptions = $newExtraDesc;

                $product->description = $details ?: '';
                $product->summary = strLimit(strip_tags($details ?: ''), 150);

                if ($publishStatus !== null) {
                    $product->is_published = intval($publishStatus);
                } else {
                    $product->is_published = 1;
                }

                $product->in_stock = $outOfStockDefaultQty;
                $product->track_inventory = 1;
                $product->show_stock = 1;
                $product->product_type_id = 0;
                $product->product_type = 1;
                $product->is_downloadable = 0;
                $product->show_in_products_page = 1;

                if ($isNew || !$product->main_image_id) {
                    $mainImageId = $this->downloadAndSaveMohasagorImage($imageUrl);
                    if ($mainImageId) {
                        $product->main_image_id = $mainImageId;
                    }
                }

                $product->save();

                // Category mapping
                if ($localCategoryId) {
                    $product->categories()->sync([$localCategoryId]);
                } else {
                    $category = Category::first();
                    if ($category) {
                        $product->categories()->sync([$category->id]);
                    }
                }

                $imagePath = '';
                if ($product->main_image_id) {
                    $media = Media::find($product->main_image_id);
                    if ($media) {
                        $imagePath = asset($media->path . '/' . $media->file_name);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Scraped product ' . ($isNew ? 'imported' : 'updated') . ' successfully!',
                    'product' => [
                        'status' => $isNew ? 'created' : 'updated',
                        'name' => $product->name,
                        'sku' => $sku,
                        'price' => $product->regular_price,
                        'image' => $imagePath
                    ]
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Import failed: ' . $e->getMessage()
                ]);
            }
        }

        // Standard Mohasagor sync
        $request->validate([
            'product_id' => 'required',
        ]);
        $productId = $request->product_id;

        $products = $this->getMohasagorCatalog();
        $foundProduct = null;
        if ($products) {
            foreach ($products as $p) {
                if ($p['id'] == $productId) {
                    $foundProduct = $p;
                    break;
                }
            }
        }

        if (!$foundProduct) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found in cached catalog. Try refreshing.'
            ]);
        }

        try {
            $result = $this->syncMohasagorProduct(
                $foundProduct,
                true,
                $localCategoryId,
                $priceMarkupType,
                $priceMarkupValue,
                $publishStatus,
                $outOfStockDefaultQty
            );

            if ($result['status'] === 'created' || $result['status'] === 'updated') {
                return response()->json([
                    'success' => true,
                    'message' => 'Product ' . ($result['status'] === 'created' ? 'imported' : 'updated') . ' successfully!',
                    'product' => $result
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Product sync status: skipped or failed.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ]);
        }
    }

    public function mohasagorScrapeLink(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
        ]);

        $url = trim($request->url);

        // Check if URL is Mohasagor
        $isMohasagor = false;
        $host = parse_url($url, PHP_URL_HOST);
        if ($host && (str_contains(strtolower($host), 'mohasagor.com') || str_contains(strtolower($host), 'mohasagor.com.bd'))) {
            $isMohasagor = true;
        }

        if (!$isMohasagor) {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $html = curl_exec($ch);
                curl_close($ch);

                if (empty($html)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to retrieve page content from ' . $host
                    ]);
                }

                // 1. Name
                $name = '';
                if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\']([^"\']+)["\']/is', $html, $matches)) {
                    $name = html_entity_decode(trim($matches[1]));
                } elseif (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
                    $name = html_entity_decode(trim($matches[1]));
                }

                if (empty($name)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Could not extract product name from the page.'
                    ]);
                }

                // 2. Image
                $image = '';
                if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']+)["\']/is', $html, $matches)) {
                    $image = trim($matches[1]);
                }

                // 3. Description/Specs
                $description = '';
                if (preg_match('/<section[^>]*id="specification"[^>]*>(.*?)<\/section>/is', $html, $matches)) {
                    $description = $matches[1];
                } elseif (preg_match('/<div[^>]*class="[^"]*specification[^"]*"[^>]*>(.*?)<\/div>/is', $html, $matches)) {
                    $description = $matches[1];
                } elseif (preg_match('/<div[^>]*id="tab-description"[^>]*>(.*?)<\/div>/is', $html, $matches)) {
                    $description = $matches[1];
                } elseif (preg_match('/<div[^>]*class="[^"]*description[^"]*"[^>]*>(.*?)<\/div>/is', $html, $matches)) {
                    $description = $matches[1];
                } elseif (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\']([^"\']+)["\']/is', $html, $matches)) {
                    $description = html_entity_decode(trim($matches[1]));
                }
                $description = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $description);

                // 4. Price
                $price = 0;
                if (preg_match('/class="[^"]*(?:price-new|special-price|cash-price)[^"]*"[^>]*>\s*([0-9,.]+)\s*(?:৳|BDT|Tk|tk|Taka)?/is', $html, $matches)) {
                    $price = floatval(str_replace(',', '', $matches[1]));
                } elseif (preg_match('/<meta\s+property=["\']product:price:amount["\']\s+content=["\']([^"\']+)["\']/is', $html, $matches)) {
                    $price = floatval($matches[1]);
                } elseif (preg_match('/<meta\s+property=["\']price["\']\s+content=["\']([^"\']+)["\']/is', $html, $matches)) {
                    $price = floatval($matches[1]);
                } elseif (preg_match('/class="[^"]*(?:product-price|price|amount)[^"]*"[^>]*>\s*([0-9,.]+)\s*(?:৳|BDT|Tk|tk|Taka)?/is', $html, $matches)) {
                    $price = floatval(str_replace(',', '', $matches[1]));
                } elseif (preg_match('/([0-9,.]+)\s*(?:৳|BDT|Tk|tk|Taka)/is', $html, $matches)) {
                    $price = floatval(str_replace(',', '', $matches[1]));
                }

                $scrapedId = abs(crc32($url));
                $sku = 'SCRP-' . $scrapedId;

                $localProduct = Product::where('sku', $sku)->first();
                
                $results = [[
                    'id' => $scrapedId,
                    'name' => $name,
                    'price' => $price,
                    'sale_price' => $price,
                    'thumbnail_img' => $image,
                    'details' => $description,
                    'category' => 'External Web',
                    'stock_status' => 'available',
                    'is_imported' => $localProduct ? true : false,
                    'local_id' => $localProduct ? $localProduct->id : null,
                    'product_code' => $sku,
                    'is_generic' => true
                ]];

                return response()->json([
                    'success' => true,
                    'results' => $results,
                    'count' => count($results)
                ]);

            } catch (\Exception $ex) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to scrape external URL: ' . $ex->getMessage()
                ]);
            }
        }

        // Existing Mohasagor logic
        $singleId = null;
        if (preg_match('/(?:\/product\/|\/product-details\/|\/details\/|MHS-)(\d+)/i', $url, $matches)) {
            if (str_contains($url, '/product/') || str_contains($url, '/product-details/') || str_contains($url, '/details/')) {
                $singleId = $matches[1];
            }
        }

        $productIds = [];
        if ($singleId) {
            $productIds[] = intval($singleId);
        } else {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(15)->get($url);
                if ($response->successful()) {
                    $html = $response->body();
                    
                    preg_match_all('/(?:\/product\/|\/product-details\/|\/details\/|MHS-)(\d+)/i', $html, $matches);
                    if (!empty($matches[1])) {
                        foreach ($matches[1] as $id) {
                            $productIds[] = intval($id);
                        }
                    }
                    
                    preg_match_all('/data-product-id=["\'](\d+)["\']/i', $html, $matchesData);
                    if (!empty($matchesData[1])) {
                        foreach ($matchesData[1] as $id) {
                            $productIds[] = intval($id);
                        }
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to fetch the URL page. HTTP Status Code: ' . $response->status()
                    ]);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to scan the URL. Please verify the URL is valid and accessible. Error: ' . $e->getMessage()
                ]);
            }
        }

        $productIds = array_values(array_unique($productIds));

        if (empty($productIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No Mohasagor products found on the specified page.'
            ]);
        }

        $products = $this->getMohasagorCatalog();
        $missingSome = false;
        if ($products) {
            $cachedIds = array_column($products, 'id');
            foreach ($productIds as $pid) {
                if (!in_array($pid, $cachedIds)) {
                    $missingSome = true;
                    break;
                }
            }
        } else {
            $missingSome = true;
        }

        if ($missingSome) {
            $products = $this->getMohasagorCatalog(true);
        }

        if (!$products) {
            return response()->json([
                'success' => false,
                'message' => 'Mohasagor API catalog could not be retrieved.'
            ]);
        }

        $results = [];
        foreach ($products as $p) {
            if (in_array(intval($p['id']), $productIds)) {
                $sku = 'MHS-' . $p['id'];
                $localProduct = Product::where('sku', $sku)->first();
                $p['is_imported'] = $localProduct ? true : false;
                $p['local_id'] = $localProduct ? $localProduct->id : null;
                $results[] = $p;
            }
        }

        return response()->json([
            'success' => true,
            'results' => $results,
            'count' => count($results)
        ]);
    }

    public function bulkCategoryUpdate(Request $request) {
        $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|integer|exists:products,id',
            'categories' => 'required|array|min:1',
            'categories.*' => 'required|integer|exists:categories,id',
        ]);

        $productIds = $request->product_ids;
        $categoryIds = $request->categories;

        foreach ($productIds as $productId) {
            $product = Product::findOrFail($productId);
            $this->productManager->adjustCategories($categoryIds, $product, true);
        }

        $notify[] = ['success', 'Category updated successfully for selected products'];
        return back()->withNotify($notify);
    }
}
