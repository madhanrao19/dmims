@include('errors.layout', [
    'code' => 500,
    'title' => 'Something Went Wrong',
    'message' => "An unexpected error occurred on our end. It's been logged and we'll look into it — please try again shortly.",
])
