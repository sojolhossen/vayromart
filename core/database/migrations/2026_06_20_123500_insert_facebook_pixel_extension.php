<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $exists = DB::table('extensions')->where('act', 'facebook-pixel')->exists();
        if (!$exists) {
            DB::table('extensions')->insert([
                'act' => 'facebook-pixel',
                'name' => 'Facebook Pixel',
                'description' => 'Key location is shown below',
                'image' => 'facebook_pixel.png',
                'script' => '<script>
  !function(f,b,e,v,n,t,s)
  {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
  n.callMethod.apply(n,arguments):n.queue.push(arguments)};
  if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version=\'2.0\';
  n.queue=[];t=b.createElement(e);t.async=!0;
  t.src=v;s=b.getElementsByTagName(e)[0];
  s.parentNode.insertBefore(t,s)}(window, document,\'script\',
  \'https://connect.facebook.net/en_US/fbevents.js\');
  fbq(\'init\', \'{{pixel_id}}\');
  fbq(\'track\', \'PageView\');
</script>
<noscript><img height="1" width="1" style="display:none"
  src="https://www.facebook.com/tr?id={{pixel_id}}&ev=PageView&noscript=1"
/></noscript>',
                'shortcode' => json_encode([
                    'pixel_id' => [
                        'title' => 'Pixel ID',
                        'value' => ''
                    ]
                ]),
                'support' => 'fb_pixel_support.png',
                'status' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('extensions')->where('act', 'facebook-pixel')->delete();
    }
};
