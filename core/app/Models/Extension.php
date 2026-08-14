<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class Extension extends Model
{
    use GlobalStatus;

    protected $casts = [
        'shortcode' => 'object',
    ];

    protected $hidden = ['script','shortcode'];

    public function scopeGenerateScript()
    {
        $script = $this->script;
        $shortcode = json_decode(json_encode($this->shortcode), true) ?: [];
        foreach ($shortcode as $key => $item) {
            $val = is_array($item) ? ($item['value'] ?? '') : ($item->value ?? '');
            $script = str_replace('{{' . $key . '}}', $val, $script);
        }
        return $script;
    }
}
