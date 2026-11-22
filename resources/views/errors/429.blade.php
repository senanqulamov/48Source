@include('errors.approved', ['statusCode' => 429, 'title' => __('Too many requests'), 'description' => __('Please reduce the request rate and try again later.')])
