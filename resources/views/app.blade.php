<!DOCTYPE html>
<html class="h-full bg-white">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=mo-1">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/momo-favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=mo-1">

    @vite(['resources/js/app.ts'])
    @inertiaHead
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>
<body class="h-full subpixel-antialiased text-gray-900">
    @inertia
</body>
</html>
