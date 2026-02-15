<!DOCTYPE html>
<html class="h-full bg-white">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="/images/favicon.png">

    @vite(['resources/js/app.ts'])
    @inertiaHead
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>
<body class="h-full subpixel-antialiased text-gray-900">
    @inertia
</body>
</html>
