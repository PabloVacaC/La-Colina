@inject('preloaderHelper', 'JeroenNoten\LaravelAdminLte\Helpers\PreloaderHelper')

<div class="{{ $preloaderHelper->makePreloaderClasses() }}" style="{{ $preloaderHelper->makePreloaderStyle() }}">

    @hasSection('preloader')

        {{-- Use a custom preloader content --}}
        @yield('preloader')

    @else
        @php
            $configuracion = \App\Models\Configuracion::first();
        @endphp

        @if($configuracion)
            {{-- Use the default preloader content --}}
            <img src="{{ url($configuracion->logo)  }}"
                 class="img-circle {{ config('adminlte.preloader.img.effect', 'animation__shake') }}"
                 alt="{{ config('adminlte.preloader.img.alt', 'AdminLTE Preloader Image') }}"
                 width="{{ config('adminlte.preloader.img.width', 60) }}"
                 height="{{ config('adminlte.preloader.img.height', 60) }}"
                 style="animation-iteration-count:infinite;">
        @else
            {{-- Use the default preloader content --}}
            <img src="{{ asset(config('adminlte.preloader.img.path', 'vendor/adminlte/dist/img/AdminLTELogo.png')) }}"
                 class="img-circle {{ config('adminlte.preloader.img.effect', 'animation__shake') }}"
                 alt="{{ config('adminlte.preloader.img.alt', 'AdminLTE Preloader Image') }}"
                 width="{{ config('adminlte.preloader.img.width', 60) }}"
                 height="{{ config('adminlte.preloader.img.height', 60) }}"
                 style="animation-iteration-count:infinite;">
        @endif





    @endif

</div>


