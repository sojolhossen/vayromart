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
            'photos.*'         => ['required', 'image', new FileTypeValidate(['jpeg', 'jpg', 'png'])],
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
            $newFilename = $originalName;

            while (file_exists($path . '/' . $newFilename)) {
                $counter++;
                $newFilename = $filename . '(' . $counter . ').' . $extension;
            }
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
}
