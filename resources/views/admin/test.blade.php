@if(Session::has('error'))
    <li>{{ Session::get('error') }}</li>
@endif

@if(Session::has('success'))
    <li>{{ Session::get('success') }}</li>
@endif
<a href="{{ route('admin.logout') }}">Logout</a>
