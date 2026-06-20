<?php

namespace App\Lib;

use App\Models\Wishlist;

/**
 * Class WishlistManager
 *
 * This class is responsible for managing wishlist-related operations.
 */
class WishlistManager {

    public function getWishlistItemById($id) {
        if (auth()->check()) {
            return Wishlist::where('user_id', auth()->id())->where('id', $id)->first();
        }
        return Wishlist::where('session_id', getSessionId())->where('id', $id)->first();
    }

    private static $wishlistProductIds = null;

    public static function clearCache() {
        self::$wishlistProductIds = null;
    }

    private function loadWishlistProductIds() {
        if (is_null(self::$wishlistProductIds)) {
            if (auth()->check()) {
                self::$wishlistProductIds = Wishlist::where('user_id', auth()->id())->pluck('product_id')->toArray();
            } else {
                self::$wishlistProductIds = Wishlist::where('session_id', getSessionId())->pluck('product_id')->toArray();
            }
        }
        return self::$wishlistProductIds;
    }

    public function isProductExistInWishlist($productId) {
        $ids = $this->loadWishlistProductIds();
        return in_array($productId, $ids);
    }

    public function userWishlistQuery($checkProduct = true) {
        $wishlistData = Wishlist::query();

        if ($checkProduct) {
            $wishlistData->hasProduct();
        }

        if (auth()->check()) {
            return $wishlistData->where('user_id', auth()->id());
        }

        return $wishlistData->where('session_id', getSessionId());
    }

    public function getWishlistCount() {
        return $this->userWishlistQuery()->count();
    }

    public function getWishlist($limit = null, $pagination = false) {
        $eagerLoadableRelations = [
            'product',
            'product.productVariants',
            'product.brand',
            'product.categories'
        ];

        $wishlist = $this->userWishlistQuery()->with($eagerLoadableRelations)->orderBy('id', 'desc');

        if ($limit) {
            $wishlist->limit($limit);
        }

        if ($pagination) {
            return $wishlist->paginate(getPaginate());
        }

        return $wishlist->get();
    }

    public function insertUserToWishlist() {
        $wishlistItems = Wishlist::where('session_id', getSessionId())->where('user_id', 0)->get();

        foreach ($wishlistItems as $wishlistItem) {
            Wishlist::where('user_id', auth()->id())->where('product_id', $wishlistItem->product_id)->delete();
        }

        Wishlist::where('session_id', getSessionId())->update(['user_id' => auth()->id()]);
    }
}
