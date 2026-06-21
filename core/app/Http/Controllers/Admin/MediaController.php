<?php

namespace  App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Rules\FileTypeValidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MediaController extends Controller {

    public function media() {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('general_settings', 'webp_auto_convert')) {
            try {
                \Illuminate\Support\Facades\Schema::table('general_settings', function ($table) {
                    $table->tinyInteger('webp_auto_convert')->default(1);
                });
                \Illuminate\Support\Facades\Cache::forget('GeneralSetting');
            } catch (\Exception $e) {
                \Log::error("Failed to auto-create webp_auto_convert in MediaController: " . $e->getMessage());
            }
        }

        try {
            $this->repairDatabaseReferences();
        } catch (\Exception $e) {
            \Log::error("Failed to repair database image references: " . $e->getMessage());
        }

        $pageTitle = 'Product Images';
        $mediaFiles = Media::searchable(['file_name'])
            ->withCount('products')
            ->withCount('productImages')
            ->withCount('productVariants')
            ->withCount('productVariantImages');

        if (request()->has('status')) {
            $status = request()->status;
            if ($status == 'used') {
                $mediaFiles->where(function($q) {
                    $q->has('products')
                      ->orHas('productImages')
                      ->orHas('productVariants')
                      ->orHas('productVariantImages');
                });
            } elseif ($status == 'unused') {
                $mediaFiles->doesntHave('products')
                    ->doesntHave('productImages')
                    ->doesntHave('productVariants')
                    ->doesntHave('productVariantImages');
            }
        }

        if (request()->has('format') && request()->format != '') {
            $mediaFiles->where('file_name', 'LIKE', '%.' . request()->format);
        }

        if (request()->has('order_by')) {
            try {
                $orderBy = explode('::', request()->order_by);
                $mediaFiles->orderBy($orderBy[0], $orderBy[1]);
            } catch (\Exception $e) {
                $notify[] = ['error', 'Invalid data'];
                return back()->withNotify($notify);
            }
        }else{
            $mediaFiles->orderBy('id', 'desc');
        }

        $mediaFiles = $mediaFiles->paginate(48);

        return view('admin.uploaded_files', compact('pageTitle', 'mediaFiles'));
    }

    public function mediaFiles() {
        $mediaFiles = Media::orderBy('id', 'desc')->paginate(33);
        return response()->json($mediaFiles);
    }

    public function deleteUnused() {
        $unusedMedia = Media::doesntHave('products')
            ->doesntHave('productImages')
            ->doesntHave('productVariants')
            ->doesntHave('productVariantImages')
            ->get();

        $count = 0;
        foreach ($unusedMedia as $media) {
            try {
                fileManager()->removeFile($media->path . '/' . @$media->file_name);
                fileManager()->removeFile($media->path . '/thumb_' . @$media->file_name);
                $media->delete();
                $count++;
            } catch (\Exception $e) {
                // Ignore error if file doesn't exist
            }
        }

        $notify[] = ['success', $count . ' unused files deleted successfully'];
        return back()->withNotify($notify);
    }

    public function upload(Request $request) {
        $validator = Validator::make($request->all(), [
            'photos'           => 'required|array|max:20',
            'photos.*'         => ['required', 'image', new FileTypeValidate(['jpeg', 'jpg', 'png', 'webp'])],
            'files_for'        => 'required|in:product,category,categoryIcon,brand'
        ], [
            'photos.required' => 'Please upload at least one image',
        ]);

        if ($validator->fails()) {
            return errorResponse($validator->errors());
        }

        $uploaded = [];

        $filesFor = $request->files_for;

        foreach ($request->photos as $photo) {

            $originalName = $photo->getClientOriginalName();
            $filename = pathinfo($originalName, PATHINFO_FILENAME);
            $extension = $photo->getClientOriginalExtension();
            $path = getFilePath($filesFor);

            $counter = 0;
            $targetExt = $extension;
            if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'webp'])) {
                $targetExt = 'webp';
            }

            while (file_exists($path . '/' . $filename . ($counter ? '(' . $counter . ')' : '') . '.' . $targetExt)) {
                $counter++;
            }
            $newFilename = $filename . ($counter ? '(' . $counter . ')' : '') . '.' . $extension;

            $media            = new Media();
            $media->path      = getFilePath($filesFor);
            $media->file_name = fileUploader($photo, getFilePath($filesFor), getFileSize($filesFor), null, getThumbSize($filesFor), $newFilename);
            $media->save();
            $uploaded[] = $media;
        }

        return successResponse('Uploaded successfully', ['uploaded' => $uploaded]);
    }

    function delete($id) {

        try {
            $media = Media::find($id);
            fileManager()->removeFile($media->path . '/' . @$media->file_name);
            fileManager()->removeFile($media->path . '/thumb_' . @$media->file_name);
            $media->delete();
        } catch (\Exception $e) {
            $notify[] = ['error', 'File not found'];
            return back()->withNotify($notify);
        }

        $notify[] = ['success', 'Deleted successfully'];
        return back()->withNotify($notify);
    }

    public function toggleWebpConvert(Request $request) {
        $general = gs();
        $general->webp_auto_convert = $request->value ? 1 : 0;
        $general->save();

        \Illuminate\Support\Facades\Cache::forget('GeneralSetting');

        return response()->json(['success' => true, 'message' => 'WebP auto-conversion setting updated successfully']);
    }

    public function initBulkConvert() {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('general_settings', 'webp_auto_convert')) {
            try {
                \Illuminate\Support\Facades\Schema::table('general_settings', function ($table) {
                    $table->tinyInteger('webp_auto_convert')->default(1);
                });
                \Illuminate\Support\Facades\Cache::forget('GeneralSetting');
            } catch (\Exception $e) {
                \Log::error("Failed to auto-create webp_auto_convert in MediaController: " . $e->getMessage());
            }
        }

        $ids = Media::where('file_name', 'NOT LIKE', '%.webp')->pluck('id');

        return response()->json([
            'success' => true,
            'ids' => $ids,
            'total' => count($ids)
        ]);
    }

    public function stepBulkConvert(Request $request) {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Invalid ID.']);
        }

        $media = Media::find($request->id);
        if (!$media) {
            return response()->json(['success' => false, 'message' => 'Media file not found.']);
        }

        $filename = $media->file_name;
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        if (strtolower($extension) === 'webp') {
            return response()->json(['success' => true, 'message' => 'Already WebP.', 'filename' => $filename]);
        }

        $oldPath = $media->path . '/' . $filename;
        $oldThumbPath = $media->path . '/thumb_' . $filename;

        if (!file_exists($oldPath) || !is_file($oldPath)) {
            return response()->json(['success' => false, 'message' => 'Physical file does not exist on server.', 'filename' => $filename]);
        }

        try {
            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            
            $newFilename = pathinfo($filename, PATHINFO_FILENAME) . '.webp';
            $newPath = $media->path . '/' . $newFilename;
            $newThumbPath = $media->path . '/thumb_' . $newFilename;

            // 1. Convert main image
            $image = $manager->read($oldPath);
            $image->save($newPath);

            // 2. Convert thumb image if it exists
            if (file_exists($oldThumbPath) && is_file($oldThumbPath)) {
                $thumbImage = $manager->read($oldThumbPath);
                $thumbImage->save($newThumbPath);
                @unlink($oldThumbPath);
            }

            // Delete old main file
            @unlink($oldPath);

            // 3. Update database record
            $media->file_name = $newFilename;
            $media->save();

            // Update Category image/icon references
            \App\Models\Category::where('image', $filename)->update(['image' => $newFilename]);
            \App\Models\Category::where('icon', $filename)->update(['icon' => $newFilename]);

            // Update Brand logo references
            \App\Models\Brand::where('logo', $filename)->update(['logo' => $newFilename]);

            // Update Product descriptions/summaries/extra_descriptions
            \App\Models\Product::where('description', 'LIKE', '%' . $filename . '%')
                ->get()
                ->each(function($prod) use ($filename, $newFilename) {
                    $prod->description = str_replace($filename, $newFilename, $prod->description);
                    $prod->save();
                });

            \App\Models\Product::where('summary', 'LIKE', '%' . $filename . '%')
                ->get()
                ->each(function($prod) use ($filename, $newFilename) {
                    $prod->summary = str_replace($filename, $newFilename, $prod->summary);
                    $prod->save();
                });

            \Illuminate\Support\Facades\DB::table('products')
                ->where('extra_descriptions', 'LIKE', '%' . $filename . '%')
                ->get()
                ->each(function($prod) use ($filename, $newFilename) {
                    $extraDecs = str_replace($filename, $newFilename, $prod->extra_descriptions);
                    \Illuminate\Support\Facades\DB::table('products')
                        ->where('id', $prod->id)
                        ->update(['extra_descriptions' => $extraDecs]);
                });

            \Illuminate\Support\Facades\DB::table('frontends')
                ->where('data_values', 'LIKE', '%' . $filename . '%')
                ->get()
                ->each(function($front) use ($filename, $newFilename) {
                    $dataValues = str_replace($filename, $newFilename, $front->data_values);
                    \Illuminate\Support\Facades\DB::table('frontends')
                        ->where('id', $front->id)
                        ->update(['data_values' => $dataValues]);
                });

            return response()->json([
                'success' => true,
                'message' => "Converted {$filename} to WebP successfully.",
                'filename' => $filename
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to convert media ID {$media->id} to WebP: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "Error: " . $e->getMessage(),
                'filename' => $filename
            ]);
        }
    }

    public function deleteSelected(Request $request) {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'required|integer'
        ]);

        if ($validator->fails()) {
            $notify[] = ['error', 'No images selected or invalid IDs.'];
            return back()->withNotify($notify);
        }

        $mediaFiles = Media::whereIn('id', $request->ids)->get();
        $count = 0;

        foreach ($mediaFiles as $media) {
            try {
                fileManager()->removeFile($media->path . '/' . @$media->file_name);
                fileManager()->removeFile($media->path . '/thumb_' . @$media->file_name);
                $media->delete();
                $count++;
            } catch (\Exception $e) {
                // Ignore error if file doesn't exist
            }
        }

        $notify[] = ['success', $count . ' selected files deleted successfully'];
        return back()->withNotify($notify);
    }

    private function repairDatabaseReferences() {
        $webpFiles = Media::where('file_name', 'LIKE', '%.webp')->pluck('file_name')->toArray();
        $webpByBasename = [];
        foreach ($webpFiles as $file) {
            $base = strtolower(pathinfo($file, PATHINFO_FILENAME));
            $webpByBasename[$base] = $file;
        }

        // 1. Repair Category image & icon
        $categories = \App\Models\Category::all();
        foreach ($categories as $cat) {
            $changed = false;
            if ($cat->image) {
                $ext = strtolower(pathinfo($cat->image, PATHINFO_EXTENSION));
                if ($ext !== 'webp') {
                    $base = strtolower(pathinfo($cat->image, PATHINFO_FILENAME));
                    if (isset($webpByBasename[$base])) {
                        $cat->image = $webpByBasename[$base];
                        $changed = true;
                    }
                }
            }
            if ($cat->icon) {
                $ext = strtolower(pathinfo($cat->icon, PATHINFO_EXTENSION));
                if ($ext !== 'webp') {
                    $base = strtolower(pathinfo($cat->icon, PATHINFO_FILENAME));
                    if (isset($webpByBasename[$base])) {
                        $cat->icon = $webpByBasename[$base];
                        $changed = true;
                    }
                }
            }
            if ($changed) {
                $cat->save();
            }
        }

        // 2. Repair Brand logo
        $brands = \App\Models\Brand::all();
        foreach ($brands as $brand) {
            if ($brand->logo) {
                $ext = strtolower(pathinfo($brand->logo, PATHINFO_EXTENSION));
                if ($ext !== 'webp') {
                    $base = strtolower(pathinfo($brand->logo, PATHINFO_FILENAME));
                    if (isset($webpByBasename[$base])) {
                        $brand->logo = $webpByBasename[$base];
                        $brand->save();
                    }
                }
            }
        }

        // Helper function for in-memory string search and replace
        $replaceFunc = function($text, $webpByBasename, &$changed) {
            if (empty($text)) return $text;
            return preg_replace_callback('#([^/\\\\\'"\s>]+)\.(?:png|jpg|jpeg)#i', function($matches) use ($webpByBasename, &$changed) {
                $original = $matches[0];
                $base = strtolower($matches[1]);
                if (isset($webpByBasename[$base])) {
                    $changed = true;
                    return $webpByBasename[$base];
                }
                return $original;
            }, $text);
        };

        // 3. Repair Products (description, summary, extra_descriptions)
        $products = \App\Models\Product::where(function($q) {
            $q->where('description', 'LIKE', '%.png%')
              ->orWhere('description', 'LIKE', '%.jpg%')
              ->orWhere('description', 'LIKE', '%.jpeg%')
              ->orWhere('summary', 'LIKE', '%.png%')
              ->orWhere('summary', 'LIKE', '%.jpg%')
              ->orWhere('summary', 'LIKE', '%.jpeg%')
              ->orWhere('extra_descriptions', 'LIKE', '%.png%')
              ->orWhere('extra_descriptions', 'LIKE', '%.jpg%')
              ->orWhere('extra_descriptions', 'LIKE', '%.jpeg%');
        })->get();

        foreach ($products as $prod) {
            $changed = false;
            if ($prod->description) {
                $prod->description = $replaceFunc($prod->description, $webpByBasename, $changed);
            }
            if ($prod->summary) {
                $prod->summary = $replaceFunc($prod->summary, $webpByBasename, $changed);
            }
            if ($prod->extra_descriptions) {
                if (is_array($prod->extra_descriptions) || is_object($prod->extra_descriptions)) {
                    $jsonStr = json_encode($prod->extra_descriptions);
                    $newJsonStr = $replaceFunc($jsonStr, $webpByBasename, $changed);
                    if ($changed) {
                        $prod->extra_descriptions = json_decode($newJsonStr, true);
                    }
                } else {
                    $prod->extra_descriptions = $replaceFunc($prod->extra_descriptions, $webpByBasename, $changed);
                }
            }
            if ($changed) {
                $prod->save();
            }
        }

        // 4. Repair Frontends (data_values)
        $frontends = \Illuminate\Support\Facades\DB::table('frontends')
            ->where(function($q) {
                $q->where('data_values', 'LIKE', '%.png%')
                  ->orWhere('data_values', 'LIKE', '%.jpg%')
                  ->orWhere('data_values', 'LIKE', '%.jpeg%');
            })->get();

        foreach ($frontends as $front) {
            $changed = false;
            $newDataValues = $replaceFunc($front->data_values, $webpByBasename, $changed);
            if ($changed) {
                \Illuminate\Support\Facades\DB::table('frontends')
                    ->where('id', $front->id)
                    ->update(['data_values' => $newDataValues]);
            }
        }

        // 5. Auto-Optimize Logo Images
        $logoPath = base_path('../assets/images/logo_icon');
        if (file_exists($logoPath) && is_dir($logoPath)) {
            try {
                $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                $logos = ['logo.png' => 'logo.webp', 'logo_dark.png' => 'logo_dark.webp'];
                foreach ($logos as $png => $webp) {
                    $pngFilePath = $logoPath . '/' . $png;
                    $webpFilePath = $logoPath . '/' . $webp;
                    if (file_exists($pngFilePath) && !file_exists($webpFilePath)) {
                        $img = $manager->read($pngFilePath);
                        // Scale down if width is greater than 600px
                        if ($img->width() > 600) {
                            $img->scale(width: 600);
                            // Also save back scaled version to PNG to reduce size of original
                            $img->save($pngFilePath);
                        }
                        // Save as WebP
                        $img->toWebp(80)->save($webpFilePath);
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Failed to auto-optimize logo images: " . $e->getMessage());
            }
        }

        // 6. Auto-Optimize Frontend section images (banners, sliders, etc.)
        $frontendPath = base_path('../assets/images/frontend');
        if (file_exists($frontendPath) && is_dir($frontendPath)) {
            try {
                $directory = new \RecursiveDirectoryIterator($frontendPath);
                $iterator = new \RecursiveIteratorIterator($directory);
                $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                
                foreach ($iterator as $info) {
                    if ($info->isFile()) {
                        $ext = strtolower($info->getExtension());
                        if (in_array($ext, ['png', 'jpg', 'jpeg'])) {
                            $filePath = $info->getRealPath();
                            $dirPath = dirname($filePath);
                            $filename = $info->getFilename();
                            $basename = pathinfo($filename, PATHINFO_FILENAME);
                            $newFilename = $basename . '.webp';
                            $newPath = $dirPath . '/' . $newFilename;
                            
                            if (!file_exists($newPath)) {
                                $image = $manager->read($filePath);
                                // Scale down if too wide for banners
                                if ($image->width() > 1200) {
                                    $image->scale(width: 1200);
                                }
                                $image->toWebp(80)->save($newPath);
                                
                                // Update frontends database table references
                                \Illuminate\Support\Facades\DB::table('frontends')
                                    ->where('data_values', 'LIKE', '%' . $filename . '%')
                                    ->get()
                                    ->each(function($front) use ($filename, $newFilename) {
                                        $dataValues = str_replace($filename, $newFilename, $front->data_values);
                                        \Illuminate\Support\Facades\DB::table('frontends')
                                            ->where('id', $front->id)
                                            ->update(['data_values' => $dataValues]);
                                    });
                                
                                // Delete old file
                                @unlink($filePath);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Failed to auto-optimize frontend images: " . $e->getMessage());
            }
        }
    }
}
