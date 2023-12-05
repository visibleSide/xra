@php
    $menues = DB::table('setup_pages')->where('status', 1)->latest()->take(5)->get();
@endphp
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        Start Header
    ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
    <header class="header-section">
        <div class="header">
            <div class="header-bottom-area">
                <div class="container">
                    <div class="header-menu-content">
                        <nav class="navbar navbar-expand-lg p-0">
                            <a class="site-logo site-title" href="{{ setRoute('index')}}"><img src="{{ get_logo($basic_settings,"dark") }}"
                                alt="site-logo"></a>
                            <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse"
                                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                                aria-expanded="false" aria-label="Toggle navigation">
                                <span class="fas fa-bars"></span>
                            </button>
                            @php
                                $current_url = URL::current();
                            @endphp
                            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                                <ul class="navbar-nav main-menu mx-auto">
                                    @foreach ($menues as $item)
                                        @php
                                            $title = $item->title ?? "";
                                        @endphp
                                        <li><a href="{{ url($item->url) }}" class=" @if($current_url == url($item->url)) active @endif ">{{ __($title) }} <i class="fas fa-caret-right"></i></a></li>
                                    @endforeach
                                </ul>
                                <div class="header-action">
                                    <div class="language-select">
                                        @php
                                            $__current_local = session("local") ?? get_default_language_code();
                                        @endphp
                                        <select class="nice-select" name="lang_switcher" id="">
                                            @foreach ($__languages as $__item)
                                                <option value="{{ $__item->code }}" @if ($__current_local == $__item->code)
                                                    @selected(true)
                                                @endif>{{ $__item->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>   
                                </div>
                                <div class="header-action  ms-lg-3 ms-0">
                                    @auth
                                        <a href="{{ setRoute('user.dashboard')}}" class="btn--base">{{ __("Dashboard") }}</a>
                                    @else
                                        <a href="{{ setRoute('user.login')}}" class="btn--base">{{ __("Login") }}</a>
                                    @endauth
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        End Header
    ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
    @push('script')
    <script>
        $("select[name=lang_switcher]").change(function(){
        var selected_value = $(this).val();
        var submitForm = `<form action="{{ setRoute('frontend.languages.switch') }}" id="local_submit" method="POST"> @csrf <input type="hidden" name="target" value="${$(this).val()}" ></form>`;
        $("body").append(submitForm);
        $("#local_submit").submit();
        });
    </script>
    @endpush