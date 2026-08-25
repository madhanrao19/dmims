@include('errors.layout', [
    'code' => 403,
    'title' => 'Access Denied',
    'message' => "You don't have permission to view this page. If you believe this is a mistake, contact your administrator.",
])
