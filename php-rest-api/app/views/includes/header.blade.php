<div class="shell">
    <!-- Logo + Top Nav -->
    <div id="top">
        <h1>{{ HTML::linkRoute($baseUrl, 'ReceiptClub ' . $baseName . ' Management System') }}</h1>
        <h4>{{ $shortName }}</h4>
        <div id="top-navigation">
            Welcome <a href="#"><strong>{{ $users->Email }}</strong></a>
            <span>|</span>
            {{ HTML::linkRoute('logout', 'Log out') }}
        </div>
    </div>
    <!-- End Logo + Top Nav -->

    <!-- Main Nav -->
<!--    <div id="navigation">
        <ul>
            <li>
                <a href="#" class="active"><span>Dashboard</span></a>
            </li>
        </ul>
    </div>-->
    <!-- End Main Nav -->
</div>