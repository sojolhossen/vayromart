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

    protected static function boot()
    {
        parent::boot();

        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('chatbot_landing_pages')) {
                \Illuminate\Support\Facades\Schema::create('chatbot_landing_pages', function ($table) {
                    $table->id();
                    $table->unsignedBigInteger('product_id')->index();
                    $table->string('slug')->unique();
                    $table->string('title');
                    $table->mediumText('content');
                    $table->text('design_settings')->nullable();
                    $table->timestamps();
                });
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to auto-create chatbot_landing_pages: " . $e->getMessage());
        }
    }

    /**
     * Get the associated product
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
