@php
    $footer = getContent('footer.content', true);
    $footer = @$footer->data_values;
    $socials = getContent('social_icon.element', orderById: true);
    $menus = \App\Models\Frontend::where('data_keys', 'footer_menu.content')->first()->data_values;

@endphp

<!-- Footer Section Starts Here -->
<footer class="footer-area footer-bg ">
    <div class="container">
        @if (
            @$footer->logo ||
                @$footer->footer_note ||
                gs('subscriber_module') == Status::YES ||
                @$footer->contact_address ||
                @$footer->cell_number ||
                @$footer->email)
            <div class="footer-top">
                <div class="footer-widget widget-about">
                    @if (@$footer->logo)
                        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
                            data-src="{{ getImage('assets/images/frontend/footer/' . @$footer->logo) }}"
                            class="lazyload footer-logo" alt="footer-logo" width="220" height="51" style="aspect-ratio: 220/51; width: 100%; height: auto;">
                    @endif

                    @if (@$footer->footer_note)
                        <p class="mb-0">{{ __($footer->footer_note) }}</p>
                    @endif
                </div>

                @include('Template::partials.newsletter')

                @if (@$footer->contact_address || @$footer->cell_number || @$footer->email)
                    <div class="widget-contact">
                        @if (@$footer->contact_heading)
                            <h6 class="title">{{ __(@$footer->contact_heading) }}</h6>
                        @endif
                        <ul>
                            @if ($footer->contact_address)
                                <li>
                                    <i class="las la-map-marker"></i> {{ __(@$footer->contact_address) }}
                                </li>
                            @endif

                            @if (@$footer->cell_number)
                                <li>
                                    <a href="tel:{{ @$footer->cell_number }}"><i
                                            class="las la-phone"></i>{{ @$footer->cell_number }}</a>
                                </li>
                            @endif
                            @if (@$footer->email)
                                <li>
                                    <a href="mailto:{{ @$footer->email }}"><i
                                            class="las la-envelope"></i>{{ @$footer->email }}</a>
                                </li>
                            @endif
                        </ul>
                    </div>
                @endif
            </div>
        @endif
        @if ($menus)
            <div class="footer-middle">
                @foreach ($menus as $menu)
                    <div class="footer-widget widget-link">
                        <h6 class="title">{{ __($menu->title) }}</h6>
                        <ul>
                            @foreach ($menu->links as $link)
                                <li><a href="{{ url($link->url) }}">{{ __($link->name) }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
        @if (
            @$footer->copyright_text ||
                @$footer->payment_methods ||
                (!blank($socials) && @$footer->social_status == Status::YES))
            <div class="footer-copyright py-3">
                <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center">
                    @if (@$footer->copyright_text)
                        <div class="left">
                            <p class="mb-0">
                                @php echo @$footer->copyright_text; @endphp
                            </p>
                        </div>
                    @endif
                    @if (!blank($socials) && @$footer->social_status == Status::YES)
                        <ul class="social-icons d-flex gap-2 flex-wrap mt-0">
                            @foreach ($socials as $item)
                                <li>
                                    <a href="{{ $item->data_values->url }}" target="_blank">
                                        @php
                                            echo $item->data_values->social_icon;
                                        @endphp
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if (@$footer->payment_methods)
                        <div class="right">
                            <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"
                                data-src="{{ getImage('assets/images/frontend/footer/' . @$footer->payment_methods, '250x30') }}"
                                class="lazyload" alt="@lang('footer')" width="250" height="30" style="aspect-ratio: 250/30; width: 100%; height: auto;">
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</footer>
<!-- Footer Section Ends Here -->

<div class="modal fade" id="quickView">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <button type="button" class="close modal-close-btn " data-bs-dismiss="modal" aria-label="Close">
                <i class="las la-times"></i>
            </button>
            <div class="modal-body">
                <div class="ajax-loader-wrapper d-flex align-items-center justify-content-center">
                    <div class="spinner-border" role="status">
                        <span class="sr-only">@lang('Loading')...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
