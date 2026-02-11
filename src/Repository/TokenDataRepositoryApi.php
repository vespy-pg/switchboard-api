<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TokenInfo;
use App\Repository\Interface\TokenDataRepositoryInterface;
use App\Repository\Wrapper\PDOWrapper;
use App\Security\Exception\ApiTokenExpiredException;
use App\Security\Exception\ApiTokenValidationException;

class TokenDataRepositoryApi implements TokenDataRepositoryInterface
{
    protected PDOWrapper $pdo;
    private string $authDomain;
    private string $authTokenValidationEndpoint;
    private string $authMiddlewareToken;
    private bool $useHttp;

    public function __construct(
        PDOWrapper $pdo,
        string $authDomain,
        string $authTokenValidationEndpoint,
        string $authMiddlewareToken,
        bool $useHttp
    ) {
        $this->pdo = $pdo;
        $this->authDomain = $authDomain;
        $this->authTokenValidationEndpoint = $authTokenValidationEndpoint;
        $this->authMiddlewareToken = $authMiddlewareToken;
        $this->useHttp = $useHttp;
    }

    public function getTokenInfo($token): TokenInfo
    {
        $tokenArray = explode('.', $token);
        if (count($tokenArray) !== 3) {
            throw new ApiTokenValidationException('The JWT token must have two dots');
        }

        list($header, $payload, $signature) = $tokenArray;

        $decodedPayload = base64_decode($payload);
        if (!$decodedPayload) {
            throw new ApiTokenValidationException('Could not base64 decode JWT token');
        }

        try {
            $payloadArray = json_decode($decodedPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            throw new ApiTokenValidationException('Could not json decode JWT token');
        }

        // Send introspection request to auth API
        $data = ['token' => $token];

        if (!array_key_exists('subdomain', $payloadArray)) {
            throw new ApiTokenValidationException('Authentication failed: `subdomain` not found in the provided token');
        }

        if ($this->useHttp) {
            $protocol = 'http';
        } else {
            $protocol = 'https';
        }

        $url = $protocol . '://' . $payloadArray['subdomain'] . '.' . $this->authDomain . '/auth' .
            $this->authTokenValidationEndpoint;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Bearer ' . $this->authMiddlewareToken
        ]);
        curl_setopt($ch, CURLOPT_URL, $url);
        if (file_exists('/etc/ssl/cacert.pem')) {
            $certificate_location = '/etc/ssl/cacert.pem';
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $certificate_location);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $certificate_location);
        }
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, dirname(__DIR__) . '/../../../var/cookies.txt');
        curl_setopt($ch, CURLOPT_COOKIEFILE, dirname(__DIR__) . '/../../../var/cookies.txt');

        // the below is needed to allow dev container to connect to external auth over warp
        if ($_ENV['APP_ENV'] ?? '' === 'dev') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }

        $result = curl_exec($ch);

        if ($result === false) {
            $curlError = curl_error($ch);
            curl_close($ch);
            throw new ApiTokenValidationException(
                "Token validation failed using URL $url; curlError: $curlError"
            );
        }
        curl_close($ch);

        $tokenInfo = json_decode($result, true);
        if (array_key_exists('error', $tokenInfo) && array_key_exists('message', $tokenInfo['error'])) {
            throw new ApiTokenValidationException($tokenInfo['error']['message']);
        }

        if (!array_key_exists('active', $tokenInfo)) {
            throw new ApiTokenValidationException('Unexpected response received from auth: {tokenInfo}');
        }

        if (!$tokenInfo['active']) {
            throw new ApiTokenExpiredException('Token is no longer active');
        }

        return $this->arrayToEntity($tokenInfo);
    }

    public function arrayToEntity(array $data): TokenInfo
    {
        $tokenInfo = new TokenInfo($data['jti']);
        $tokenInfo->setExpiresAt($data['expires_at']);
        $tokenInfo->setTokenOwner($data['sub']);
        $tokenInfo->setUsername($data['username']);
        $tokenInfo->setUserType($data['user_type']);
        $tokenInfo->setClientName($data['client_name']);

        return $tokenInfo;
    }
}
