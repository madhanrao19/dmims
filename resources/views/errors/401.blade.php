@include('errors.layout', [
    'code' => 401,
    'title' => 'Authentication Required',
    'message' => 'You need to sign in to view this page.',
])
