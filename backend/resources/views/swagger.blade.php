<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} – API Documentation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui.css">
    <style>
        body { margin: 0; background: #fafafa; }
        .token-status {
            font-family: sans-serif; font-size: 14px; padding: 10px 20px;
            background: #1b2333; color: #f4f1ea;
        }
        .token-status b { color: #d9a24f; }
        .token-status button { margin-left: 12px; cursor: pointer; }
    </style>
</head>
<body>
<div class="token-status" id="token-status">Not logged in. Call <b>POST /auth/login</b> to get a token.</div>
<div id="swagger-ui"></div>

<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
<script nonce="{{ Vite::cspNonce() }}">
    // Remembers the JWT from login/register/refresh responses and sends it with every request,
    // so you can log in once and then try the protected endpoints directly.
    let token = null;
    const status = document.getElementById('token-status');

    function showStatus() {
        if (!token) {
            status.innerHTML = 'Not logged in. Call <b>POST /auth/login</b> to get a token.';
            return;
        }
        const role = JSON.parse(atob(token.split('.')[1])).role;
        status.innerHTML = 'Logged in as role <b>' + role + '</b>. The token is sent automatically with every request.'
            + '<button type="button" id="forget-token">Forget token</button>';
        // Inline onclick handlers are blocked by the Content-Security-Policy
        document.getElementById('forget-token').addEventListener('click', () => {
            token = null;
            showStatus();
        });
    }

    window.ui = SwaggerUIBundle({
        url: @json(url('/docs/api.json')),
        dom_id: '#swagger-ui',
        deepLinking: true,
        tryItOutEnabled: true,
        displayRequestDuration: true,
        requestInterceptor: (request) => {
            if (token && !request.headers.Authorization) {
                request.headers.Authorization = 'Bearer ' + token;
            }
            request.headers.Accept = 'application/json';
            return request;
        },
        responseInterceptor: (response) => {
            const newToken = response.body?.data?.token?.access_token;
            if (newToken) {
                token = newToken;
                showStatus();
            }
            if (response.url.endsWith('/auth/logout') && response.status === 204) {
                token = null;
                showStatus();
            }
            return response;
        },
    });
</script>
</body>
</html>
