<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} REST API 文档</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body { margin: 0; }
        .topbar { display: none; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js" defer></script>
    <script>
        window.addEventListener('load', function () {
            window.ui = SwaggerUIBundle({
                url: '{{ route('api.openapi') }}',
                dom_id: '#swagger-ui',
                deepLinking: true,
                persistAuthorization: true,
                // Sanctum Bearer Token：点击右上角 Authorize 填入 /login 签发的 token
                withCredentials: false,
            });
        });
    </script>
</body>
</html>
