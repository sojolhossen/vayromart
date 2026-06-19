@php
    $requiredConfig = new \App\Lib\RequiredConfig();
    $configs = $requiredConfig->getConfig();
    $progressPercentage = $requiredConfig->completedConfigPercent();
    $completedConfig = $requiredConfig->completedConfig();

    uksort($configs, function ($a, $b) use ($completedConfig) {
        $aIndex = array_search($a, $completedConfig);
        $bIndex = array_search($b, $completedConfig);
        $aIndex = $aIndex === false ? -1 : $aIndex + 100;
        $bIndex = $bIndex === false ? -1 : $bIndex + 100;

        return $aIndex <=> $bIndex;
    });
@endphp

@if ($requiredConfig->completedConfigCount() < $requiredConfig->totalConfigs())
    <div class="configure-card">
        <div class="configure-card-header">
            <div class="configure-card-top flex-align gap-4">
                <div class="configure-card-left flex-1">
                    <div class="configure-card-slide">
                        @foreach ($configs as $key => $config)
                            <h6 class="configure-card-title flex-align gap-2 mb-2 flex-nowrap" data-config_url="{{ $config['route'] }}">
                                <span class="configure-status @if (in_array($key, $completedConfig)) complete @endif"><i class="fas fa-check"></i></span>
                                {{ __($config['title']) }}
                            </h6>
                        @endforeach
                    </div>
                    <div class="progress" role="progressbar" aria-label="Basic example" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: {{ $progressPercentage }}%"></div>
                    </div>
                </div>
                <a href="" class="configure-card-link flex-shrink-0">
                    <i class="fas fa-exclamation-triangle"></i>
                    @lang('Configure')
                </a>
            </div>
            <div class="configure-card-bottom flex-align mt-2">
                <a href="javascript:void(0)" class="next-btn">
                    <i class="fa-solid fa-arrow-left"></i>
                    @lang('Previous')
                </a>
                <div class="flex-1 flex-center gap-2">
                    <span class="count">
                        <span class="configure-count">{{ $requiredConfig->completedConfigCount() }}</span>
                        @lang('of') <span class="configure-total">{{ $requiredConfig->totalConfigs() }}</span>
                    </span>
                    <div class="show-btn">
                        @lang('Show')
                        <span class="icon text-white">
                            <i class="las la-angle-down"></i>
                        </span>
                    </div>
                </div>
                <a href="javascript:void(0)" class="prev-btn">
                    @lang('Next')
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>
        <div class="configure-card-body">
            <ul class="configure-list">
                @foreach ($configs as $key => $config)
                    <li class="configure-item flex-between">
                        <div class="configure-item-name flex-align gap-2">
                            <span class="configure-status @if (in_array($key, $completedConfig)) complete @endif"><i class="fas fa-check text-white"></i></span>
                            {{ __($config['title']) }}
                        </div>

                        <a href="{{ $config['route'] }}" class="configure-item-btn @if (in_array($key, $completedConfig)) disabled @endif">
                            <i class="fas fa-angle-right"></i>
                            @lang('Configure Now')
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

@push('script-lib')
    <script src="{{ asset('assets/global/js/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('assets/global/js/jquery.ui.touch-punch.min.js') }}"></script>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";

            $('.configure-card').draggable()
        })(jQuery);
    </script>
@endpush

@push('style')
    <style>
        .configure-card.ui-draggable {
            cursor: move;
            height: max-content;
        }
    </style>
@endpush
