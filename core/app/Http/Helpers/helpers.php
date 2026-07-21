<?php

use App\Constants\Status;
use App\Models\Extension;
use App\Models\Frontend;
use App\Models\GeneralSetting;
use Carbon\Carbon;
use App\Lib\Captcha;
use App\Lib\CartManager;
use App\Lib\ClientInfo;
use App\Lib\CurlRequest;
use App\Lib\FileManager;
use App\Lib\ImportFileReader;
use App\Lib\ProductManager;
use App\Lib\ProductPriceManager;
use App\Lib\WishlistManager;
use App\Models\ProductCollection;
use App\Models\Language;
use App\Models\Offer;
use App\Models\Product;
use App\Models\PromotionalBanner;
use App\Notify\Notify;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Laramin\Utility\VugiChugi;

function systemDetails() {
    $system['name'] = 'visermart';
    $system['version'] = '2.2';
    $system['build_version'] = '5.1.9';
    return $system;
}

function slug($string) {
    return Str::slug($string);
}

function verificationCode($length) {
    if ($length == 0) return 0;
    $min = pow(10, $length - 1);
    $max = (int) ($min - 1) . '9';
    return random_int($min, $max);
}

function getNumber($length = 8) {
    $characters = '1234567890';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}


function activeTemplate($asset = false) {
    $template = session('template') ?? gs('active_template');
    if ($asset) return 'assets/templates/' . $template . '/';
    return 'templates.' . $template . '.';
}

function activeTemplateName() {
    $template = session('template') ?? gs('active_template');
    return $template;
}

function frontendComponent($componentName) {
    return 'frontend.' . activeTemplateName() . '.' . $componentName;
}

function siteLogo($type = null) {
    $name = $type ? "logo_$type" : 'logo';
    $path = getFilePath('logoIcon');
    if (file_exists($path . '/' . $name . '.webp')) {
        return asset($path . '/' . $name . '.webp');
    }
    return asset($path . '/' . $name . '.png');
}
function siteFavicon() {
    $path = getFilePath('logoIcon');
    if (file_exists($path . '/favicon.webp')) {
        return asset($path . '/favicon.webp');
    }
    return asset($path . '/favicon.png');
}

function loadReCaptcha() {
    return Captcha::reCaptcha();
}

function loadCustomCaptcha($width = '100%', $height = 46, $bgColor = '#003') {
    return Captcha::customCaptcha($width, $height, $bgColor);
}

function verifyCaptcha() {
    return Captcha::verify();
}

function loadExtension($key) {
    if ($key === 'facebook-pixel') {
        $extensionRecord = \App\Models\Extension::where('act', 'facebook-pixel')->first();
        if (!$extensionRecord) {
            \App\Models\Extension::insert([
                'act' => 'facebook-pixel',
                'name' => 'Facebook Pixel & Conversions API',
                'description' => 'Enter your Facebook Pixel ID and Conversions API (CAPI) Access Token below for server-side tracking.',
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
                    ],
                    'access_token' => [
                        'title' => 'Conversions API (CAPI) Access Token (Optional)',
                        'value' => ''
                    ],
                    'test_event_code' => [
                        'title' => 'Test Event Code (Optional)',
                        'value' => ''
                    ]
                ]),
                'support' => 'fb_pixel_support.png',
                'status' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            // Ensure facebook-pixel is active and shortcode + script are updated
            $sc = json_decode(json_encode($extensionRecord->shortcode), true) ?: [];
            $sc['pixel_id'] = [
                'title' => 'Pixel ID',
                'value' => '1012202121425400'
            ];
            $sc['access_token'] = [
                'title' => 'Conversions API (CAPI) Access Token (Optional)',
                'value' => 'EAAXVtFuQjQ4BSNOSa3J0Lxr3TX6emEzggxcaP1mnHYqZAiZCfeq7Ro9AVm3ZCUYLGI66InUQL6XPDg1cQZAR0NZAjO8frZCztOX83Betz8j1fnETjSx3kBjUPrQgmVRfsN324HUhbNTXETlbqGTW3UWASLAH1ozkwCfFrRZBxxbg1Lm2vGgxreedOzgMa92JbNzAAZDZD'
            ];
            $sc['test_event_code'] = [
                'title' => 'Test Event Code (Optional)',
                'value' => 'TEST89704'
            ];
            $extensionRecord->shortcode = $sc;
            $extensionRecord->status = \App\Constants\Status::ENABLE;
            $extensionRecord->name = 'Facebook Pixel & Conversions API';
            $extensionRecord->description = 'Enter your Facebook Pixel ID and Conversions API (CAPI) Access Token below for server-side tracking.';
            $extensionRecord->script = '<script>
  !function(f,b,e,v,n,t,s)
  {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
  n.callMethod.apply(n,arguments):n.queue.push(arguments)};
  if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version=\'2.0\';
  n.queue=[];t=b.createElement(e);t.async=!0;
  t.src=v;s=b.getElementsByTagName(e)[0];
  s.parentNode.insertBefore(t,s)}(window, document,\'script\',
  \'https://connect.facebook.net/en_US/fbevents.js\');
  fbq(\'init\', \'1012202121425400\');
  fbq(\'track\', \'PageView\');
</script>
<noscript><img height="1" width="1" style="display:none"
  src="https://www.facebook.com/tr?id=1012202121425400&ev=PageView&noscript=1"
/></noscript>';
            $extensionRecord->save();
        }
    }

    $extension = Extension::where('act', $key)->where('status', Status::ENABLE)->first();
    if ($extension && $key === 'facebook-pixel' && !request()->is('admin*')) {
        sendFbCapiEvent('PageView');
    }
    return $extension ? $extension->generateScript() : '';
}

/**
 * Send Facebook Conversions API (CAPI) Server-Side Event
 */
function sendFbCapiEvent($eventName, $customData = [], $userData = []) {
    try {
        $extension = \App\Models\Extension::where('act', 'facebook-pixel')->first();

        $shortcode = json_decode(json_encode($extension->shortcode ?? []), true);
        $pixelId = !empty($shortcode['pixel_id']['value']) ? $shortcode['pixel_id']['value'] : '1012202121425400';
        $accessToken = !empty($shortcode['access_token']['value']) ? $shortcode['access_token']['value'] : 'EAAXVtFuQjQ4BSNOSa3J0Lxr3TX6emEzggxcaP1mnHYqZAiZCfeq7Ro9AVm3ZCUYLGI66InUQL6XPDg1cQZAR0NZAjO8frZCztOX83Betz8j1fnETjSx3kBjUPrQgmVRfsN324HUhbNTXETlbqGTW3UWASLAH1ozkwCfFrRZBxxbg1Lm2vGgxreedOzgMa92JbNzAAZDZD';
        $testEventCode = !empty($shortcode['test_event_code']['value']) ? $shortcode['test_event_code']['value'] : 'TEST89704';

        // Prepare User Data with SHA256 hashes according to Facebook CAPI spec
        $userPayload = [
            'client_ip_address' => request()->ip(),
            'client_user_agent' => request()->userAgent(),
        ];

        if (!empty($userData['phone'])) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $userData['phone']);
            if (strlen($cleanPhone) === 11 && strpos($cleanPhone, '01') === 0) {
                $cleanPhone = '88' . $cleanPhone;
            }
            $userPayload['ph'] = [hash('sha256', strtolower(trim($cleanPhone)))];
        }

        if (!empty($userData['email'])) {
            $userPayload['em'] = [hash('sha256', strtolower(trim($userData['email'])))];
        }

        if (!empty($userData['name'])) {
            $names = explode(' ', trim($userData['name']), 2);
            if (!empty($names[0])) {
                $userPayload['fn'] = [hash('sha256', strtolower(trim($names[0])))];
            }
            if (!empty($names[1])) {
                $userPayload['ln'] = [hash('sha256', strtolower(trim($names[1])))];
            }
        }

        $eventPayload = [
            'event_name' => $eventName,
            'event_time' => time(),
            'action_source' => 'website',
            'event_source_url' => url()->current(),
            'user_data' => $userPayload,
            'custom_data' => array_merge(['currency' => 'BDT'], $customData)
        ];

        $body = [
            'data' => [$eventPayload]
        ];

        if (!empty($testEventCode)) {
            $body['test_event_code'] = $testEventCode;
        }

        \Illuminate\Support\Facades\Http::timeout(5)
            ->post("https://graph.facebook.com/v18.0/{$pixelId}/events?access_token={$accessToken}", $body);

        return true;
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error("FB CAPI Error: " . $e->getMessage());
        return false;
    }
}

function getTrx($length = 12) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function getAmount($amount, $length = 2) {
    $amount = round($amount ?? 0, $length);
    return $amount + 0;
}

function showAmount($amount, $decimal = 2, $separate = true, $exceptZeros = false, $currencyFormat = true) {
    $separator = '';
    if ($separate) {
        $separator = ',';
    }
    $printAmount = number_format($amount, $decimal, '.', $separator);
    if ($exceptZeros) {
        $exp = explode('.', $printAmount);
        if ($exp[1] * 1 == 0) {
            $printAmount = $exp[0];
        } else {
            $printAmount = rtrim($printAmount, '0');
        }
    }
    if ($currencyFormat) {
        if (gs('currency_format') == Status::CUR_BOTH) {
            return gs('cur_sym') . $printAmount . ' ' . __(gs('cur_text'));
        } elseif (gs('currency_format') == Status::CUR_TEXT) {
            return $printAmount . ' ' . __(gs('cur_text'));
        } else {
            return gs('cur_sym') . $printAmount;
        }
    }
    return $printAmount;
}


function removeElement($array, $value) {
    return array_diff($array, (is_array($value) ? $value : array($value)));
}

function cryptoQR($wallet) {
    return "https://api.qrserver.com/v1/create-qr-code/?data=$wallet&size=300x300&ecc=m";
}

function keyToTitle($text) {
    return ucfirst(preg_replace("/[^A-Za-z0-9 ]/", ' ', $text));
}


function titleToKey($text) {
    return strtolower(str_replace(' ', '_', $text));
}


function strLimit($title = null, $length = 10) {
    return Str::limit($title, $length);
}


function getIpInfo() {
    $ipInfo = ClientInfo::ipInfo();
    return $ipInfo;
}


function osBrowser() {
    $osBrowser = ClientInfo::osBrowser();
    return $osBrowser;
}


function getTemplates() {
    $param['purchasecode'] = env("PURCHASECODE");
    $param['website'] = @$_SERVER['HTTP_HOST'] . @$_SERVER['REQUEST_URI'] . ' - ' . env("APP_URL");
    $url = VugiChugi::gttmp() . systemDetails()['name'];
    $response = CurlRequest::curlPostContent($url, $param);
    if ($response) {
        return $response;
    } else {
        return null;
    }
}


function getPageSections($arr = false) {
    $jsonUrl = resource_path('views/') . str_replace('.', '/', activeTemplate()) . 'sections.json';
    $sections = json_decode(file_get_contents($jsonUrl));
    if ($arr) {
        $sections = json_decode(file_get_contents($jsonUrl), true);
        ksort($sections);
    }
    return $sections;
}


function getImage($image, $size = null) {
    $clean = '';
    if (file_exists($image) && is_file($image)) {
        return asset($image) . $clean;
    }
    if ($size) {
        return route('placeholder.image', $size);
    }
    return asset('assets/images/default.png');
}


function notify($user, $templateName, $shortCodes = null, $sendVia = null, $createLog = true, $pushImage = null) {
    $globalShortCodes = [
        'site_name' => gs('site_name'),
        'site_currency' => gs('cur_text'),
        'currency_symbol' => gs('cur_sym'),
    ];

    if (gettype($user) == 'array') {
        $user = (object) $user;
    }

    $shortCodes = array_merge($shortCodes ?? [], $globalShortCodes);

    $notify = new Notify($sendVia);
    $notify->templateName = $templateName;
    $notify->shortCodes = $shortCodes;
    $notify->user = $user;
    $notify->createLog = $createLog;
    $notify->pushImage = $pushImage;
    $notify->userColumn = isset($user->id) ? $user->getForeignKey() : 'user_id';
    $notify->send();
}

function getPaginate($paginate = null) {
    if (!$paginate) {
        $paginate = gs('paginate_number');
    }
    return $paginate;
}

function paginateLinks($data, $view = null) {
    return $data->appends(request()->all())->links($view);
}

function menuActive($routeName, $type = null, $param = null) {
    if ($type == 3) $class = 'side-menu--open';
    elseif ($type == 2) $class = 'sidebar-submenu__open';
    else $class = 'active';

    if (is_array($routeName)) {
        foreach ($routeName as $key => $value) {
            if (request()->routeIs($value)) return $class;
        }
    } elseif (request()->routeIs($routeName)) {
        if ($param) {
            $routeParam = array_values(@request()->route()->parameters ?? []);
            if (strtolower(@$routeParam[0]) == strtolower($param)) return $class;
            else return;
        }
        return $class;
    }
}

function fileUploader($file, $location, $size = null, $old = null, $thumb = null, $filename = null) {
    $fileManager = new FileManager($file);
    $fileManager->path = $location;
    $fileManager->size = $size;
    $fileManager->old = $old;
    $fileManager->thumb = $thumb;
    $fileManager->filename = $filename;
    $fileManager->upload();
    return $fileManager->filename;
}

function fileManager() {
    return new FileManager();
}

function getFilePath($key) {
    return fileManager()->$key()->path;
}

function getFileSize($key) {
    return fileManager()->$key()->size;
}

function getFileExt($key) {
    return fileManager()->$key()->extensions;
}

function diffForHumans($date) {
    $lang = session()->get('lang');
    if (!$lang) {
        $lang = getDefaultLang();
    }

    Carbon::setlocale($lang);
    return Carbon::parse($date)->diffForHumans();
}


function showDateTime($date, $format = 'Y-m-d h:i A') {
    if (!$date) {
        return '-';
    }
    $lang = session()->get('lang');
    if (!$lang) {
        $lang = getDefaultLang();
    }

    Carbon::setlocale($lang);
    return Carbon::parse($date)->translatedFormat($format);
}

function getDefaultLang() {
    return Language::where('is_default', Status::YES)->first()->code ?? 'en';
}

function getContent($dataKeys, $singleQuery = false, $limit = null, $orderById = false) {

    $templateName = activeTemplateName();
    if ($singleQuery) {
        $content = Frontend::where('tempname', $templateName)->where('data_keys', $dataKeys)->orderBy('id', 'desc')->first();
    } else {
        $article = Frontend::where('tempname', $templateName);
        $article->when($limit != null, function ($q) use ($limit) {
            return $q->limit($limit);
        });
        if ($orderById) {
            $content = $article->where('data_keys', $dataKeys)->orderBy('id')->get();
        } else {
            $content = $article->where('data_keys', $dataKeys)->orderBy('id', 'desc')->get();
        }
    }
    return $content;
}

function urlPath($routeName, $routeParam = null) {
    if ($routeParam == null) {
        $url = route($routeName);
    } else {
        $url = route($routeName, $routeParam);
    }
    $basePath = route('home');
    $path = str_replace($basePath, '', $url);
    return $path;
}


function showMobileNumber($number) {
    $length = strlen($number);
    return substr_replace($number, '***', 2, $length - 4);
}

function showEmailAddress($email) {
    $endPosition = strpos($email, '@') - 1;
    return substr_replace($email, '***', 1, $endPosition);
}


function getRealIP() {
    $ip = $_SERVER["REMOTE_ADDR"];
    //Deep detect ip
    if (filter_var(@$_SERVER['HTTP_FORWARDED'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_FORWARDED'];
    }
    if (filter_var(@$_SERVER['HTTP_FORWARDED_FOR'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_FORWARDED_FOR'];
    }
    if (filter_var(@$_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    if (filter_var(@$_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    if (filter_var(@$_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    }
    if (filter_var(@$_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if ($ip == '::1') {
        $ip = '127.0.0.1';
    }

    return $ip;
}


function appendQuery($key, $value) {
    return request()->fullUrlWithQuery([$key => $value]);
}

function dateSort($a, $b) {
    return strtotime($a) - strtotime($b);
}

function dateSorting($arr) {
    usort($arr, "dateSort");
    return $arr;
}

function gs($key = null) {
    $general = Cache::get('GeneralSetting');
    if (!$general) {
        $general = GeneralSetting::first();
        Cache::put('GeneralSetting', $general);
    }
    if ($key) return @$general->$key;
    return $general;
}
function isImage($string) {
    $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');
    $fileExtension = pathinfo($string, PATHINFO_EXTENSION);
    if (in_array($fileExtension, $allowedExtensions)) {
        return true;
    } else {
        return false;
    }
}

function isHtml($string) {
    if (preg_match('/<.*?>/', $string)) {
        return true;
    } else {
        return false;
    }
}

function convertToReadableSize($size) {
    preg_match('/^(\d+)([KMG])$/', $size, $matches);
    $size = (int)$matches[1];
    $unit = $matches[2];

    if ($unit == 'G') {
        return $size . 'GB';
    }

    if ($unit == 'M') {
        return $size . 'MB';
    }

    if ($unit == 'K') {
        return $size . 'KB';
    }

    return $size . $unit;
}

function frontendImage($sectionName, $image, $size = null, $seo = false) {
    if ($seo) {
        return getImage('assets/images/frontend/' . $sectionName . '/seo/' . $image, $size);
    }
    return getImage('assets/images/frontend/' . $sectionName . '/' . $image, $size);
}

function buildResponse($remark, $status, $notify, $data = null) {
    $response = [
        'remark' => $remark,
        'status' => $status,
    ];
    $message = [];
    if ($notify instanceof \Illuminate\Support\MessageBag) {
        $message['error']  = collect($notify)->map(function ($item) {
            return $item[0];
        })->values()->toArray();
    } else {
        $message = [$status => collect($notify)->map(function ($item) {
            if (is_string($item)) {
                return $item;
            }
            if (count($item) > 1) {
                return $item[1];
            }
            return $item[0];
        })->toArray()];
    }
    $response['message'] = $message;
    if ($data) {
        $response['data'] = $data;
    }
    return response()->json($response);
}
function responseSuccess($remark, $notify, $data = null) {
    return buildResponse($remark, 'success', $notify, $data);
}
function responseError($remark, $notify, $data = null) {
    return buildResponse($remark, 'error', $notify, $data);
}


function getThumbSize($key) {
    return fileManager()->$key()->thumb;
}

function displayRating($averageRating) {
    $averageRating = $averageRating > 5 ? 5 : $averageRating;
    $precisionThreshold1 = 0.25;
    $precisionThreshold2 = 0.75;
    $starCount = 5;

    $precision = round($averageRating, 2) - intval($averageRating);
    $output = '';

    if ($precision > $precisionThreshold1) {
        $averageRating = intval($averageRating) + 0.5;
    }

    if ($precision > $precisionThreshold2) {
        $averageRating = intval($averageRating) + 1;
    }

    for ($i = 0; $i < intval($averageRating); $i++) {
        $output .= '<i class="la la-star"></i>';
    }

    if ($averageRating - intval($averageRating) == 0.5) {
        $i++;
        $output .= '<i class="las la-star-half-alt"></i>';
    }

    for ($k = 0; $k < $starCount - $i; $k++) {
        $output .= '<i class="lar la-star"></i>';
    }

    return $output;
}

function checkWishList($productId) {
    if (wishlistManager()->isProductExistInWishlist($productId)) {
        return true;
    } else {
        return false;
    }
}

function checkCompareList($productId) {
    $compare = session()->get('compare') ?? [];
    $compare = array_keys($compare);

    if (in_array($productId, $compare)) {
        return true;
    } else {
        return false;
    }
}

function numberFormatShort($n) {
    $nFormat = 0;
    $suffix  = '';
    if ($n > 0 && $n < 1000) {
        // 1 - 999
        $nFormat = floor($n);
    } else if ($n >= 1000 && $n < 1000000) {
        // 1k-999k
        $nFormat = floor($n / 1000);
        $suffix  = 'K+';
    } else if ($n >= 1000000 && $n < 1000000000) {
        // 1m-999m
        $nFormat = floor($n / 1000000);
        $suffix  = 'M+';
    } else if ($n >= 1000000000 && $n < 1000000000000) {
        // 1b-999b
        $nFormat = floor($n / 1000000000);
        $suffix  = 'B+';
    } else if ($n >= 1000000000000) {
        // 1t+
        $nFormat = floor($n / 1000000000000);
        $suffix  = 'T+';
    }
    return $nFormat . $suffix;
}

function productAttributesDetails($attributes, $attrData) {
    $variants   = '';
    $variants   = [];
    $extraPrice = 0;
    foreach ($attributes ?? [] as $key => $aid) {
        $price                   = $attrData->where('id', $aid)->first()->extra_price;
        $variants[$key]['name']  = $attrData->where('id', $aid)->first()->productAttribute->name;
        $variants[$key]['value'] = $attrData->where('id', $aid)->first()->name;
        $variants[$key]['price'] = $price;

        $extraPrice += $price;
    }
    $details['variants']    = $variants;
    $details['extra_price'] = $extraPrice;
    return $details;
}

function array_flatten($array) {
    if (!is_array($array)) {
        return false;
    }
    $result = array();
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $result = array_merge($result, array_flatten($value));
        } else {
            $result[$key] = $value;
        }
    }
    return $result;
}

function getAvatar($image, $clean = '') {
    return file_exists($image) && is_file($image) ? asset($image) . $clean : asset(getFilePath('avatar'));
}

function slugToId($slug) {
    $slug = (explode('-', $slug));
    return end($slug);
}

function productAttributes($attributes, $attributesData) {
    $variants = '';
    if ($attributes) {
        foreach ($attributes as $aid) {
            $variants .= @$attributesData->where('id', $aid)->first()->productAttribute->name . ' : ';
            $variants .= @$attributesData->where('id', $aid)->first()->name . '<br>';
        }
        return $variants;
    } else {
        return null;
    }
}

function multiLayerCategory($category) {
    $multiLayerCategory = false;

    if (isset($category['allSubcategories'])) {
        $subcategories = $category['allSubcategories'];
        foreach ($subcategories as $subCat) {
            if (count($subCat['allSubcategories'])) {
                $multiLayerCategory = true;
            }
        }
    }
    return $multiLayerCategory;
}

function errorResponse($message, $data = []) {
    return response()->json(['status' => 'error', 'message' => $message, ...$data]);
}

function successResponse($message, $data = []) {
    return response()->json(['status' => 'success', 'message' => $message, ...$data]);
}

function keepFirstLetterOfWords($input) {
    $words = explode('-', $input);
    $firstLetters = [];
    foreach ($words as $word) {
        $firstLetters[] = substr(trim($word), 0, 1);
    }
    $result = implode('-', $firstLetters);

    return $result;
}

function generateSKU($product, $variant = null) {
    $sku = $product->sku;

    if (!$product->sku) {
        if ($product->brand) {
            $sku .= '-' . strtoupper(substr($product->brand->name, 0, 3));
        }

        if ($product->categories->count()) {
            $sku .= '-' . strtoupper(substr($product->categories->first()->name, 0, 3));
        }
        if ($product->id) {
            $sku .= '-' . sprintf("%02d", $product->id);
        }
    }

    if ($variant) {
        $sku .= '-' . keepFirstLetterOfWords($variant);
    }

    $sku = preg_replace('/^-/', '', $sku, 1);
    return $sku;
}

function productManager() {
    return new ProductManager();
}

function productPriceManager() {
    return new ProductPriceManager();
}

function cartManager() {
    return new CartManager();
}

function wishlistManager() {
    return new WishlistManager();
}

function prepareAttributeValues($attributeValues) {
    $attributeValues = is_array($attributeValues) ? $attributeValues : [];
    sort($attributeValues);
    $attributes    = array_map('intval', $attributeValues);
    return json_encode($attributes);
}

function getSessionId() {
    $sessionId = session('session_id');
    if (!$sessionId) {
        $sessionId = uniqid();
        session()->put('session_id', $sessionId);
    }
    return $sessionId;
}

function getCountries() {
    $countries = json_decode(file_get_contents(resource_path('views/partials/country.json')));
    if (isset($countries->BD)) {
        return (object) ['BD' => $countries->BD];
    }
    return $countries;
}


function yn($condition) {
    return $condition ? trans('Yes') : trans('No');
}

function shopLink($params) {
    return route('product.all', $params);
}

function moveElement(&$array, $oldIndex, $newIndex) {
    if ($oldIndex == $newIndex) {
        // No need to move if the old and new indices are the same
        return;
    }
    // Remove the element from the old index
    $element = array_splice($array, $oldIndex, 1)[0];

    // Insert the element at the new index
    array_splice($array, $newIndex, 0, [$element]);
}

function formattedFilterParameterRatings($active = 0) {
    $i = 0;
    $html = '';
    for (; $i < $active; $i++) {
        $html .= '<span class="rating-list__icon rating-list__icon-active">
                    <i class="fas fa-star"></i>
                </span>';
    }

    $after = 5 - $i;

    if ($after > 0) {
        for ($k = 0; $k < $after; $k++) {
            $html .= '  <span class="rating-list__icon rating-list__icon-disable">
                            <i class="fas fa-star"></i>
                        </span>';
        }
    }

    return $html;
}

function mediaType($extension) {
    $type = null;
    $extArray = [
        'image'    => ['jpg', 'jpeg', 'png', 'gif', 'bmp'],
        'audio'    => ['mp3', 'wav', 'ogg'],
        'video'    => ['mp4', 'mkv', 'flv'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
        'archive'  => ['zip', 'rar', 'tar', 'gz']
    ];

    array_walk($extArray, function ($values, $key) use ($extension, &$type) {
        if (in_array($extension, $values)) {
            $type = $key;
        }
    });

    return $type;
}

function getMediaIcon($type) {
    return getImage('assets/images/default_media/' . $type . '.png');
}

/**
 * If you change key value of these array, you have to change home page conditional rendering code,
 * search homepage_layout all over the file and
 * @param $isKeys expect boolean value, if true then return only keys or full array
 * @return array of keys or full array by keys
 */
function homepageLayouts($isKeys = false) {
    $layouts =  [
        "sidebar_menu" => [
            "key" => "sidebar_menu",
            "name" => "Sidebar Menu Layout",
            "banner" => asset('assets/images/home_page_layout/sidebar_menu.png')
        ],
        "full_width_banner" => [
            "key" => "full_width_banner",
            "name" => "Full Width Banner Layout",
            "banner" => asset('assets/images/home_page_layout/full_width_banner.png')
        ]
    ];

    if ($isKeys) {
        return array_column($layouts, "key");
    }

    return $layouts;
}

function createUniqueSlug($name, $model, $id = 0) {
    $slug = slug($name ?? 'No title');

    $originalSlug = $slug;
    $hasSoftDelete = in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive($model));

    $query = $hasSoftDelete ? $model::withTrashed()->where('slug', $slug) : $model::where('slug', $slug);

    if ($id) {
        $query->where('id', '!=', $id);
    }

    $i = 1;

    while ($query->exists()) {
        $slug = $originalSlug . '-' . $i++;
        $query = $hasSoftDelete ? $model::withTrashed()->where('slug', $slug) : $model::where('slug', $slug);
        if ($id) {
            $query->where('id', '!=', $id);
        }
    }

    return $slug;
}



function svg($name) {
    return getImage(getFilePath('svg') . '/' . $name . '.svg');
}

function getSectionData($key, $isOnlyData = false, $isFrontend = false) {
    if (!$key) return null;

    $model = null;
    $models = [
        'collection_' => [
            'modelQuery' => ProductCollection::query(),
            'section' => 'Template::sections.collection'
        ],
        'banner_'    => [
            'modelQuery' => PromotionalBanner::query(),
            'section' => 'Template::sections.promo_banner'
        ],
        'offer_'    => [
            'modelQuery' => ($isFrontend ? Offer::running() : Offer::query())->with(['products' => function ($q) {
                $q->published()->ratingReviewCount()->with(['brand:id,name', 'productVariants', 'displayImage', 'activeOffer']);
            }]),
            'section' => 'Template::sections.offer'
        ]
    ];

    try {
        $modelIndex = $key;
        $parts = explode('_', $modelIndex);
        $modelIndex = $parts[0] . '_';

        if (!array_key_exists($modelIndex, $models)) {
            return null;
        }

        $model = $models[$modelIndex]['modelQuery'];
        $modelData = $model->where('unique_key', $key)->first();
        if (!$modelData) return null;

        if ($isOnlyData) {
            return $modelData;
        }

        return [
            'section' => $models[$modelIndex]['section'],
            'data' => $modelData
        ];
    } catch (\Exception $e) {
        return null;
    }
}

function getSeoContents($model) {
    return (object) [
        'social_title'       => $model->meta_title,
        'social_description' => $model->meta_description,
        'description'        => $model->meta_description,
        'keywords'           => $model->meta_keywords,
        'image'              => $model->seo_image,
        'image_size'         => $model->seo_image_size,
    ];
}

function sendOrderPlacedNotification($user, $order) {
    notify($user, 'ORDER_PLACED', [
        'order_number'      => $order->order_number,
        'order_details_url' => route($order->user_id ? 'user.orders.details' : 'orders.details', $order->order_number),
        'order_track_url'   => route('order.track'),
    ]);
}

function importFileReader($file, $columns, $modelClass = Product::class) {
    $fileRead = new ImportFileReader($file, $modelClass);

    $fileRead->columns = $columns;
    $fileRead->readFile();

    return $fileRead;
}


function getFileInfoViaFullPath($fullPath) {
    $pathInfo = pathinfo(parse_url($fullPath, PHP_URL_PATH));
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    $headers = @get_headers($fullPath, 1, $context);
    if (!is_array($headers)) {
        $headers = [];
    }

    return (object) [
        'basename' => $pathInfo['basename'] ?? null,
        'filename' => $pathInfo['filename'] ?? null,
        'extension' => $pathInfo['extension'] ?? null,
        'mime_type' => $headers['Content-Type'] ?? null,
        'size' => $headers['Content-Length'] ?? null,
        'full_path' => $fullPath,
        'formatted_size' => formatFileSize($headers['Content-Length'] ?? 0),
    ];
}

function formatFileSize($bytes, $precision = 2) {
    if ($bytes < 1024) return $bytes . ' B';

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = floor(log($bytes, 1024));
    $value = $bytes / pow(1024, $power);

    return round($value, $precision) . ' ' . $units[$power];
}

function shortenFileName($filename, $maxLength = 30) {
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $nameOnly = pathinfo($filename, PATHINFO_FILENAME);

    if (strlen($filename) <= $maxLength) {
        return $filename;
    }

    $extWithDot = $ext ? '.' . $ext : '';
    $keep = $maxLength - strlen($extWithDot) - 3;

    $start = substr($nameOnly, 0, ceil($keep / 2));
    $end = substr($nameOnly, -floor($keep / 2));

    return $start . '...' . $end . $extWithDot;
}

function hexToHsl($hex)
{
    $hex   = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $red   = hexdec(substr($hex, 0, 2)) / 255;
    $green = hexdec(substr($hex, 2, 2)) / 255;
    $blue  = hexdec(substr($hex, 4, 2)) / 255;
    $cmin  = min($red, $green, $blue);
    $cmax  = max($red, $green, $blue);
    $delta = $cmax - $cmin;
    if ($delta == 0) {
        $hue = 0;
    } elseif ($cmax === $red) {
        $hue = (($green - $blue) / $delta);
    } elseif ($cmax === $green) {
        $hue = ($blue - $red) / $delta + 2;
    } else {
        $hue = ($red - $green) / $delta + 4;
    }
    $hue = round($hue * 60);
    if ($hue < 0) {
        $hue += 360;
    }
    $lightness  = (($cmax + $cmin) / 2);
    $saturation = $delta === 0 ? 0 : ($delta / (1 - abs(2 * $lightness - 1)));
    if ($saturation < 0) {
        $saturation += 1;
    }
    $lightness  = round($lightness * 100);
    $saturation = round($saturation * 100);
    $hsl['h']   = $hue;
    $hsl['s']   = $saturation;
    $hsl['l']   = $lightness;
    return $hsl;
}
