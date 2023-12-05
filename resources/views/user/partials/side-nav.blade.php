<div class="sidebar">
    <div class="sidebar-inner">
        <div class="sidebar-area">
            <div class="sidebar-logo">
                <a href="{{ setRoute('index')}}" class="sidebar-main-logo">
                    <img src="{{ get_logo($basic_settings,"dark") }}" data-white_img="{{ get_logo($basic_settings,"dark") }}"
                        data-dark_img="{{ get_logo($basic_settings,"dark") }}" alt="logo">
                </a>
                <button class="sidebar-menu-bar">
                    <i class="fas fa-exchange-alt"></i>
                </button>
            </div>
            <div class="sidebar-menu-wrapper">
                <ul class="sidebar-menu">
                    <li class="sidebar-menu-item">
                        <a href="{{ setRoute('user.dashboard') }}">
                            <i class="menu-icon las la-palette"></i>
                            <span class="menu-title">{{__("Dashboard")}}</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ setRoute('user.send.remittance.index') }}">
                            <i class="menu-icon las la-fax"></i>
                            <span class="menu-title">{{__("Send Remittance")}}</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ setRoute('user.transaction.index') }}">
                            <i class="menu-icon las la-wallet"></i>
                            <span class="menu-title">{{__("Transactions")}}</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ setRoute('user.recipient.show') }}">
                            <i class="menu-icon las la-user-friends"></i>
                            <span class="menu-title">{{__("Recipients")}}</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ setRoute('user.authorize.kyc') }}">
                            <i class="menu-icon las la-user-alt"></i>
                            <span class="menu-title">{{__("KYC")}}</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ setRoute('user.security.google.2fa') }}">
                            <i class="menu-icon las la-qrcode"></i>
                            <span class="menu-title">{{__("2FA Security")}}</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="javascript:void(0)" class="logout-btn">
                            <i class="menu-icon las la-sign-out-alt"></i>
                            <span class="menu-title">{{__("Logout")}}</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="sidebar-doc-box bg_img" data-background="{{ asset('public/frontend//images/element/side-bg.webp') }}">
            <div class="sidebar-doc-icon">
                <i class="las la-headset"></i>
            </div>
            <div class="sidebar-doc-content">
                <h4 class="title">{{ __("Help Center") }}</h4>
                <p>{{ __("How can we help you?") }}</p>
                <div class="sidebar-doc-btn">
                    <a href="{{ setRoute('user.support.ticket.index') }}" class="btn--base w-100">{{__("Get Support")}}</a>
                </div>
            </div>
        </div>
    </div>
</div>


