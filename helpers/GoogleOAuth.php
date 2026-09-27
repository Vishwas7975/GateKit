<?php

class GoogleOAuth
{
    // Step 1: Build the Google login URL
    public static function getAuthUrl(): string
    {
        $params = http_build_query([
            'client_id'     => GOOGLE_CLIENT_ID,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'access_type'   => 'online',
            'prompt'        => 'select_account',
        ]);

        return 'https://accounts.google.com/o/oauth2/auth?' . $params;
    }

    // Step 2: Exchange the authorization code for an access token
    public static function getAccessToken(string $code): ?string
    {
        $response = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query([
                    'code'          => $code,
                    'client_id'     => GOOGLE_CLIENT_ID,
                    'client_secret' => GOOGLE_CLIENT_SECRET,
                    'redirect_uri'  => GOOGLE_REDIRECT_URI,
                    'grant_type'    => 'authorization_code',
                ]),
                'ignore_errors' => true,
            ],
        ]));

        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }

    // Step 3: Fetch the Google profile using the access token
    public static function getUserInfo(string $accessToken): ?array
    {
        $response = file_get_contents(
            'https://www.googleapis.com/oauth2/v2/userinfo',
            false,
            stream_context_create([
                'http' => [
                    'header'        => 'Authorization: Bearer ' . $accessToken,
                    'ignore_errors' => true,
                ],
            ])
        );

        if (!$response) {
            return null;
        }

        return json_decode($response, true);
    }
}
