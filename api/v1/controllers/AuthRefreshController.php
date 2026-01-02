<?php

namespace ACA\Api\Controllers;

use ACA\Api\Core\Auth\JWTService;
use ACA\Api\Core\Auth\RefreshTokenService;
use ACA\Api\Core\Response;

class AuthRefreshController
{
    public function refresh(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['refresh_token'])) {
            Response::error('Refresh token required', 400);
            return;
        }

        $tokenRow = RefreshTokenService::findValid($input['refresh_token']);

        if (!$tokenRow) {
            // possible reuse attack
            RefreshTokenService::revokeChain($input['refresh_token']);
            Response::error('Invalid refresh token', 401);
            return;
        }

        $newRefreshToken = RefreshTokenService::rotate($tokenRow);

        $accessToken = JWTService::generate([
            'user_id' => $tokenRow['user_id']
        ]);

        Response::success([
            'access_token'  => $accessToken,
            'refresh_token' => $newRefreshToken,
            'token_type'    => 'Bearer'
        ]);
    }
}
