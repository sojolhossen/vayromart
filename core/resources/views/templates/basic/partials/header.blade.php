 @php
     $headers = App\Models\Frontend::where('data_keys', 'headers.order.content')->first()->data_values;
     $headerThree = App\Models\Frontend::where('data_keys', 'header_three.content')->first()?->data_values;
     $siteMenu = $headerThree->group->links;
 @endphp


 <div class="header-area bg-white">
     @foreach ($headers as $header)
         @if ($header != 'header_three')
             @include('Template::partials.headers.'.$header)
         @endif
     @endforeach
 </div>

 @if (in_array('header_three', $headers))
     @include('Template::partials.headers.header_three')
 @endif

 @include('Template::partials.mobile_menu')
