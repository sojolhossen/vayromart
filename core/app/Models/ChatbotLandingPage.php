<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotLandingPage extends Model
{
    protected $table = 'chatbot_landing_pages';

    protected $fillable = [
        'product_id',
        'slug',
        'title',
        'content',
        'design_settings'
    ];

    protected $casts = [
        'design_settings' => 'array'
    ];

    /**
     * Get the associated product
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
